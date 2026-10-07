<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\PackageStatus;
use App\Models\PackageLog;
use App\Models\PackageType;
use App\Models\PreAlert;
use App\Models\PreAlertReceipt;
use App\Models\PackagePurchaseReceipt;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\WeightRate;
use App\Models\ActivityLog;
use App\Models\TenantSetting;
use App\Models\ShippingMode;
use App\Models\SuperadminLogisticsPackage;
use App\Services\CustomerNotificationService;
use App\Services\CourierNotificationService;
use App\Services\SubscriptionLimitService;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $packages = $this->filteredTenantPackages($request)
            ->with(['customer.user', 'status', 'invoiceItems.invoice'])
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());
        $customers = $this->tenantCustomers()->with('user')->orderBy('mailbox')->get();
        $statuses = PackageStatus::where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->get();

        $shippingModes = $this->shippingModes($tenantId);
        $handoverLogs = PackageLog::where('tenant_id', $tenantId)
            ->where('note', 'like', '%handoff%')
            ->with(['package.customer.user', 'status', 'user'])
            ->latest('created_at')
            ->limit(25)
            ->get();

        return view('courier.packages.index', compact('packages', 'customers', 'statuses', 'shippingModes', 'handoverLogs'));
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;
        $preAlert = $this->preAlertFromRequest(request());
        $logisticsPackage = $this->logisticsPackageFromRequest(request());
        $customers = $this->tenantCustomers()->with('user')->orderBy('mailbox')->get();
        $statuses = PackageStatus::where('tenant_id', $tenantId)->orderBy('sort_order')->get();
        $types = PackageType::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();
        $rates = WeightRate::where('tenant_id', $tenantId)->where('is_active', true)->with('outlet')->orderBy('freight_type')->orderBy('min_weight')->get();
        $shippingModes = $this->shippingModes($tenantId);
        $billingWeightMode = $this->billingWeightMode($tenantId);
        return view('courier.packages.create', compact('customers', 'statuses', 'types', 'rates', 'shippingModes', 'preAlert', 'logisticsPackage', 'billingWeightMode'));
    }

    public function store(Request $request, CustomerNotificationService $notifications, SubscriptionLimitService $limits)
    {
        $tenantId = auth()->user()->tenant_id;

        ShippingMode::ensureDefaultsForTenant($tenantId);

        $data = $request->validate([
            'tracking_number' => 'nullable|string|max:150|unique:packages,tracking_number,NULL,id,tenant_id,' . $tenantId,
            'customer_id' => 'nullable|exists:customers,id',
            'status_id' => 'nullable|exists:package_statuses,id',
            'package_type_id' => 'nullable|exists:package_types,id',
            'supplier_tracking' => 'nullable|string|max:150',
            'mailbox' => 'nullable|string|max:100',
            'courier' => 'nullable|string|max:100',
            'merchant' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'region' => 'nullable|string|max:40',
            'volume_cuft' => 'nullable|numeric|min:0',
            'volume_cbm' => 'nullable|numeric|min:0',
            'unknown_fee_jmd' => 'nullable|numeric|min:0',
            'unknown_fee_reason' => 'nullable|string|max:255',
            'price_usd' => 'nullable|numeric|min:0',
            'price_jmd' => 'nullable|numeric|min:0',
            'freight_type' => ['required', Rule::exists('shipping_modes', 'code')->where('tenant_id', $tenantId)->where('is_active', true)],
            'delivery_type' => 'required|in:pickup,delivery',
            'date_received' => 'nullable|date',
            'purchase_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:4096',
            'notes' => 'nullable|string',
            'logistics_package_id' => 'nullable|integer',
        ]);

        $logisticsPackage = $this->logisticsPackageForStore($tenantId, $data['logistics_package_id'] ?? null);
        if ($logisticsPackage) {
            $data = $this->mergeLogisticsPackageData($data, $logisticsPackage);
        }

        $preAlert = $this->matchingPreAlert($tenantId, $data['supplier_tracking'] ?? null, $data['customer_id'] ?? null);
        if ($preAlert) {
            $data = $this->mergePreAlertData($data, $preAlert);
        }

        $data = $this->normalizeRegionalPackageData($tenantId, $data);

        $customer = ! empty($data['customer_id'])
            ? $this->tenantCustomers()->findOrFail($data['customer_id'])
            : null;
        $limits->ensureCanCreate($tenantId, 'packages_month');

        $statusId = $this->tenantStatusId($tenantId, $data['status_id'] ?? null);
        $data['package_type_id'] = $this->tenantPackageTypeId($tenantId, $data['package_type_id'] ?? null);
        $data['price_jmd'] = $this->packagePrice($tenantId, $data);
        $data['processing_fee_jmd'] = $this->processingFee($tenantId, $data);
        $data['storage_days'] = $data['storage_days'] ?? $this->storageDaysFromDate($data['date_received'] ?? null);
        $data['storage_fee'] = $this->storageFee($tenantId, $data['storage_days'] ?? 0);
        $receiptPath = $this->storeReceipt($request);
        if (! $receiptPath && $preAlert && $preAlert->receipts->isNotEmpty()) {
            $receiptPath = $preAlert->receipts->first()->path;
        }
        unset($data['purchase_receipt']);
        unset($data['logistics_package_id']);
        $data['tracking_number'] = $this->normalizeTrackingNumber($data['tracking_number'] ?? null, $tenantId);

        $package = Package::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'mailbox' => optional($customer)->mailbox ?: Customer::normalizeMailbox($data['mailbox'] ?? null),
            'outlet_id' => optional($customer)->outlet_id,
            'status_id' => $statusId,
            'date_received' => $data['date_received'] ?? now()->toDateString(),
            'invoice_file' => $receiptPath,
        ]));

        if ($preAlert) {
            $preAlert->update([
                'status' => 'converted',
                'package_id' => $package->id,
            ]);
        }

        if ($logisticsPackage) {
            $logisticsPackage->update(['courier_package_id' => $package->id]);
        }

        PackageLog::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status_id' => $package->status_id,
            'note' => 'Package created',
            'location' => optional(auth()->user()->outlet)->name,
            'added_by' => auth()->id(),
            'created_at' => now(),
        ]);

        ActivityLog::record('created', 'packages', 'Created package '.$package->tracking_number, [
            'package_id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'mailbox' => $package->mailbox,
        ]);

        $notifications->packageCreated($package->fresh(['tenant', 'customer.user', 'status']));

        return redirect()->route('courier.packages.index')->with('success', 'Package created successfully');
    }

    public function show($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $package = $this->tenantPackages()
            ->with(['customer.user', 'status', 'packageType', 'invoiceItems.invoice', 'logs' => fn ($query) => $query->with(['status', 'user'])->latest('created_at')])
            ->findOrFail($id);
        $statuses = PackageStatus::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $customers = $this->tenantCustomers()->with('user')->orderBy('mailbox')->get();
        return view('courier.packages.show', compact('package', 'statuses', 'customers'));
    }

    public function edit($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $package = $this->tenantPackages()->findOrFail($id);
        $customers = $this->tenantCustomers()->with('user')->orderBy('mailbox')->get();
        $statuses = PackageStatus::where('tenant_id', $tenantId)->orderBy('sort_order')->get();
        $types = PackageType::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();
        $rates = WeightRate::where('tenant_id', $tenantId)->where('is_active', true)->with('outlet')->orderBy('freight_type')->orderBy('min_weight')->get();
        $shippingModes = $this->shippingModes($tenantId);
        $billingWeightMode = $this->billingWeightMode($tenantId);
        return view('courier.packages.edit', compact('package', 'customers', 'statuses', 'types', 'rates', 'shippingModes', 'billingWeightMode'));
    }

    public function update(Request $request, $id, CustomerNotificationService $notifications, SubscriptionLimitService $limits)
    {
        $tenantId = auth()->user()->tenant_id;
        ShippingMode::ensureDefaultsForTenant($tenantId);
        $package = $this->tenantPackages()->findOrFail($id);

        $data = $request->validate([
            'tracking_number' => 'nullable|string|max:150|unique:packages,tracking_number,' . $id . ',id,tenant_id,' . $tenantId,
            'customer_id' => 'nullable|exists:customers,id',
            'status_id' => 'nullable|exists:package_statuses,id',
            'package_type_id' => 'nullable|exists:package_types,id',
            'supplier_tracking' => 'nullable|string|max:150',
            'mailbox' => 'nullable|string|max:100',
            'courier' => 'nullable|string|max:100',
            'merchant' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'region' => 'nullable|string|max:40',
            'volume_cuft' => 'nullable|numeric|min:0',
            'volume_cbm' => 'nullable|numeric|min:0',
            'unknown_fee_jmd' => 'nullable|numeric|min:0',
            'unknown_fee_reason' => 'nullable|string|max:255',
            'price_usd' => 'nullable|numeric|min:0',
            'price_jmd' => 'nullable|numeric|min:0',
            'freight_type' => ['required', Rule::exists('shipping_modes', 'code')->where('tenant_id', $tenantId)->where('is_active', true)],
            'delivery_type' => 'required|in:pickup,delivery',
            'storage_days' => 'nullable|integer|min:0',
            'storage_fee' => 'nullable|numeric|min:0',
            'date_received' => 'nullable|date',
            'date_delivered' => 'nullable|date',
            'purchase_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:4096',
            'notes' => 'nullable|string',
        ]);

        if (! empty($data['customer_id'])) {
            $customer = $this->tenantCustomers()->findOrFail($data['customer_id']);
            $data['outlet_id'] = $customer->outlet_id;
            $data['mailbox'] = $data['mailbox'] ?? $customer->mailbox;
        }
        $limits->ensureStorageDaysAllowed($tenantId, $data['storage_days'] ?? null);
        $data = $this->normalizeRegionalPackageData($tenantId, $data);
        $data['price_jmd'] = $this->packagePrice($tenantId, $data);
        $data['processing_fee_jmd'] = $this->processingFee($tenantId, $data);
        $data['storage_days'] = $data['storage_days'] ?? $this->storageDaysFromDate($data['date_received'] ?? null);
        $data['storage_fee'] = $this->storageFee($tenantId, $data['storage_days'] ?? 0);

        $data['status_id'] = $this->tenantStatusId($tenantId, $data['status_id'] ?? null);
        $data['package_type_id'] = $this->tenantPackageTypeId($tenantId, $data['package_type_id'] ?? null);
        if ($receiptPath = $this->storeReceipt($request)) {
            $data['invoice_file'] = $receiptPath;
        }
        unset($data['purchase_receipt']);
        $data['tracking_number'] = $this->normalizeTrackingNumber($data['tracking_number'] ?? $package->tracking_number, $tenantId);
        $oldStatus = $package->status_id;
        $package->update($data);

        if ($oldStatus !== $package->status_id) {
            $log = PackageLog::create([
                'tenant_id' => $tenantId,
                'package_id' => $package->id,
                'status_id' => $package->status_id,
                'note' => 'Status updated',
                'location' => optional(auth()->user()->outlet)->name,
                'added_by' => auth()->id(),
                'created_at' => now(),
            ]);

        }

        ActivityLog::record('updated', 'packages', 'Updated package '.$package->tracking_number, [
            'package_id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'status_id' => $package->status_id,
        ]);

        return redirect()->route('courier.packages.index')->with('success', 'Package updated successfully');
    }

    public function destroy($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $package = $this->tenantPackages()->findOrFail($id);
        $trackingNumber = $package->tracking_number;
        $package->delete();

        ActivityLog::record('deleted', 'packages', 'Deleted package '.$trackingNumber);

        return redirect()->route('courier.packages.index')->with('success', 'Package deleted successfully');
    }

    public function export(Request $request): StreamedResponse
    {
        $packages = $this->filteredTenantPackages($request)
            ->with(['customer.user', 'status', 'invoiceItems.invoice'])
            ->latest()
            ->get();

        ActivityLog::record('exported', 'packages', 'Exported filtered packages CSV');

        return $this->streamPackagesCsv($packages, 'packages-'.now()->format('Ymd-His').'.csv');
    }

    public function template(): StreamedResponse
    {
        $columns = [
            'tracking_number',
            'mailbox',
            'customer_email',
            'supplier_tracking',
            'courier',
            'merchant',
            'description',
            'weight',
            'price_usd',
            'price_jmd',
            'freight_type',
            'delivery_type',
            'status',
            'storage_days',
            'storage_fee',
            'processing_fee_jmd',
            'date_received',
            'notes',
        ];

        return response()->streamDownload(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, ['', 'MBX-1001', 'customer@example.com', 'SUP123', 'DHL', 'Amazon', 'Shoes', '2.50', '45.00', '', 'air', 'pickup', 'Received', '0', '0', '0', now()->toDateString(), 'Leave tracking_number blank to auto-generate the HAWB.']);
            fclose($handle);
        }, 'package-bulk-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, SubscriptionLimitService $limits)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($column) => Str::snake(trim((string) $column)), fgetcsv($handle) ?: []);
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (! $data || $this->createPackageFromImport($data, $limits) === false) {
                $skipped++;
                continue;
            }
            $created++;
        }
        fclose($handle);

        ActivityLog::record('bulk_imported', 'packages', 'Bulk uploaded '.$created.' packages; skipped '.$skipped);

        return redirect()->route('courier.packages.index')->with('success', 'Bulk upload complete. Created '.$created.' packages; skipped '.$skipped.'.');
    }

    public function print(Request $request)
    {
        $packages = $this->filteredTenantPackages($request)
            ->with(['customer.user', 'status', 'invoiceItems.invoice'])
            ->latest()
            ->get();

        return view('courier.packages.print', compact('packages'));
    }

    public function preAlerts(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $preAlerts = PreAlert::where('tenant_id', $tenantId)
            ->with(['customer.user', 'receipts', 'package'])
            ->when(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id, fn ($query) => $query->where('outlet_id', auth()->user()->outlet_id))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('pre_alert_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('courier', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhereHas('customer.user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();

        return view('courier.pre-alerts.index', compact('preAlerts'));
    }

    public function preAlertReceipt(PreAlertReceipt $receipt)
    {
        abort_if((int) $receipt->preAlert->tenant_id !== (int) auth()->user()->tenant_id, 404);
        abort_if(! Storage::disk('public')->exists($receipt->path), 404);

        return response()->file(Storage::disk('public')->path($receipt->path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($receipt->original_name ?: basename($receipt->path)).'"',
        ]);
    }

    public function aging(Request $request, CourierNotificationService $courierNotifications)
    {
        $tenantId = auth()->user()->tenant_id;
        $query = $this->filteredTenantPackages($request);
        $minimumAge = max((int) $request->input('min_age', TenantSetting::getValue($tenantId, 'storage_free_days', 7)), 0);
        $this->applyAgingEligibility($query, $minimumAge);

        $packages = $query
            ->with(['customer.user', 'status', 'invoiceItems.invoice'])
            ->oldest('date_received')
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());
        $customers = $this->tenantCustomers()->with('user')->orderBy('mailbox')->get();
        $statuses = $this->agingStatusQuery($tenantId)->orderBy('sort_order')->orderBy('name')->get();

        return view('courier.packages.aging', compact('packages', 'customers', 'statuses', 'minimumAge'));
    }

    public function sendAgingReminders(Request $request, CustomerNotificationService $notifications)
    {
        $data = $request->validate([
            'package_ids' => 'required|array|min:1',
            'package_ids.*' => 'integer',
            'min_age' => 'nullable|integer|min:0',
            'send_key' => 'nullable|string|max:80',
        ]);
        $tenantId = auth()->user()->tenant_id;
        $minimumAge = max((int) ($data['min_age'] ?? TenantSetting::getValue($tenantId, 'storage_free_days', 7)), 0);
        $sendKey = (string) ($data['send_key'] ?? '');

        if ($sendKey !== '' && session()->has('aging_reminder_send_keys.'.$sendKey)) {
            return redirect()->route('courier.packages.aging', ['min_age' => $minimumAge])
                ->with('success', 'This aging reminder request was already processed.');
        }

        if ($sendKey !== '') {
            $processedKeys = session('aging_reminder_send_keys', []);
            $processedKeys[$sendKey] = now()->toDateTimeString();
            session(['aging_reminder_send_keys' => array_slice($processedKeys, -20, null, true)]);
        }

        $packages = $this->tenantPackages()
            ->where(fn ($query) => $this->applyAgingEligibility($query, $minimumAge))
            ->whereIn('id', collect($data['package_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->with(['tenant', 'customer.user', 'status', 'invoiceItems.invoice'])
            ->get()
            ->filter(fn ($package) => optional(optional($package->customer)->user)->email);

        if ($packages->isEmpty()) {
            return back()->withErrors(['package_ids' => 'Select at least one eligible aging package with a customer email.']);
        }

        $sent = 0;
        $failed = 0;

        foreach ($packages->groupBy('customer_id') as $customerPackages) {
            $notifications->agingPackagesReminder($customerPackages->values(), $minimumAge) ? $sent++ : $failed++;
        }

        ActivityLog::record('aging_reminders_sent', 'packages', 'Sent aging package reminders', [
            'package_ids' => $packages->pluck('id')->values()->all(),
            'customers_notified' => $sent,
            'failed_customers' => $failed,
        ]);

        $message = 'Aging package reminder sent to '.$sent.' customer'.($sent === 1 ? '' : 's').'.';
        if ($failed > 0) {
            $message .= ' '.$failed.' customer'.($failed === 1 ? '' : 's').' could not be emailed.';
        }

        return back()->with('success', $message);
    }

    public function reassign(Request $request, $id)
    {
        $tenantId = auth()->user()->tenant_id;
        $package = $this->tenantPackages()->findOrFail($id);
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
        ]);
        $customer = $this->tenantCustomers()->findOrFail($data['customer_id']);
        $oldMailbox = $package->mailbox;

        $package->update([
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'mailbox' => $customer->mailbox,
        ]);

        PackageLog::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status_id' => $package->status_id,
            'note' => 'Package reassigned from '.($oldMailbox ?: 'unassigned').' to '.$customer->mailbox,
            'location' => optional(auth()->user()->outlet)->name,
            'added_by' => auth()->id(),
            'created_at' => now(),
        ]);

        ActivityLog::record('reassigned', 'packages', 'Reassigned package '.$package->tracking_number, [
            'package_id' => $package->id,
            'customer_id' => $customer->id,
        ]);

        return back()->with('success', 'Package reassigned successfully.');
    }

    public function downloadReceipt($id)
    {
        $package = $this->tenantPackages()->findOrFail($id);
        abort_if(! $package->invoice_file || ! Storage::disk('public')->exists($package->invoice_file), 404);

        return response()->file(Storage::disk('public')->path($package->invoice_file), [
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($package->invoice_file)).'"',
        ]);
    }

    public function purchaseReceipt($id, PackagePurchaseReceipt $receipt)
    {
        $package = $this->tenantPackages()->findOrFail($id);
        abort_if((int) $receipt->package_id !== (int) $package->id, 404);
        abort_if(! $receipt->path || ! Storage::disk('public')->exists($receipt->path), 404);

        return response()->file(Storage::disk('public')->path($receipt->path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($receipt->original_name ?: basename($receipt->path)).'"',
        ]);
    }

    public function label($id)
    {
        $package = $this->tenantPackages()
            ->with(['tenant', 'customer.user', 'status', 'packageType', 'outlet'])
            ->findOrFail($id);

        return view('courier.packages.label', compact('package'));
    }

    private function tenantPackages()
    {
        $query = Package::where('tenant_id', auth()->user()->tenant_id)
            ->whereNotNull('date_received');

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function tenantCustomers()
    {
        $query = Customer::where('tenant_id', auth()->user()->tenant_id);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function preAlertFromRequest(Request $request): ?PreAlert
    {
        if (! $request->filled('pre_alert_id')) {
            return null;
        }

        return PreAlert::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'submitted')
            ->with(['customer.user', 'receipts'])
            ->find($request->integer('pre_alert_id'));
    }

    private function logisticsPackageFromRequest(Request $request): ?SuperadminLogisticsPackage
    {
        if (! $request->filled('logistics_package_id')) {
            return null;
        }

        return SuperadminLogisticsPackage::where('tenant_id', auth()->user()->tenant_id)
            ->whereNull('courier_package_id')
            ->with(['manifest', 'invoiceItems.invoice'])
            ->find($request->integer('logistics_package_id'));
    }

    private function logisticsPackageForStore(int $tenantId, $logisticsPackageId): ?SuperadminLogisticsPackage
    {
        if (! $logisticsPackageId) {
            return null;
        }

        $package = SuperadminLogisticsPackage::where('tenant_id', $tenantId)
            ->whereNull('courier_package_id')
            ->findOrFail((int) $logisticsPackageId);

        abort_if(! $package->checked_in_at, 422, 'This warehouse package must be checked in by System Admin before it can be converted.');

        return $package;
    }

    private function mergeLogisticsPackageData(array $data, SuperadminLogisticsPackage $logisticsPackage): array
    {
        $data['supplier_tracking'] = $data['supplier_tracking'] ?: $logisticsPackage->tracking_number;
        $data['description'] = $data['description'] ?: $logisticsPackage->description;
        $data['weight'] = ((float) ($data['weight'] ?? 0) > 0) ? $data['weight'] : $logisticsPackage->weight;
        $data['freight_type'] = $data['freight_type'] ?? $this->tenantDefaultFreightType((int) $logisticsPackage->tenant_id);
        $data['date_received'] = $data['date_received'] ?? optional($logisticsPackage->checked_in_at)->toDateString() ?? now()->toDateString();
        $data['courier'] = $data['courier'] ?: optional($logisticsPackage->manifest)->cargo_company;
        $data['merchant'] = $data['merchant'] ?: 'Warehouse';

        return $data;
    }

    private function matchingPreAlert(int $tenantId, ?string $supplierTracking, ?int $customerId): ?PreAlert
    {
        $supplierTracking = trim((string) $supplierTracking);
        if ($supplierTracking === '') {
            return null;
        }

        return PreAlert::where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->where('supplier_tracking', $supplierTracking)
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->with(['customer', 'receipts'])
            ->first();
    }

    private function mergePreAlertData(array $data, PreAlert $preAlert): array
    {
        $data['customer_id'] = $data['customer_id'] ?? $preAlert->customer_id;
        $data['supplier_tracking'] = $data['supplier_tracking'] ?: $preAlert->supplier_tracking;
        $data['courier'] = $data['courier'] ?: $preAlert->courier;
        $data['merchant'] = $data['merchant'] ?: $preAlert->merchant;
        $data['description'] = $data['description'] ?: $preAlert->description;
        $data['price_usd'] = ((float) ($data['price_usd'] ?? 0) > 0) ? $data['price_usd'] : $preAlert->price_usd;
        $data['delivery_type'] = $data['delivery_type'] ?? $preAlert->delivery_type;

        return $data;
    }

    private function filteredTenantPackages(Request $request)
    {
        return $this->tenantPackages()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('tracking_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhere('courier', 'like', $term)
                        ->orWhereHas('customer.user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status_id'), fn ($query) => $query->where('status_id', $request->integer('status_id')))
            ->when($request->filled('freight_type'), fn ($query) => $query->where('freight_type', $request->freight_type))
            ->when($request->filled('delivery_type'), fn ($query) => $query->where('delivery_type', $request->delivery_type))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date_received', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date_received', '<=', $request->date_to))
            ->when($request->boolean('aging'), fn ($query) => $this->applyAgingEligibility($query, max((int) $request->input('min_age', 7), 0)))
            ->when($request->boolean('at_warehouse'), fn ($query) => $this->applyWarehouseEligibility($query))
            ->when($request->boolean('in_transit'), fn ($query) => $this->applyTransitEligibility($query))
            ->when($request->boolean('undelivered'), fn ($query) => $this->applyUndeliveredEligibility($query))
            ->when($request->filled('min_age') && ! $request->boolean('aging'), function ($query) use ($request) {
                $query->whereNotNull('date_received')
                    ->whereDate('date_received', '<=', now()->subDays((int) $request->min_age)->toDateString());
            });
    }

    private function applyWarehouseEligibility($query)
    {
        return $query
            ->whereNull('date_delivered')
            ->whereDoesntHave('status', function ($status) {
                $status->where('slug', 'delivered')
                    ->orWhere('name', 'like', '%transit%')
                    ->orWhere('name', 'like', '%out for delivery%');
            });
    }

    private function applyTransitEligibility($query)
    {
        return $query
            ->whereNull('date_delivered')
            ->whereHas('status', fn ($status) => $status->where('name', 'like', '%transit%'));
    }

    private function applyUndeliveredEligibility($query)
    {
        return $query
            ->whereNull('date_delivered')
            ->whereDoesntHave('status', fn ($status) => $status->where('slug', 'delivered'));
    }

    private function applyAgingEligibility($query, int $minimumAge = 7)
    {
        return $query->currentlyAging($minimumAge);
    }

    private function agingStatusQuery(int $tenantId)
    {
        return PackageStatus::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereIn('slug', [
                        'ready-for-pickup',
                        'available-for-pickup',
                        'ready-for-delivery',
                        'available-for-delivery',
                        'out-for-delivery',
                    ])
                    ->orWhere(function ($nested) {
                        $nested->where(function ($availability) {
                            $availability->where('name', 'like', '%Ready%')
                                ->orWhere('name', 'like', '%Available%')
                                ->orWhere('name', 'like', '%Out%');
                        })->where(function ($type) {
                            $type->where('name', 'like', '%Pickup%')
                                ->orWhere('name', 'like', '%Delivery%');
                        });
                    });
            });
    }

    private function streamPackagesCsv($packages, string $filename): StreamedResponse
    {
        $columns = ['tracking_number', 'customer', 'mailbox', 'supplier_tracking', 'courier', 'merchant', 'description', 'status', 'freight_type', 'delivery_type', 'weight', 'price_usd', 'price_jmd', 'storage_fee', 'processing_fee_jmd', 'total_jmd', 'date_received', 'date_delivered', 'age_days'];

        return response()->streamDownload(function () use ($packages, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($packages as $package) {
                $invoiceItem = $package->activeInvoiceItem();
                fputcsv($handle, [
                    $package->tracking_number,
                    optional(optional($package->customer)->user)->full_name,
                    $package->mailbox,
                    $package->supplier_tracking,
                    $package->courier,
                    $package->merchant,
                    $package->description,
                    optional($package->status)->name,
                    $package->freight_type,
                    $package->delivery_type,
                    $package->weight,
                    $package->price_usd,
                    $package->price_jmd,
                    $package->storage_fee,
                    $package->processing_fee_jmd,
                    $invoiceItem ? (float) $invoiceItem->total_jmd : (float) $package->price_jmd + (float) $package->storage_fee + (float) $package->processing_fee_jmd,
                    optional($package->date_received)->format('Y-m-d'),
                    optional($package->date_delivered)->format('Y-m-d'),
                    $package->date_received ? $package->date_received->diffInDays(now()) : '',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function createPackageFromImport(array $data, SubscriptionLimitService $limits): bool
    {
        $tenantId = auth()->user()->tenant_id;
        $tracking = trim((string) ($data['tracking_number'] ?? ''));
        $tracking = $tracking === '' ? $this->nextHawbNumber($tenantId) : strtoupper($tracking);
        if (Package::where('tenant_id', $tenantId)->where('tracking_number', $tracking)->exists()) {
            return false;
        }

        $customer = $this->customerFromImport($data);
        $preAlert = $this->matchingPreAlert($tenantId, $data['supplier_tracking'] ?? null, optional($customer)->id);
        if ($preAlert) {
            $data = $this->mergePreAlertData($data, $preAlert);
            $customer = $customer ?: $preAlert->customer;
        }
        $data['freight_type'] = $this->allowedFreightType($tenantId, $data['freight_type'] ?? $this->tenantDefaultFreightType($tenantId));
        $data = $this->normalizeRegionalPackageData($tenantId, $data);
        $limits->ensureCanCreate($tenantId, 'packages_month');

        $package = Package::create([
            'tenant_id' => $tenantId,
            'customer_id' => optional($customer)->id,
            'outlet_id' => optional($customer)->outlet_id,
            'tracking_number' => $tracking,
            'supplier_tracking' => $data['supplier_tracking'] ?? null,
            'mailbox' => optional($customer)->mailbox ?: Customer::normalizeMailbox($data['mailbox'] ?? null),
            'courier' => $data['courier'] ?? null,
            'merchant' => $data['merchant'] ?? null,
            'description' => $data['description'] ?? null,
            'weight' => (float) ($data['weight'] ?? 0),
            'price_usd' => (float) ($data['price_usd'] ?? 0),
            'price_jmd' => (float) ($data['price_jmd'] ?? 0),
            'freight_type' => $data['freight_type'],
            'region' => $data['region'] ?? 'USA',
            'volume_cuft' => $data['volume_cuft'] ?? null,
            'volume_cbm' => $data['volume_cbm'] ?? null,
            'label_type' => $data['label_type'] ?? null,
            'delivery_type' => $this->allowed($data['delivery_type'] ?? 'pickup', ['pickup', 'delivery'], 'pickup'),
            'status_id' => $this->statusFromImport($data),
            'storage_days' => (int) ($data['storage_days'] ?? 0),
            'storage_fee' => (float) ($data['storage_fee'] ?? 0),
            'processing_fee_jmd' => (float) ($data['processing_fee_jmd'] ?? $this->processingFee($tenantId)),
            'unknown_fee_jmd' => (float) ($data['unknown_fee_jmd'] ?? 0),
            'unknown_fee_reason' => $data['unknown_fee_reason'] ?? null,
            'date_received' => ! empty($data['date_received']) ? $data['date_received'] : now()->toDateString(),
            'invoice_file' => $preAlert && $preAlert->receipts->isNotEmpty() ? $preAlert->receipts->first()->path : null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($preAlert) {
            $preAlert->update([
                'status' => 'converted',
                'package_id' => $package->id,
            ]);
        }

        PackageLog::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status_id' => $package->status_id,
            'note' => 'Bulk uploaded package',
            'location' => optional(auth()->user()->outlet)->name,
            'added_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return true;
    }

    private function customerFromImport(array $data): ?Customer
    {
        $query = $this->tenantCustomers();
        if (! empty($data['customer_id'])) {
            return (clone $query)->whereKey($data['customer_id'])->first();
        }
        if (! empty($data['mailbox'])) {
            return (clone $query)->whereNormalizedMailbox($data['mailbox'])->first();
        }
        if (! empty($data['customer_email'])) {
            return (clone $query)->whereHas('user', fn ($userQuery) => $userQuery->where('email', $data['customer_email']))->first();
        }

        return null;
    }

    private function statusFromImport(array $data): ?int
    {
        $query = PackageStatus::where('tenant_id', auth()->user()->tenant_id);
        if (! empty($data['status_id'])) {
            return (clone $query)->whereKey($data['status_id'])->value('id');
        }
        if (! empty($data['status'])) {
            return (clone $query)
                ->where(fn ($statusQuery) => $statusQuery->where('slug', Str::slug($data['status']))->orWhere('name', $data['status']))
                ->value('id');
        }

        return (clone $query)->orderByDesc('is_default')->orderBy('sort_order')->value('id');
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function storeReceipt(Request $request): ?string
    {
        if (! $request->hasFile('purchase_receipt')) {
            return null;
        }

        return $request->file('purchase_receipt')->store('package-receipts', 'public');
    }

    private function shippingModes(int $tenantId)
    {
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)->orderBy('category')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name')->get();
    }

    private function shippingModeCategory(int $tenantId, string $freightType): string
    {
        return ShippingMode::where('tenant_id', $tenantId)->where('code', $freightType)->value('category')
            ?: ($freightType === 'sea' ? 'sea' : ($freightType === 'local' ? 'local' : 'air'));
    }

    private function allowedFreightType(int $tenantId, string $value): string
    {
        $value = ShippingMode::normalizeCode($value);
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)->where('code', $value)->exists() ? $value : $this->tenantDefaultFreightType($tenantId);
    }

    private function tenantDefaultFreightType(int $tenantId): string
    {
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('code') ?: 'air';
    }

    private function normalizeRegionalPackageData(int $tenantId, array $data): array
    {
        $freightType = $data['freight_type'] ?? $this->tenantDefaultFreightType($tenantId);
        $freightCategory = $this->shippingModeCategory($tenantId, $freightType);
        $data['region'] = $this->normalizePackageRegion($data['region'] ?? 'USA');

        if ($freightCategory === 'sea') {
            $cuft = max(0, (float) ($data['volume_cuft'] ?? 0));
            $cbm = max(0, (float) ($data['volume_cbm'] ?? 0));
            $legacyMeasure = max(0, (float) ($data['weight'] ?? 0));

            if ($cuft <= 0 && $cbm > 0) {
                $cuft = $cbm * WeightRate::CBM_TO_CUFT;
            } elseif ($cbm <= 0 && $cuft > 0) {
                $cbm = $cuft / WeightRate::CBM_TO_CUFT;
            } elseif ($cuft <= 0 && $legacyMeasure > 0) {
                $cuft = $legacyMeasure;
                $cbm = $legacyMeasure / WeightRate::CBM_TO_CUFT;
            }

            $data['volume_cuft'] = $cuft > 0 ? round($cuft, 4) : null;
            $data['volume_cbm'] = $cbm > 0 ? round($cbm, 4) : null;
            $data['weight'] = $cuft > 0 ? round($cuft, 2) : $legacyMeasure;
        } else {
            $data['volume_cuft'] = null;
            $data['volume_cbm'] = null;
        }

        $unknownFee = max(0, (float) ($data['unknown_fee_jmd'] ?? 0));
        $data['unknown_fee_jmd'] = round($unknownFee, 2);
        if ($unknownFee <= 0) {
            $data['unknown_fee_reason'] = null;
        }
        $data['label_type'] = $this->regionalLabelType($data['region'], $freightCategory);

        return $data;
    }

    private function normalizePackageRegion(?string $region): string
    {
        $value = trim((string) $region);
        if ($value === '') {
            return 'USA';
        }

        $key = strtolower($value);
        return in_array($key, ['cn', 'china'], true) ? 'China' : (in_array($key, ['us', 'usa'], true) ? 'USA' : $value);
    }

    private function regionalLabelType(?string $region, string $freightCategory): string
    {
        $normalized = $this->normalizePackageRegion($region);
        $code = strtolower($normalized) === 'china' ? 'CN' : (strtolower($normalized) === 'usa' ? 'US' : strtoupper($normalized));
        $method = $freightCategory === 'sea' ? 'Sea' : 'Air';

        return trim($code.' '.$method);
    }

    private function applyRegionRateScope($query, ?string $region)
    {
        $normalized = strtolower($this->normalizePackageRegion($region));

        return $query
            ->where(function ($regionQuery) use ($normalized) {
                $regionQuery->whereNull('region')
                    ->orWhere('region', '')
                    ->orWhereRaw('LOWER(region) = ?', [$normalized]);
            })
            ->orderByRaw("CASE WHEN LOWER(COALESCE(region, '')) = ? THEN 0 WHEN region IS NULL OR region = '' THEN 1 ELSE 2 END", [$normalized]);
    }
    private function packageRate(int $tenantId, array $data): ?WeightRate
    {
        $actualWeight = (float) ($data['weight'] ?? 0);
        $freightType = $data['freight_type'] ?? $this->tenantDefaultFreightType($tenantId);
        $freightCategory = $this->shippingModeCategory($tenantId, $freightType);
        if ($actualWeight <= 0) {
            return null;
        }
        $weight = $this->billableWeight($actualWeight, $this->billingWeightMode($tenantId));

        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $outletId = optional($customer)->outlet_id;
        $useBranchRates = false;
        if ($outletId && in_array($freightCategory, ['air', 'sea'], true)) {
            $useBranchRates = \App\Models\Outlet::where('tenant_id', $tenantId)
                ->whereKey($outletId)
                ->where('rate_group_mode', 'custom')
                ->exists();
        }

        $base = WeightRate::where('tenant_id', $tenantId)
            ->where('freight_type', $freightType)
            ->where('is_active', true)
            ->where(function ($query) use ($outletId, $useBranchRates) {
                if ($useBranchRates) {
                    $query->where('outlet_id', $outletId)->orWhereNull('outlet_id');
                } else {
                    $query->whereNull('outlet_id');
                }
            })
            ->where(function ($query) use ($customer) {
                if ($customer && $customer->pricing_tier_id) {
                    $query->where('pricing_tier_id', $customer->pricing_tier_id)->orWhereNull('pricing_tier_id');
                } else {
                    $query->whereNull('pricing_tier_id');
                }
            })
            ->orderByRaw('outlet_id is null')
            ->orderByRaw('pricing_tier_id is null');
        $base = $this->applyRegionRateScope($base, $data['region'] ?? 'USA');

        $rate = (clone $base)
            ->where($this->rateRangeClause($freightCategory, $weight))
            ->orderBy('min_weight')
            ->first();

        return $rate ?: (clone $base)
            ->where($this->finalContinuousRateClause($freightCategory, $weight))
            ->orderByDesc('min_weight')
            ->first();
    }
    private function packagePrice(int $tenantId, array $data): float
    {
        if (! empty($data['price_jmd']) && (float) $data['price_jmd'] > 0) {
            return (float) $data['price_jmd'];
        }

        $actualWeight = (float) ($data['weight'] ?? 0);
        $freightType = $data['freight_type'] ?? $this->tenantDefaultFreightType($tenantId);
        $freightCategory = $this->shippingModeCategory($tenantId, $freightType);
        if ($actualWeight <= 0) {
            return 0;
        }
        $weight = $this->billableWeight($actualWeight, $this->billingWeightMode($tenantId));

        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $outletId = optional($customer)->outlet_id;
        $useBranchRates = false;
        if ($outletId && in_array($freightCategory, ['air', 'sea'], true)) {
            $useBranchRates = \App\Models\Outlet::where('tenant_id', $tenantId)
                ->whereKey($outletId)
                ->where('rate_group_mode', 'custom')
                ->exists();
        }

        $rateQuery = WeightRate::where('tenant_id', $tenantId)
            ->where('freight_type', $freightType)
            ->where('is_active', true)
            ->where($this->rateRangeClause($freightCategory, $weight))
            ->where(function ($query) use ($outletId, $useBranchRates) {
                if ($useBranchRates) {
                    $query->where('outlet_id', $outletId)->orWhereNull('outlet_id');
                } else {
                    $query->whereNull('outlet_id');
                }
            })
            ->where(function ($query) use ($customer) {
                if ($customer && $customer->pricing_tier_id) {
                    $query->where('pricing_tier_id', $customer->pricing_tier_id)->orWhereNull('pricing_tier_id');
                } else {
                    $query->whereNull('pricing_tier_id');
                }
            });

        $rate = $this->applyRegionRateScope($rateQuery, $data['region'] ?? 'USA')
            ->orderByRaw('outlet_id is null')
            ->orderByRaw('pricing_tier_id is null')
            ->orderBy('min_weight')
            ->first();

        if (! $rate) {
            $fallbackQuery = WeightRate::where('tenant_id', $tenantId)
                ->where('freight_type', $freightType)
                ->where('is_active', true)
                ->where($this->finalContinuousRateClause($freightCategory, $weight))
                ->where(function ($query) use ($outletId, $useBranchRates) {
                    if ($useBranchRates) {
                        $query->where('outlet_id', $outletId)->orWhereNull('outlet_id');
                    } else {
                        $query->whereNull('outlet_id');
                    }
                })
                ->where(function ($query) use ($customer) {
                    if ($customer && $customer->pricing_tier_id) {
                        $query->where('pricing_tier_id', $customer->pricing_tier_id)->orWhereNull('pricing_tier_id');
                    } else {
                        $query->whereNull('pricing_tier_id');
                    }
                });

            $rate = $this->applyRegionRateScope($fallbackQuery, $data['region'] ?? 'USA')
                ->orderByRaw('outlet_id is null')
                ->orderByRaw('pricing_tier_id is null')
                ->orderByDesc('min_weight')
                ->first();
        }

        if (! $rate) {
            return 0;
        }

        return $rate->priceFor($weight, $data['delivery_type'] ?? 'pickup');
    }

    private function billingWeightMode(int $tenantId): string
    {
        $mode = TenantSetting::getValue($tenantId, 'billing_weight_mode', 'actual');

        return in_array($mode, ['actual', 'round_up', 'round_down'], true) ? $mode : 'actual';
    }

    private function billableWeight(float $weight, string $mode): float
    {
        if ($weight <= 0) {
            return 0;
        }

        return match ($mode) {
            'round_up' => ceil($weight),
            'round_down' => max(floor($weight), 1),
            default => $weight,
        };
    }

    private function storageFee(int $tenantId, ?int $storageDays): float
    {
        $days = (int) ($storageDays ?? 0);
        $freeDays = (int) TenantSetting::getValue($tenantId, 'storage_free_days', 0);
        if ($days <= $freeDays) {
            return 0;
        }

        $flat = (float) TenantSetting::getValue($tenantId, 'storage_flat_fee_jmd', 0);
        $daily = (float) TenantSetting::getValue($tenantId, 'storage_daily_increment_jmd', 0);

        return round($flat + max($days - $freeDays, 0) * $daily, 2);
    }

    private function rateRangeClause(string $freightType, float $measure): \Closure
    {
        return function ($query) use ($freightType, $measure) {
            if ($freightType !== 'sea') {
                $query->where('min_weight', '<=', $measure)
                    ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $measure));
                return;
            }

            $cbm = $measure / WeightRate::CBM_TO_CUFT;
            $query->where(function ($unitQuery) use ($measure, $cbm) {
                $unitQuery->where(function ($cuftQuery) use ($measure) {
                    $cuftQuery->where(fn ($unit) => $unit->whereNull('volume_unit')->orWhere('volume_unit', 'cuft'))
                        ->where('min_weight', '<=', $measure)
                        ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $measure));
                })->orWhere(function ($cbmQuery) use ($cbm) {
                    $cbmQuery->where('volume_unit', 'cbm')
                        ->where('min_weight', '<=', $cbm)
                        ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $cbm));
                });
            });
        };
    }

    private function finalContinuousRateClause(string $freightType, float $measure): \Closure
    {
        return function ($query) use ($freightType, $measure) {
            if ($freightType !== 'sea') {
                $query->where('min_weight', '<=', $measure);
                return;
            }

            $cbm = $measure / WeightRate::CBM_TO_CUFT;
            $query->where(function ($unitQuery) use ($measure, $cbm) {
                $unitQuery->where(function ($cuftQuery) use ($measure) {
                    $cuftQuery->where(fn ($unit) => $unit->whereNull('volume_unit')->orWhere('volume_unit', 'cuft'))
                        ->where('min_weight', '<=', $measure);
                })->orWhere(function ($cbmQuery) use ($cbm) {
                    $cbmQuery->where('volume_unit', 'cbm')
                        ->where('min_weight', '<=', $cbm);
                });
            });
        };
    }
    private function storageDaysFromDate($date): int
    {
        if (! $date) {
            return 0;
        }

        try {
            return max(0, (int) \Carbon\Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay()));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function processingFee(int $tenantId, array $data = []): float
    {
        if ($data) {
            $rate = $this->packageRate($tenantId, $data);
            if ($rate && (float) $rate->processing_fee_jmd > 0) {
                return (float) $rate->processing_fee_jmd;
            }
        }

        return (float) TenantSetting::getValue($tenantId, 'processing_fee_jmd', 0);
    }

    private function tenantStatusId(int $tenantId, ?int $statusId): ?int
    {
        if ($statusId) {
            return PackageStatus::where('tenant_id', $tenantId)->whereKey($statusId)->value('id')
                ?: abort(422, 'Selected package status is not available for this tenant.');
        }

        return PackageStatus::where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->value('id');
    }

    private function tenantPackageTypeId(int $tenantId, ?int $packageTypeId): ?int
    {
        if (! $packageTypeId) {
            return null;
        }

        return PackageType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereKey($packageTypeId)
            ->value('id')
            ?: abort(422, 'Selected package type is not available.');
    }

    private function normalizeTrackingNumber(?string $trackingNumber, int $tenantId): string
    {
        $trackingNumber = trim((string) $trackingNumber);

        return $trackingNumber !== ''
            ? strtoupper($trackingNumber)
            : $this->nextHawbNumber($tenantId);
    }

    private function nextHawbNumber(int $tenantId): string
    {
        $prefix = $this->hawbPrefix($tenantId);
        $sequence = Package::where('tenant_id', $tenantId)
            ->where('tracking_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $hawb = $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Package::where('tenant_id', $tenantId)->where('tracking_number', $hawb)->exists());

        return $hawb;
    }

    private function hawbPrefix(int $tenantId): string
    {
        $configured = TenantSetting::getValue($tenantId, 'hawb_prefix')
            ?: TenantSetting::getValue($tenantId, 'courier_code')
            ?: TenantSetting::getValue($tenantId, 'mailbox_prefix');
        $prefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configured)));

        if ($prefix !== '') {
            return $prefix;
        }

        $companyName = optional(Tenant::find($tenantId))->company_name ?: optional(auth()->user()->tenant)->company_name ?: 'COU';
        $fallback = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $companyName), 0, 4));

        return $fallback !== '' ? $fallback : 'COU';
    }
}
















