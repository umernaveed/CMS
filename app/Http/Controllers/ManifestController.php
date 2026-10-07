<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CustomsOffice;
use App\Models\LoadingPort;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\Outlet;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\SystemSetting;
use App\Mail\TenantTemplateMail;
use App\Services\TenantMailService;
use App\Support\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManifestController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $manifests = $this->filteredTenantManifests($request)
            ->withCount('items')
            ->with(['customsOffice', 'loadingPort'])
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());
        $customsOffices = $this->customsOffices();
        $loadingPorts = $this->loadingPorts();
        $statuses = PackageStatus::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('courier.manifests.index', compact('manifests', 'customsOffices', 'loadingPorts', 'statuses'));
    }

    public function create()
    {
        return view('courier.manifests.create', $this->formData(new Manifest([
            'manifest_number' => $this->nextManifestNumber(),
            'date_of_departure' => now()->toDateString(),
            'exporter_name' => auth()->user()->tenant->company_name ?? '',
        ])));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->validated($request);
        $packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);
        $filePath = $this->storeManifestFile($request);
        unset($data['manifest_file']);

        $manifest = DB::transaction(function () use ($data, $packageIds, $tenantId, $filePath) {
            $manifest = Manifest::create(array_merge($data, [
                'tenant_id' => $tenantId,
                'status' => 'draft',
                'file_path' => $filePath,
            ]));

            $this->syncPackages($manifest, $packageIds);

            return $manifest;
        });

        ActivityLog::record('created', 'manifests', 'Created manifest '.$manifest->manifest_number, [
            'manifest_id' => $manifest->id,
            'packages' => count($packageIds),
        ]);
        $this->emailManifestCreated($manifest->fresh(['tenant']));

        return redirect()->route('courier.manifests.show', $manifest)->with('success', 'Manifest created successfully.');
    }

    public function show(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest)
            ->load(['customsOffice', 'loadingPort', 'submitter', 'items.package.customer.user', 'items.package.status', 'items.package.invoiceItems.invoice']);

        $availablePackages = $this->availablePackages($manifest->id)->get();

        return view('courier.manifests.show', compact('manifest', 'availablePackages'));
    }

    public function edit(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);
        $this->ensureManifestEditable($manifest);

        return view('courier.manifests.edit', $this->formData($manifest));
    }

    public function update(Request $request, Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);
        $this->ensureManifestEditable($manifest);
        $data = $this->validated($request, $manifest->id);
        $packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);
        if ($filePath = $this->storeManifestFile($request)) {
            $data['file_path'] = $filePath;
        }
        unset($data['manifest_file']);

        DB::transaction(function () use ($manifest, $data, $packageIds) {
            $manifest->update($data);
            $this->syncPackages($manifest, $packageIds, true);
        });

        ActivityLog::record('updated', 'manifests', 'Updated manifest '.$manifest->manifest_number, [
            'manifest_id' => $manifest->id,
        ]);

        return redirect()->route('courier.manifests.show', $manifest)->with('success', 'Manifest updated successfully.');
    }

    public function destroy(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);
        $this->ensureManifestEditable($manifest);
        if ($manifest->items()->exists()) {
            return back()->withErrors(['manifest' => 'Move or remove all packages before deleting this manifest. Closed manifests cannot be deleted or modified.']);
        }
        $number = $manifest->manifest_number;

        DB::transaction(function () use ($manifest) {
            $manifest->delete();
        });

        ActivityLog::record('deleted', 'manifests', 'Deleted manifest '.$number);

        return redirect()->route('courier.manifests.index')->with('success', 'Manifest deleted successfully.');
    }

    public function addPackages(Request $request, Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);
        $this->ensureManifestEditable($manifest);
        $data = $request->validate([
            'package_ids' => 'required|array|min:1',
            'package_ids.*' => 'integer',
        ]);

        $this->syncPackages($manifest, $data['package_ids']);

        return back()->with('success', 'Packages added to manifest.');
    }

    public function removePackage(Manifest $manifest, Package $package)
    {
        $manifest = $this->authorizeManifest($manifest);
        $this->ensureManifestEditable($manifest);
        abort_unless($package->tenant_id === auth()->user()->tenant_id && $package->manifest_id === $manifest->id, 404);

        ManifestItem::where('manifest_id', $manifest->id)->where('package_id', $package->id)->delete();
        $package->update(['manifest_id' => null, 'is_in_manifest' => false]);

        return back()->with('success', 'Package removed from manifest.');
    }

    public function updatePackageStatuses(Request $request, Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'status_id' => 'required|integer',
        ]);

        $status = PackageStatus::where('tenant_id', $tenantId)->findOrFail($data['status_id']);
        $packages = $manifest->packages()->with(['customer.user'])->get();

        if ($packages->isEmpty()) {
            return back()->with('warning', 'No packages are attached to this manifest.');
        }

        $updatedCount = 0;
        foreach ($packages as $package) {
            if ((int) $package->status_id === (int) $status->id) {
                continue;
            }

            PackageLog::create([
                'tenant_id' => $tenantId,
                'package_id' => $package->id,
                'status_id' => $status->id,
                'note' => 'Manifest package status updated',
                'location' => optional(auth()->user()->outlet)->name,
                'added_by' => auth()->id(),
                'created_at' => now(),
            ]);
            $updatedCount++;
        }

        if ($updatedCount === 0) {
            return back()->with('info', 'All packages already have the selected status.');
        }

        ActivityLog::record('updated', 'manifests', 'Bulk updated package status on manifest '.$manifest->manifest_number, [
            'manifest_id' => $manifest->id,
            'status_id' => $status->id,
            'packages_updated' => $updatedCount,
        ]);

        return back()->with('success', 'Updated status for '.$updatedCount.' package(s) and notified customers.');
    }

    public function submit(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);

        if ($manifest->items()->count() === 0) {
            return back()->withErrors(['manifest' => 'Add at least one package before submitting this manifest.']);
        }

        $manifest->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        ActivityLog::record('submitted', 'manifests', 'Submitted manifest '.$manifest->manifest_number, [
            'manifest_id' => $manifest->id,
        ]);

        return back()->with('success', 'Manifest submitted successfully.');
    }

    public function export(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest)
            ->load(['items.package.customer.user', 'items.package.status']);

        $airWeightLb = $this->manifestWeightLb($manifest);
        $airWeightKg = $this->manifestWeightKg($manifest);
        $seaVolumeCuft = $this->manifestVolumeCuft($manifest);
        $seaVolumeCbm = $this->manifestVolumeCbm($manifest);

        $lines = [
            'MANIFEST '.$manifest->manifest_number,
            str_repeat('=', 60),
            'Cargo Company: '.$manifest->cargo_company,
            'Departure Date: '.optional($manifest->date_of_departure)->format('Y-m-d'),
            'Packages: '.$manifest->items->count(),
            'Total Value: '.$this->manifestValueDisplay($manifest),
            '',
            'PACKAGE LIST',
            str_repeat('-', 60),
        ];

        if ($airWeightLb > 0) {
            array_splice($lines, 5, 0, 'Total Air Weight: '.number_format($airWeightLb, 2).' lb / '.number_format($airWeightKg, 2).' kg');
        }

        if ($seaVolumeCuft > 0 || $seaVolumeCbm > 0) {
            array_splice($lines, 5, 0, 'Total Sea Volume: '.number_format($seaVolumeCuft, 2).' cu.ft / '.number_format($seaVolumeCbm, 3).' CBM');
        }

        foreach ($manifest->items as $item) {
            $package = $item->package;
            if (! $package) {
                continue;
            }
            $lines[] = $package->tracking_number.' | '.($package->supplier_tracking ?: '-').' | '.($package->mailbox ?: '-').' | '.$package->measureDisplay();
            $lines[] = (optional(optional($package->customer)->user)->full_name ?: '-').' | '.($package->merchant ?: '-');
            $lines[] = ($package->description ?: '-').' | '.(optional($package->status)->name ?: '-').' | '.$package->manifestValueDisplay();
            $lines[] = '';
        }

        return SimplePdf::download(Str::slug($manifest->manifest_number).'-manifest.pdf', $lines);
    }

    public function exportList(Request $request): StreamedResponse
    {
        $manifests = $this->filteredTenantManifests($request)
            ->withCount('items')
            ->with(['customsOffice', 'loadingPort'])
            ->latest()
            ->get();

        ActivityLog::record('exported', 'manifests', 'Exported filtered manifests CSV');

        return response()->streamDownload(function () use ($manifests) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['manifest_number', 'cargo_company', 'customs_office', 'loading_port', 'departure_date', 'status', 'package_count', 'master_air_bill', 'voyage_number', 'carrier_code']);
            foreach ($manifests as $manifest) {
                fputcsv($out, [
                    $manifest->manifest_number,
                    $manifest->cargo_company,
                    optional($manifest->customsOffice)->name,
                    optional($manifest->loadingPort)->name,
                    optional($manifest->date_of_departure)->format('Y-m-d'),
                    $manifest->status,
                    $manifest->items_count,
                    $manifest->master_air_bill,
                    $manifest->voyage_number,
                    $manifest->carrier_code,
                ]);
            }
            fclose($out);
        }, 'manifests-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function printList(Request $request)
    {
        $manifests = $this->filteredTenantManifests($request)
            ->withCount('items')
            ->with(['customsOffice', 'loadingPort'])
            ->latest()
            ->get();

        return view('courier.manifests.print-list', compact('manifests'));
    }

    public function print(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest)
            ->load(['customsOffice', 'loadingPort', 'submitter', 'items.package.customer.user', 'items.package.status', 'items.package.invoiceItems.invoice']);

        return view('courier.manifests.print', compact('manifest'));
    }

    public function download(Manifest $manifest)
    {
        $manifest = $this->authorizeManifest($manifest);

        if ($manifest->file_path && Storage::disk('public')->exists($manifest->file_path)) {
            return Storage::disk('public')->download($manifest->file_path);
        }

        return $this->export($manifest);
    }

    public function template(): StreamedResponse
    {
        $columns = [
            'manifest_number',
            'cargo_company',
            'customs_office',
            'date_of_departure',
            'shipping_agent_code',
            'exporter_name',
            'exporter_address',
            'loading_port',
            'master_air_bill',
            'voyage_number',
            'carrier_code',
            'package_tracking_numbers',
            'status',
        ];

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, ['MF-'.now()->format('Ymd').'-0001', 'Cargo Co', 'Kingston', now()->toDateString(), 'AGT01', auth()->user()->tenant->company_name ?? '', 'Exporter address', 'Miami', 'MAWB123', 'V001', 'C001', 'TRK123|TRK456', 'draft']);
            fclose($out);
        }, 'manifest-bulk-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($column) => Str::snake(trim((string) $column)), fgetcsv($handle) ?: []);
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (! $data || ! $this->createManifestFromImport($data)) {
                $skipped++;
                continue;
            }
            $created++;
        }
        fclose($handle);

        ActivityLog::record('bulk_imported', 'manifests', 'Bulk uploaded '.$created.' manifests; skipped '.$skipped);

        return redirect()->route('courier.manifests.index')->with('success', 'Bulk upload complete. Created '.$created.' manifests; skipped '.$skipped.'.');
    }

    private function formData(Manifest $manifest): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'manifest' => $manifest,
            'customsOffices' => CustomsOffice::where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })->orderBy('name')->get(),
            'loadingPorts' => LoadingPort::where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })->orderBy('name')->get(),
            'outlets' => Outlet::where('tenant_id', $tenantId)
                ->when(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id, fn ($query) => $query->where('id', auth()->user()->outlet_id))
                ->orderBy('name')
                ->get(),
            'cargoCompanies' => collect(preg_split('/\r\n|\r|\n/', (string) optional(SystemSetting::where('key', 'manifest_cargo_companies')->first())->value))
                ->map(fn ($company) => trim($company))
                ->filter()
                ->values(),
            'availablePackages' => $this->availablePackages($manifest->id)->get(),
            'selectedPackageIds' => $manifest->exists ? $manifest->packages()->pluck('packages.id')->all() : [],
        ];
    }

    private function validated(Request $request, ?int $manifestId = null): array
    {
        $tenantId = auth()->user()->tenant_id;

        return $request->validate([
            'manifest_number' => 'required|string|max:100|unique:manifests,manifest_number,'.$manifestId.',id,tenant_id,'.$tenantId,
            'cargo_company' => 'nullable|string|max:255',
            'customs_office_id' => 'nullable|exists:customs_offices,id',
            'date_of_departure' => 'nullable|date',
            'shipping_agent_code' => 'nullable|string|max:100',
            'exporter_name' => 'nullable|string|max:255',
            'exporter_address' => 'nullable|string',
            'loading_port_id' => 'nullable|exists:loading_ports,id',
            'master_air_bill' => 'nullable|string|max:255',
            'voyage_number' => 'nullable|string|max:255',
            'carrier_code' => 'nullable|string|max:255',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer',
            'manifest_file' => 'nullable|file|mimes:pdf,csv,txt,xlsx,xls,jpg,jpeg,png|max:8192',
        ]);
    }

    private function syncPackages(Manifest $manifest, array $packageIds, bool $replace = false): void
    {
        $tenantId = auth()->user()->tenant_id;
        $packageIds = Package::where('tenant_id', $tenantId)
            ->whereIn('id', $packageIds)
            ->where(function ($query) use ($manifest) {
                $query->whereNull('manifest_id')->orWhere('manifest_id', $manifest->id);
            })
            ->pluck('id')
            ->all();

        if ($replace) {
            Package::where('tenant_id', $tenantId)
                ->where('manifest_id', $manifest->id)
                ->whereNotIn('id', $packageIds)
                ->update(['manifest_id' => null, 'is_in_manifest' => false]);

            ManifestItem::where('manifest_id', $manifest->id)
                ->whereNotIn('package_id', $packageIds)
                ->delete();
        }

        foreach ($packageIds as $packageId) {
            ManifestItem::firstOrCreate([
                'tenant_id' => $tenantId,
                'manifest_id' => $manifest->id,
                'package_id' => $packageId,
            ]);
        }

        Package::where('tenant_id', $tenantId)
            ->whereIn('id', $packageIds)
            ->update(['manifest_id' => $manifest->id, 'is_in_manifest' => true]);
    }

    private function availablePackages(?int $manifestId = null)
    {
        $query = Package::where('tenant_id', auth()->user()->tenant_id)
            ->with(['customer.user', 'status', 'outlet'])
            ->orderByDesc('date_received')
            ->orderByDesc('id');

        $query->where(function ($query) use ($manifestId) {
            $query->whereNull('manifest_id');
            if ($manifestId) {
                $query->orWhere('manifest_id', $manifestId);
            }
        });

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function ensureManifestEditable(Manifest $manifest): void
    {
        if ($manifest->status !== 'draft') {
            abort(422, 'Closed or submitted manifests cannot be modified or deleted.');
        }
    }

    private function manifestWeightLb(Manifest $manifest): float
    {
        return (float) $manifest->items->sum(fn ($item) => optional($item->package)->isSeaFreight() ? 0 : (float) optional($item->package)->weight);
    }

    private function manifestWeightKg(Manifest $manifest): float
    {
        return $this->manifestWeightLb($manifest) * 0.45359237;
    }

    private function manifestVolumeCuft(Manifest $manifest): float
    {
        return (float) $manifest->items->sum(function ($item) {
            $package = $item->package;

            return $package && $package->isSeaFreight() ? (float) ($package->volume_cuft ?: $package->weight) : 0;
        });
    }

    private function manifestVolumeCbm(Manifest $manifest): float
    {
        return (float) $manifest->items->sum(function ($item) {
            $package = $item->package;
            if (! $package || ! $package->isSeaFreight()) {
                return 0;
            }

            $cuft = (float) ($package->volume_cuft ?: $package->weight);

            return (float) ($package->volume_cbm ?: ($cuft > 0 ? $cuft / \App\Models\WeightRate::CBM_TO_CUFT : 0));
        });
    }

    private function manifestValueDisplay(Manifest $manifest): string
    {
        $totals = $manifest->items
            ->map(fn ($item) => $item->package)
            ->filter()
            ->groupBy(fn (Package $package) => $package->manifestValueCurrency())
            ->map(fn ($packages) => (float) $packages->sum(fn (Package $package) => $package->manifestValueAmount()));

        return $totals->isEmpty()
            ? 'JMD 0.00'
            : $totals->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))->implode(' / ');
    }
    private function manifestValue(Manifest $manifest): float
    {
        return (float) $manifest->items->sum(fn ($item) => (float) optional($item->package)->price_jmd);
    }

    private function tenantManifests()
    {
        return Manifest::where('tenant_id', auth()->user()->tenant_id);
    }

    private function filteredTenantManifests(Request $request)
    {
        return $this->tenantManifests()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('manifest_number', 'like', $term)
                        ->orWhere('cargo_company', 'like', $term)
                        ->orWhere('master_air_bill', 'like', $term)
                        ->orWhere('voyage_number', 'like', $term)
                        ->orWhere('carrier_code', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('customs_office_id'), fn ($query) => $query->where('customs_office_id', $request->integer('customs_office_id')))
            ->when($request->filled('loading_port_id'), fn ($query) => $query->where('loading_port_id', $request->integer('loading_port_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date_of_departure', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date_of_departure', '<=', $request->date_to));
    }

    private function customsOffices()
    {
        $tenantId = auth()->user()->tenant_id;

        return CustomsOffice::where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();
    }

    private function loadingPorts()
    {
        $tenantId = auth()->user()->tenant_id;

        return LoadingPort::where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();
    }

    private function storeManifestFile(Request $request): ?string
    {
        if (! $request->hasFile('manifest_file')) {
            return null;
        }

        return $request->file('manifest_file')->store('manifest-files', 'public');
    }

    private function createManifestFromImport(array $data): bool
    {
        $tenantId = auth()->user()->tenant_id;
        $number = trim((string) ($data['manifest_number'] ?? ''));
        if ($number === '' || Manifest::where('tenant_id', $tenantId)->where('manifest_number', $number)->exists()) {
            return false;
        }

        $manifest = Manifest::create([
            'tenant_id' => $tenantId,
            'manifest_number' => $number,
            'cargo_company' => $data['cargo_company'] ?? null,
            'customs_office_id' => $this->customsOfficeFromImport($data),
            'date_of_departure' => ! empty($data['date_of_departure']) ? $data['date_of_departure'] : null,
            'shipping_agent_code' => $data['shipping_agent_code'] ?? null,
            'exporter_name' => $data['exporter_name'] ?? null,
            'exporter_address' => $data['exporter_address'] ?? null,
            'loading_port_id' => $this->loadingPortFromImport($data),
            'master_air_bill' => $data['master_air_bill'] ?? null,
            'voyage_number' => $data['voyage_number'] ?? null,
            'carrier_code' => $data['carrier_code'] ?? null,
            'status' => in_array($data['status'] ?? 'draft', ['draft', 'submitted', 'approved', 'rejected'], true) ? $data['status'] : 'draft',
        ]);

        $trackingNumbers = collect(explode('|', (string) ($data['package_tracking_numbers'] ?? '')))
            ->map(fn ($tracking) => trim($tracking))
            ->filter()
            ->values()
            ->all();

        if ($trackingNumbers) {
            $packageIds = Package::where('tenant_id', $tenantId)
                ->whereIn('tracking_number', $trackingNumbers)
                ->pluck('id')
                ->all();
            $this->syncPackages($manifest, $packageIds);
        }
        $this->emailManifestCreated($manifest->fresh(['tenant']));

        return true;
    }

    private function emailManifestCreated(Manifest $manifest): void
    {
        $manifest->loadMissing('tenant');
        $email = optional($manifest->tenant)->email;
        if (! $email) {
            return;
        }

        $subject = 'Manifest created: '.$manifest->manifest_number;
        try {
            app(TenantMailService::class)->send($manifest->tenant_id, $email, new TenantTemplateMail($subject, [
                'tenant' => $manifest->tenant,
                'subject' => $subject,
                'headline' => 'Manifest created',
                'bodyHtml' => '<p>A manifest has been created in your courier portal.</p>',
                'details' => [
                    'Manifest Number' => $manifest->manifest_number,
                    'Cargo Company' => $manifest->cargo_company ?: '-',
                    'Departure Date' => optional($manifest->date_of_departure)->format('M d, Y') ?: '-',
                    'Status' => ucfirst(str_replace('_', ' ', $manifest->status)),
                ],
                'buttonLabel' => 'Open Manifests',
                'buttonUrl' => route('courier.manifests.show', $manifest),
                'footerHtml' => '',
                'footerText' => '',
                'attachmentUrl' => '',
            ]));
        } catch (Throwable $exception) {
            Log::warning('Manifest created email failed', [
                'manifest_id' => $manifest->id,
                'tenant_id' => $manifest->tenant_id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function customsOfficeFromImport(array $data): ?int
    {
        $query = CustomsOffice::where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id));
        if (! empty($data['customs_office_id'])) {
            return (clone $query)->whereKey($data['customs_office_id'])->value('id');
        }
        if (! empty($data['customs_office'])) {
            return (clone $query)
                ->where(fn ($officeQuery) => $officeQuery->where('name', $data['customs_office'])->orWhere('code', $data['customs_office']))
                ->value('id');
        }

        return null;
    }

    private function loadingPortFromImport(array $data): ?int
    {
        $query = LoadingPort::where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id));
        if (! empty($data['loading_port_id'])) {
            return (clone $query)->whereKey($data['loading_port_id'])->value('id');
        }
        if (! empty($data['loading_port'])) {
            return (clone $query)
                ->where(fn ($portQuery) => $portQuery->where('name', $data['loading_port'])->orWhere('code', $data['loading_port']))
                ->value('id');
        }

        return null;
    }

    private function authorizeManifest(Manifest $manifest): Manifest
    {
        abort_unless($manifest->tenant_id === auth()->user()->tenant_id, 404);

        return $manifest;
    }

    private function nextManifestNumber(): string
    {
        $prefix = 'MF-'.now()->format('Ymd');
        $count = Manifest::where('tenant_id', auth()->user()->tenant_id)
            ->where('manifest_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}


