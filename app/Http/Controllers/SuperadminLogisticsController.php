<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CustomsOffice;
use App\Models\Customer;
use App\Models\LoadingPort;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\SuperadminLogisticsInvoice;
use App\Models\SuperadminLogisticsInvoiceItem;
use App\Models\SuperadminLogisticsInvoicePayment;
use App\Models\SuperadminLogisticsManifest;
use App\Models\SuperadminLogisticsPackage;
use App\Models\SuperadminLogisticsRate;
use App\Models\SuperadminLogisticsShipment;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantCreditTransaction;
use App\Models\TenantSetting;
use App\Mail\TenantTemplateMail;
use App\Services\FinanceEntryService;
use App\Services\SubscriptionLimitService;
use App\Services\TenantMailService;
use App\Support\LogisticsInvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperadminLogisticsController extends Controller
{
    public function index(Request $request)
    {
        return $this->logisticsPage($request, 'overview');
    }

    public function manifests(Request $request)
    {
        return $this->logisticsPage($request, 'manifests');
    }

    public function bulkUploadPage(Request $request)
    {
        return $this->logisticsPage($request, 'bulk');
    }

    public function packagesPage(Request $request)
    {
        return $this->logisticsPage($request, 'packages');
    }

    public function ratesPage(Request $request)
    {
        return $this->logisticsPage($request, 'rates');
    }

    public function invoicesPage(Request $request)
    {
        return redirect()->route('superadmin.finance.bills', [
            'billing_type' => 'shipment',
            'status' => $request->input('invoice_status') ?: $request->input('status'),
        ]);
    }

    public function bulkCheckInPage(Request $request)
    {
        $shipmentQuery = SuperadminLogisticsShipment::with(['creator'])
            ->withCount(['packages', 'manifests'])
            ->withSum('packages as packages_weight', 'weight')
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $shipmentQuery->where(function ($query) use ($search) {
                $query->where('shipment_number', 'like', '%'.$search.'%')
                    ->orWhere('master_number', 'like', '%'.$search.'%')
                    ->orWhere('cargo_company', 'like', '%'.$search.'%')
                    ->orWhere('shipping_agent_code', 'like', '%'.$search.'%');
            });
        }

        if (in_array($request->input('shipment_type'), ['air', 'sea'], true)) {
            $shipmentQuery->where('shipment_type', $request->input('shipment_type'));
        }

        if (filled($request->input('status'))) {
            $shipmentQuery->where('status', $request->input('status'));
        }

        if (filled($request->input('arrival_from'))) {
            $shipmentQuery->whereDate('arrival_date', '>=', $request->date('arrival_from'));
        }

        if (filled($request->input('arrival_to'))) {
            $shipmentQuery->whereDate('arrival_date', '<=', $request->date('arrival_to'));
        }

        $shipments = $shipmentQuery->limit(24)->get();

        $shipmentRelations = [
            'packages' => function ($query) {
                $query->latest();
            },
            'packages.tenant',
            'packages.manifest',
            'packages.courierPackage.customer.user',
            'manifests.tenant',
            'manifests.invoices',
        ];

        $selectedShipment = null;
        if ($request->filled('shipment_id')) {
            $selectedShipment = SuperadminLogisticsShipment::with($shipmentRelations)
                ->withCount(['packages', 'manifests'])
                ->find($request->integer('shipment_id'));
        }

        if (! $selectedShipment && $shipments->isNotEmpty()) {
            $selectedShipment = SuperadminLogisticsShipment::with($shipmentRelations)
                ->withCount(['packages', 'manifests'])
                ->find($shipments->first()->id);
        }

        if ($selectedShipment && $selectedShipment->shipment_type === 'sea') {
            $this->refreshShipmentOpenPackageCharges($selectedShipment);
            $selectedShipment->load($shipmentRelations)->loadCount(['packages', 'manifests']);
        }

        $scanEvents = collect();
        if ($selectedShipment && Schema::hasTable('superadmin_logistics_shipment_scans')) {
            $scanEvents = DB::table('superadmin_logistics_shipment_scans')
                ->leftJoin('tenants', 'superadmin_logistics_shipment_scans.tenant_id', '=', 'tenants.id')
                ->where('superadmin_logistics_shipment_scans.shipment_id', $selectedShipment->id)
                ->select('superadmin_logistics_shipment_scans.*', 'tenants.company_name as courier_name')
                ->latest('superadmin_logistics_shipment_scans.created_at')
                ->limit(200)
                ->get();
        }

        $inventoryPackages = SuperadminLogisticsPackage::with(['shipment', 'tenant', 'manifest', 'courierPackage.customer.user'])
            ->whereNull('checked_out_at')
            ->latest()
            ->limit(200)
            ->get();

        return view('superadmin.logistics.bulk-check-in', [
            'summary' => session('bulk_check_in_summary') ?: session('shipment_scan_summary'),
            'results' => session('bulk_check_in_results', session('shipment_scan_results', [])),
            'shipments' => $shipments,
            'selectedShipment' => $selectedShipment,
            'scanEvents' => $scanEvents,
            'unknownScanEvents' => $scanEvents->reject(fn ($event) => in_array($event->result, ['Successful', 'Already received'], true)),
            'inventoryPackages' => $inventoryPackages,
            'filters' => $request->only(['search', 'shipment_type', 'status', 'arrival_from', 'arrival_to']),
            'customsOffices' => CustomsOffice::whereNull('tenant_id')->orderBy('name')->get(),
            'loadingPorts' => LoadingPort::whereNull('tenant_id')->orderBy('name')->get(),
        ]);
    }

    public function storeShipment(Request $request)
    {
        $data = $request->validate([
            'shipment_type' => 'required|in:air,sea',
            'region' => 'nullable|string|max:40',
            'master_number' => 'nullable|string|max:150|unique:superadmin_logistics_shipments,master_number',
            'auto_generate_master' => 'nullable|boolean',
            'cargo_company' => 'required|string|max:150',
            'pieces' => 'nullable|integer|min:0',
            'departure_date' => 'required|date',
            'arrival_date' => 'nullable|date|after_or_equal:departure_date',
            'customs_office' => 'nullable|string|max:150',
            'loading_port' => 'nullable|string|max:150',
            'place_of_unloading' => 'nullable|string|max:150',
            'shipping_agent_code' => 'nullable|string|max:100',
            'asycuda_reference_number' => 'nullable|string|max:150',
            'voyage_container_numbers' => 'nullable|string|max:2000',
            'exporter_name' => 'nullable|string|max:150',
            'exporter_address' => 'nullable|string|max:2000',
            'awb_cost' => 'nullable|numeric|min:0',
            'delivery_cost' => 'nullable|numeric|min:0',
            'additional_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'documents' => 'nullable|array|max:10',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt|max:10240',
        ]);

        $type = $data['shipment_type'];
        $masterNumber = trim((string) ($data['master_number'] ?? ''));
        if ($masterNumber === '' || $request->boolean('auto_generate_master')) {
            $masterNumber = $this->nextShipmentMasterNumber($type, $data['cargo_company'] ?? null);
        }

        $documents = [];
        foreach ($request->file('documents', []) as $file) {
            $documents[] = [
                'path' => $file->store('logistics-shipments', 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $shipment = SuperadminLogisticsShipment::create([
            'shipment_number' => $this->nextShipmentNumber(),
            'shipment_type' => $type,
            'region' => $this->normalizeRegion($data['region'] ?? 'USA'),
            'master_number' => $masterNumber,
            'cargo_company' => $data['cargo_company'],
            'pieces' => $data['pieces'] ?? 0,
            'departure_date' => $data['departure_date'],
            'arrival_date' => $data['arrival_date'] ?? null,
            'customs_office' => $data['customs_office'] ?? null,
            'loading_port' => $data['loading_port'] ?? null,
            'place_of_unloading' => $data['place_of_unloading'] ?? null,
            'shipping_agent_code' => $data['shipping_agent_code'] ?? null,
            'asycuda_reference_number' => $data['asycuda_reference_number'] ?? null,
            'voyage_container_numbers' => $type === 'sea' ? ($data['voyage_container_numbers'] ?? null) : null,
            'exporter_name' => $data['exporter_name'] ?? null,
            'exporter_address' => $data['exporter_address'] ?? null,
            'documents' => $documents,
            'awb_cost' => $data['awb_cost'] ?? 0,
            'delivery_cost' => $data['delivery_cost'] ?? 0,
            'additional_cost' => $data['additional_cost'] ?? 0,
            'status' => 'in_progress',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::record('shipment_intake_created', 'superadmin_logistics', 'Created shipment intake '.$shipment->shipment_number, [
            'shipment_id' => $shipment->id,
            'master_number' => $shipment->master_number,
            'shipment_type' => $shipment->shipment_type,
        ]);

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', 'Shipment intake '.$shipment->shipment_number.' created.');
    }

    public function updateShipment(Request $request, SuperadminLogisticsShipment $shipment)
    {
        $data = $request->validate([
            'shipment_type' => 'required|in:air,sea',
            'region' => 'nullable|string|max:40',
            'status' => 'required|in:in_progress,completed',
            'master_number' => 'nullable|string|max:150|unique:superadmin_logistics_shipments,master_number,'.$shipment->id,
            'auto_generate_master' => 'nullable|boolean',
            'cargo_company' => 'required|string|max:150',
            'pieces' => 'nullable|integer|min:0',
            'departure_date' => 'required|date',
            'arrival_date' => 'nullable|date|after_or_equal:departure_date',
            'customs_office' => 'nullable|string|max:150',
            'loading_port' => 'nullable|string|max:150',
            'place_of_unloading' => 'nullable|string|max:150',
            'shipping_agent_code' => 'nullable|string|max:100',
            'asycuda_reference_number' => 'nullable|string|max:150',
            'voyage_container_numbers' => 'nullable|string|max:2000',
            'exporter_name' => 'nullable|string|max:150',
            'exporter_address' => 'nullable|string|max:2000',
            'awb_cost' => 'nullable|numeric|min:0',
            'delivery_cost' => 'nullable|numeric|min:0',
            'additional_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'documents' => 'nullable|array|max:10',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt|max:10240',
        ]);

        $type = $data['shipment_type'];
        $masterNumber = trim((string) ($data['master_number'] ?? ''));
        if ($request->boolean('auto_generate_master') || $masterNumber === '') {
            $masterNumber = $request->boolean('auto_generate_master')
                ? $this->nextShipmentMasterNumber($type, $data['cargo_company'] ?? null)
                : ($shipment->master_number ?: $this->nextShipmentMasterNumber($type, $data['cargo_company'] ?? null));
        }

        $documents = is_array($shipment->documents) ? $shipment->documents : [];
        foreach ($request->file('documents', []) as $file) {
            $documents[] = [
                'path' => $file->store('logistics-shipments', 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $shipment->update([
            'shipment_type' => $type,
            'region' => $this->normalizeRegion($data['region'] ?? $shipment->region ?? 'USA'),
            'master_number' => $masterNumber,
            'cargo_company' => $data['cargo_company'],
            'pieces' => $data['pieces'] ?? 0,
            'departure_date' => $data['departure_date'],
            'arrival_date' => $data['arrival_date'] ?? null,
            'customs_office' => $data['customs_office'] ?? null,
            'loading_port' => $data['loading_port'] ?? null,
            'place_of_unloading' => $data['place_of_unloading'] ?? null,
            'shipping_agent_code' => $data['shipping_agent_code'] ?? null,
            'asycuda_reference_number' => $data['asycuda_reference_number'] ?? null,
            'voyage_container_numbers' => $type === 'sea' ? ($data['voyage_container_numbers'] ?? null) : null,
            'exporter_name' => $data['exporter_name'] ?? null,
            'exporter_address' => $data['exporter_address'] ?? null,
            'documents' => array_values($documents),
            'awb_cost' => $data['awb_cost'] ?? 0,
            'delivery_cost' => $data['delivery_cost'] ?? 0,
            'additional_cost' => $data['additional_cost'] ?? 0,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        ActivityLog::record('shipment_intake_updated', 'superadmin_logistics', 'Updated shipment intake '.$shipment->shipment_number, [
            'shipment_id' => $shipment->id,
            'master_number' => $shipment->master_number,
            'shipment_type' => $shipment->shipment_type,
        ]);

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', 'Shipment intake '.$shipment->shipment_number.' updated.');
    }

    public function destroyShipment(SuperadminLogisticsShipment $shipment)
    {
        if ($shipment->packages()->exists() || $shipment->manifests()->exists()) {
            throw ValidationException::withMessages([
                'shipment' => 'Only blank shipment intakes can be deleted.',
            ]);
        }

        $shipmentNumber = $shipment->shipment_number;
        foreach (($shipment->documents ?: []) as $document) {
            if (! empty($document['path'])) {
                Storage::disk('public')->delete($document['path']);
            }
        }

        $shipment->delete();

        ActivityLog::record('shipment_intake_deleted', 'superadmin_logistics', 'Deleted blank shipment intake '.$shipmentNumber);

        return redirect()
            ->route('superadmin.logistics.bulk-check-in')
            ->with('success', 'Blank shipment intake '.$shipmentNumber.' deleted.');
    }

    public function scanShipmentPackage(Request $request, SuperadminLogisticsShipment $shipment)
    {
        $this->ensureShipmentIntakeIsOpen($shipment);

        $data = $request->validate([
            'tracking_number' => 'required|string|max:150',
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:kg,lb,cuft,cbm',
        ]);

        $result = DB::transaction(function () use ($shipment, $data) {
            $scanResult = $this->intakePackageIntoShipment(
                $shipment,
                (string) $data['tracking_number'],
                array_key_exists('weight', $data) && $data['weight'] !== null ? (float) $data['weight'] : null,
                $data['weight_unit'] ?? null
            );
            $this->recordShipmentScan($shipment, $scanResult);

            return $scanResult;
        });

        $level = in_array($result['result'], ['Successful', 'Already received'], true) ? 'success' : 'warning';

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with($level, $result['message'])
            ->with('shipment_scan_results', [$result]);
    }

    public function bulkUploadShipmentPackages(Request $request, SuperadminLogisticsShipment $shipment)
    {
        $this->ensureShipmentIntakeIsOpen($shipment);

        $data = $request->validate([
            'package_rows' => 'nullable|string|max:30000',
            'package_file' => 'nullable|file|mimes:csv,txt|max:4096',
        ]);

        if (blank($data['package_rows'] ?? null) && ! $request->hasFile('package_file')) {
            throw ValidationException::withMessages([
                'package_rows' => 'Paste package rows or choose a CSV file before uploading.',
            ]);
        }

        $lines = [];
        if ($request->hasFile('package_file')) {
            $handle = fopen($request->file('package_file')->getRealPath(), 'r');
            while ($handle && ($row = fgetcsv($handle)) !== false) {
                $lines[] = implode(',', $row);
            }
            if ($handle) {
                fclose($handle);
            }
        }

        if (filled($data['package_rows'] ?? null)) {
            $lines = array_merge($lines, preg_split('/\r\n|\r|\n/', (string) $data['package_rows']) ?: []);
        }

        $results = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', str_getcsv((string) $line));
            $tracking = $parts[0] ?? '';
            if ($tracking === '' || in_array(Str::lower($tracking), ['tracking', 'tracking_number', 'tracking no.', 'scan'], true)) {
                continue;
            }

            $weight = isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : null;
            $unit = isset($parts[2]) && in_array(Str::lower($parts[2]), ['kg', 'lb', 'cuft', 'cbm'], true) ? Str::lower($parts[2]) : null;

            $results[] = DB::transaction(function () use ($shipment, $tracking, $weight, $unit) {
                $scanResult = $this->intakePackageIntoShipment($shipment, $tracking, $weight, $unit);
                $this->recordShipmentScan($shipment, $scanResult);

                return $scanResult;
            });
        }

        $summary = [
            'scanned' => count($results),
            'updated' => collect($results)->whereIn('result', ['Successful', 'Already received'])->count(),
            'not_found' => collect($results)->where('result', 'Not found')->count(),
            'ambiguous' => collect($results)->where('result', 'Ambiguous')->count(),
            'duplicates' => collect($results)->where('result', 'Duplicate')->count(),
        ];

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', $summary['updated'].' package(s) imported into shipment intake. '.$summary['duplicates'].' duplicate(s), '.$summary['not_found'].' not found.')
            ->with('shipment_scan_summary', $summary)
            ->with('shipment_scan_results', $results);
    }

    public function removeShipmentPackage(SuperadminLogisticsShipment $shipment, SuperadminLogisticsPackage $package)
    {
        abort_if((int) $package->shipment_id !== (int) $shipment->id, 404);
        $this->ensurePackageIsEditable($package);

        if ($package->manifest_id) {
            throw ValidationException::withMessages([
                'package' => 'This package is already on a manifest. Remove it from the manifest instead.',
            ]);
        }

        $tracking = $package->tracking_number;
        $shipmentNumber = $shipment->shipment_number;

        DB::transaction(function () use ($package, $shipment, $tracking, $shipmentNumber) {
            $notes = trim((string) $package->location_notes);
            $notes = trim($notes."\n".'Removed from shipment intake '.$shipmentNumber.' and returned to main logistics package room.');

            $package->update([
                'shipment_id' => null,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'location_status' => 'in_house',
                'location_notes' => $notes,
            ]);

            if (Schema::hasTable('superadmin_logistics_shipment_scans')) {
                DB::table('superadmin_logistics_shipment_scans')->insert([
                    'shipment_id' => $shipment->id,
                    'logistics_package_id' => $package->id,
                    'courier_package_id' => $package->courier_package_id,
                    'tenant_id' => $package->tenant_id,
                    'scan_value' => (string) $tracking,
                    'result' => 'Removed',
                    'message' => 'Removed from shipment intake and returned to main logistics package room.',
                    'previous_status' => 'In shipment intake',
                    'new_status' => 'Main package room',
                    'weight' => $package->weight,
                    'weight_unit' => $package->weight_unit,
                    'scanned_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        ActivityLog::record('shipment_intake_package_removed', 'superadmin_logistics', 'Removed package '.$tracking.' from shipment intake '.$shipmentNumber, [
            'shipment_id' => $shipment->id,
            'package_id' => $package->id,
        ]);

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', 'Package '.$tracking.' returned to the main logistics package room.');
    }

    public function generateShipmentCourierBills(SuperadminLogisticsShipment $shipment)
    {
        if ($shipment->shipment_type === 'sea') {
            $this->refreshShipmentOpenPackageCharges($shipment);
        }

        $shipment->load(['packages.tenant']);
        $openPackages = $shipment->packages->whereNull('manifest_id');
        if ($openPackages->isEmpty()) {
            return redirect()
                ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
                ->withErrors(['shipment' => 'There are no newly scanned packages waiting for manifest generation on this shipment.']);
        }

        $createdManifests = 0;
        $reusedManifests = 0;

        foreach ($openPackages->groupBy('tenant_id') as $tenantId => $packages) {
            if (! $tenantId || $packages->isEmpty()) {
                continue;
            }

            $manifest = SuperadminLogisticsManifest::where('shipment_id', $shipment->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($manifest) {
                $reusedManifests++;
            } else {
                $manifest = SuperadminLogisticsManifest::create([
                    'shipment_id' => $shipment->id,
                    'tenant_id' => $tenantId,
                    'manifest_number' => $this->nextManifestNumber(),
                    'warehouse_reference' => $shipment->master_number,
                    'cargo_company' => $shipment->cargo_company,
                    'received_at' => $shipment->arrival_date ?: now(),
                    'status' => 'received',
                    'require_checkout_scan' => true,
                    'notes' => 'Generated from bulk check-in shipment '.$shipment->shipment_number.'.',
                    'created_by' => auth()->id(),
                ]);

                $createdManifests++;
                ActivityLog::record('created', 'superadmin_logistics', 'Created logistics manifest '.$manifest->manifest_number.' from shipment '.$shipment->shipment_number);
                $this->notifyCourier($manifest->tenant_id, 'Logistics manifest created', 'Manifest '.$manifest->manifest_number.' was created for shipment '.$shipment->shipment_number.'.');
                $this->emailCourierManifestIssued($manifest);
            }

            $packages->each(function (SuperadminLogisticsPackage $package) use ($manifest) {
                $package->update(['manifest_id' => $manifest->id]);
            });
        }

        if ($createdManifests > 0 || $reusedManifests > 0) {
            $shipment->update(['status' => 'completed']);
        }

        if ($createdManifests === 0 && $reusedManifests === 0) {
            return redirect()
                ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
                ->withErrors(['shipment' => 'No courier-assigned packages were available for manifest generation. Assign unknown packages first.']);
        }

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', $createdManifests.' manifest(s) generated, '.$reusedManifests.' existing manifest(s) reused. You can now generate bulk shipment invoices from the generated manifest(s).');
    }

    public function generateShipmentInvoices(SuperadminLogisticsShipment $shipment)
    {
        $shipment->load(['manifests.packages']);
        if ($shipment->manifests->isEmpty()) {
            return redirect()
                ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
                ->withErrors(['shipment' => 'Generate the bulk manifest before generating shipment invoices.']);
        }

        $createdInvoices = 0;
        $skippedInvoices = 0;

        foreach ($shipment->manifests as $manifest) {
            $invoice = $this->createManifestShipmentInvoice($manifest, 'Generated from bulk check-in shipment '.$shipment->shipment_number.'.');
            if ($invoice) {
                $createdInvoices++;
                $this->notifyCourier($manifest->tenant_id, 'Shipment invoice created', 'Invoice '.$invoice->invoice_number.' is ready for review and payment.');
            } else {
                $skippedInvoices++;
            }
        }

        return redirect()
            ->route('superadmin.logistics.bulk-check-in', ['shipment_id' => $shipment->id])
            ->with('success', $createdInvoices.' shipment invoice(s) generated, '.$skippedInvoices.' manifest(s) skipped because they had no unbilled packages.');
    }
    public function bulkCheckIn(Request $request)
    {
        $data = $request->validate([
            'scans' => 'required|string|max:30000',
        ]);

        $scans = $this->parseBulkCheckInScans($data['scans']);
        if (count($scans) > 500) {
            throw ValidationException::withMessages([
                'scans' => 'Scan up to 500 packages at a time so the results remain easy to review.',
            ]);
        }

        $packagesByKey = $this->packagesByBulkScanKey(collect($scans)->pluck('key')->unique()->values()->all());
        $statusCache = [];
        $seen = [];
        $seenPackageIds = [];
        $results = [];
        $summary = [
            'scanned' => count($scans),
            'updated' => 0,
            'already_received' => 0,
            'not_found' => 0,
            'ambiguous' => 0,
            'duplicates' => 0,
            'missing_status' => 0,
        ];

        foreach ($scans as $scan) {
            if (isset($seen[$scan['key']])) {
                $summary['duplicates']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Duplicate', 'This tracking number was already included in this scan batch.');
                continue;
            }
            $seen[$scan['key']] = true;

            $matches = $packagesByKey[$scan['key']] ?? collect();
            if ($matches->isEmpty()) {
                $summary['not_found']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Not found', 'No courier package matched this tracking or supplier tracking number.');
                continue;
            }
            if ($matches->count() > 1) {
                $summary['ambiguous']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Ambiguous', 'More than one package matched this scan. Open the package records and correct duplicate tracking numbers.');
                continue;
            }

            $package = $matches->first();
            if (isset($seenPackageIds[$package->id])) {
                $summary['duplicates']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Duplicate', 'This package was already included in this scan batch under another tracking number.', $package);
                continue;
            }
            $seenPackageIds[$package->id] = true;

            $tenantId = (int) $package->tenant_id;
            $statusCache[$tenantId] = $statusCache[$tenantId] ?? $this->receivedAtPortStatusForTenant($tenantId);
            $targetStatus = $statusCache[$tenantId];

            if (! $targetStatus) {
                $summary['missing_status']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Missing status', 'This courier does not have an active "Received at the Port" package status.', $package);
                continue;
            }

            if ((int) $package->status_id === (int) $targetStatus->id) {
                $summary['already_received']++;
                $results[] = $this->bulkCheckInResult($scan['raw'], 'Already received', 'Package was already marked received at the port.', $package, $targetStatus);
                continue;
            }

            $previousStatus = optional($package->status)->name;
            PackageLog::create([
                'tenant_id' => $tenantId,
                'package_id' => $package->id,
                'status_id' => $targetStatus->id,
                'note' => 'Bulk warehouse scan-in: received at the port',
                'location' => 'Warehouse / Port',
                'added_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $summary['updated']++;
            $package->status_id = $targetStatus->id;
            $package->setRelation('status', $targetStatus);
            $results[] = $this->bulkCheckInResult($scan['raw'], 'Updated', 'Status changed from '.($previousStatus ?: 'No status').' to '.$targetStatus->name.'.', $package, $targetStatus, $previousStatus);
        }

        ActivityLog::record('bulk_check_in', 'superadmin_logistics', 'Bulk warehouse scan-in completed', $summary);

        $message = $summary['updated'].' package(s) checked in. '.$summary['already_received'].' already received, '.$summary['not_found'].' not found.';
        return redirect()
            ->route('superadmin.logistics.bulk-check-in')
            ->with('success', $message)
            ->with('bulk_check_in_summary', $summary)
            ->with('bulk_check_in_results', $results);
    }
    private function logisticsPage(Request $request, string $section)
    {
        $manifests = SuperadminLogisticsManifest::with(['tenant'])
            ->withCount('packages')
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested->where('manifest_number', 'like', $term)->orWhere('warehouse_reference', 'like', $term)->orWhere('cargo_company', 'like', $term));
            })
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();

        $tenants = Tenant::orderBy('company_name')->get();
        $rates = SuperadminLogisticsRate::orderBy('shipment_type')->orderBy('region')->orderBy('weight_unit')->orderBy('min_weight')->get();
        $pricing = $this->pricingSettings();
        $shipments = SuperadminLogisticsShipment::withCount(['packages', 'manifests'])->latest()->limit(12)->get();
        $customsOffices = CustomsOffice::whereNull('tenant_id')->orderBy('name')->get();
        $loadingPorts = LoadingPort::whereNull('tenant_id')->orderBy('name')->get();
        $invoiceStatus = $request->get('invoice_status');
        $invoices = SuperadminLogisticsInvoice::with(['tenant', 'manifest'])
            ->when(in_array($invoiceStatus, ['unpaid', 'paid', 'issued', 'void'], true), fn ($query) => $query->where('status', $invoiceStatus))
            ->latest()
            ->limit(50)
            ->get();
        $packagesQuery = $this->filteredLogisticsPackages($request);
        $packages = (clone $packagesQuery)->latest()->paginate($this->perPage($request, 20))->withQueryString();
        $customers = collect();
        $totalWeightLb = (float) SuperadminLogisticsPackage::whereIn('weight_unit', ['lb', 'kg'])->selectRaw("SUM(CASE WHEN weight_unit = 'kg' THEN weight * 2.20462262 ELSE weight END) as total")->value('total');
        $totalWeightKg = (float) SuperadminLogisticsPackage::whereIn('weight_unit', ['lb', 'kg'])->selectRaw("SUM(CASE WHEN weight_unit = 'lb' THEN weight * 0.45359237 ELSE weight END) as total")->value('total');
        $totalVolumeCuft = (float) SuperadminLogisticsPackage::whereIn('weight_unit', ['cuft', 'cbm'])->selectRaw("SUM(CASE WHEN weight_unit = 'cbm' THEN weight * 35.3147 ELSE weight END) as total")->value('total');
        $totalVolumeCbm = (float) SuperadminLogisticsPackage::whereIn('weight_unit', ['cuft', 'cbm'])->selectRaw("SUM(CASE WHEN weight_unit = 'cuft' THEN weight / 35.3147 ELSE weight END) as total")->value('total');
        $stats = [
            'shipments' => SuperadminLogisticsShipment::count(),
            'manifests' => SuperadminLogisticsManifest::count(),
            'received' => SuperadminLogisticsManifest::whereIn('status', ['received', 'checked_in'])->count(),
            'unpaid' => SuperadminLogisticsInvoice::where('status', 'unpaid')->sum('total'),
            'paid' => SuperadminLogisticsInvoice::where('status', 'paid')->sum('total'),
            'packages_uploaded' => SuperadminLogisticsPackage::count(),
            'packages_unassigned' => SuperadminLogisticsPackage::where(function ($query) {
                $query->whereNull('courier_package_id')->orWhere('location_status', 'inhouse_unknown_room');
            })->count(),
            'total_weight_lb' => $totalWeightLb,
            'total_weight_kg' => $totalWeightKg,
            'total_volume_cuft' => $totalVolumeCuft,
            'total_volume_cbm' => $totalVolumeCbm,
        ];

        return view('superadmin.logistics.index', compact('manifests', 'tenants', 'rates', 'pricing', 'invoices', 'stats', 'section', 'packages', 'customers', 'shipments', 'customsOffices', 'loadingPorts'));
    }

    public function searchCustomers(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%'.$term.'%';
        $customers = Customer::query()
            ->with(['user', 'tenant'])
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->join('tenants', 'customers.tenant_id', '=', 'tenants.id')
            ->where(function ($query) use ($like) {
                $query->where('customers.mailbox', 'like', $like)
                    ->orWhere('users.full_name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('tenants.company_name', 'like', $like);
            })
            ->orderBy('tenants.company_name')
            ->orderBy('customers.mailbox')
            ->select('customers.*')
            ->limit(25)
            ->get();

        return response()->json($customers->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'label' => trim($customer->mailbox.' - '.(optional($customer->user)->full_name ?: optional($customer->user)->email).' ('.optional($customer->tenant)->company_name.')'),
        ])->values());
    }

    public function exportPackages(Request $request): StreamedResponse
    {
        return $this->logisticsPackagesCsv($request, 'superadmin-logistics-packages-export-'.now()->format('Ymd-His').'.csv');
    }

    public function downloadPackages(Request $request): StreamedResponse
    {
        return $this->logisticsPackagesCsv($request, 'superadmin-logistics-packages-'.now()->format('Ymd-His').'.csv');
    }

    public function printPackages(Request $request)
    {
        $packages = $this->filteredLogisticsPackages($request)->latest()->get();

        return view('superadmin.logistics.packages-print', compact('packages'));
    }

    public function printPackageLabels(Request $request)
    {
        $packages = $this->filteredLogisticsPackages($request)
            ->with(['tenant', 'manifest', 'courierPackage.customer.user', 'courierPackage.outlet'])
            ->latest()
            ->get();

        return view('superadmin.logistics.package-labels', compact('packages'));
    }

    public function printPackageLabel(SuperadminLogisticsPackage $package)
    {
        $packages = collect([
            $package->load(['tenant', 'manifest', 'courierPackage.customer.user', 'courierPackage.outlet']),
        ]);

        return view('superadmin.logistics.package-labels', compact('packages'));
    }
public function assignUnknownPackage(Request $request, SuperadminLogisticsPackage $package)
    {
        $data = $request->validate(['customer_id' => 'required|exists:customers,id']);
        $customer = Customer::with(['tenant'])->findOrFail($data['customer_id']);
        $tenantId = (int) $customer->tenant_id;

        DB::transaction(function () use ($package, $customer, $tenantId) {
            $package->loadMissing('manifest');
            $manifest = $package->manifest;
            if ((int) $package->tenant_id !== $tenantId) {
                $package->update(['tenant_id' => $tenantId]);
                if ($manifest && $manifest->packages()->where('tenant_id', '<>', $tenantId)->doesntExist()) {
                    $manifest->update(['tenant_id' => $tenantId]);
                }
            }

            $courierPackage = $package->courierPackage;
            if (! $courierPackage) {
                $courierPackage = Package::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'outlet_id' => $customer->outlet_id,
                    'tracking_number' => $this->nextHawbNumber($tenantId),
                    'supplier_tracking' => $package->tracking_number,
                    'mailbox' => $customer->mailbox,
                    'description' => $package->description,
                    'weight' => (float) $package->weight,
                    'freight_type' => 'sendx',
                    'delivery_type' => 'pickup',
                    'status_id' => $this->statusIdForTenant($tenantId, null),
                    'storage_days' => 0,
                    'storage_fee' => 0,
                    'processing_fee_jmd' => $this->processingFee($tenantId),
                    'date_received' => now()->toDateString(),
                    'notes' => 'Assigned from System Admin Unknown Room.',
                ]);

                PackageLog::create([
                    'tenant_id' => $tenantId,
                    'package_id' => $courierPackage->id,
                    'status_id' => $courierPackage->status_id,
                    'note' => 'Package assigned from System Admin Unknown Room',
                    'location' => null,
                    'added_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            } else {
                $courierPackage->update([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'outlet_id' => $customer->outlet_id,
                    'mailbox' => $customer->mailbox,
                ]);
            }

            $package->update([
                'tenant_id' => $tenantId,
                'courier_package_id' => $courierPackage->id,
                'location_status' => null,
                'location_notes' => null,
            ]);
        });

        return back()->with('success', 'Unknown package assigned to '.$customer->mailbox.'.');
    }

    private function filteredLogisticsPackages(Request $request)
    {
        return SuperadminLogisticsPackage::with(['tenant', 'manifest', 'courierPackage.customer.user'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('assignment'), function ($query) use ($request) {
                if ($request->assignment === 'unknown') {
                    $query->where(function ($nested) {
                        $nested->whereNull('courier_package_id')->orWhere('location_status', 'inhouse_unknown_room');
                    });
                } elseif ($request->assignment === 'assigned') {
                    $query->whereNotNull('courier_package_id')->where(function ($nested) {
                        $nested->whereNull('location_status')->orWhere('location_status', '<>', 'inhouse_unknown_room');
                    });
                }
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('tracking_number', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('location_notes', 'like', $term)
                        ->orWhereHas('tenant', fn ($tenant) => $tenant->where('company_name', 'like', $term))
                        ->orWhereHas('courierPackage', fn ($package) => $package->where('tracking_number', 'like', $term)->orWhere('supplier_tracking', 'like', $term)->orWhere('mailbox', 'like', $term))
                        ->orWhereHas('courierPackage.customer.user', fn ($user) => $user->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            });
    }

    private function logisticsPackagesCsv(Request $request, string $filename): StreamedResponse
    {
        $packages = $this->filteredLogisticsPackages($request)->latest()->get();

        return response()->streamDownload(function () use ($packages) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['uploaded_at', 'tracking_number', 'hawb', 'courier', 'mailbox', 'client', 'weight', 'unit', 'amount_jmd', 'assignment', 'manifest']);
            foreach ($packages as $package) {
                fputcsv($out, [
                    optional($package->created_at)->format('Y-m-d H:i:s'),
                    $package->tracking_number,
                    optional($package->courierPackage)->tracking_number,
                    optional($package->tenant)->company_name,
                    optional($package->courierPackage)->mailbox,
                    optional(optional(optional($package->courierPackage)->customer)->user)->full_name,
                    $package->weight,
                    $package->weight_unit,
                    number_format((float) $package->amount, 2, '.', ''),
                    ($package->location_status === 'inhouse_unknown_room' || ! $package->courier_package_id) ? 'Unknown' : 'Assigned',
                    optional($package->manifest)->manifest_number,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
    public function storeManifest(Request $request)
    {
        $data = $this->manifestData($request);

        $data['manifest_number'] = $data['manifest_number'] ?: $this->nextManifestNumber();
        $data['received_at'] = $data['received_at'] ?? now();
        $data['require_checkout_scan'] = $request->boolean('require_checkout_scan', true);

        $manifest = SuperadminLogisticsManifest::create($data + [
            'require_checkout_scan' => $request->boolean('require_checkout_scan', true),
            'status' => 'received',
            'created_by' => auth()->id(),
        ]);

        ActivityLog::record('created', 'superadmin_logistics', 'Created logistics manifest '.$manifest->manifest_number);
        $this->notifyCourier($manifest->tenant_id, 'Logistics manifest created', 'Manifest '.$manifest->manifest_number.' was created for your courier account.');
        $this->emailCourierManifestIssued($manifest);

        return redirect()->route('superadmin.logistics.show', $manifest)->with('success', 'Logistics manifest created.');
    }

    public function updateManifest(Request $request, SuperadminLogisticsManifest $manifest)
    {
        $data = $this->manifestData($request, $manifest);
        $manifest->update([
            'tenant_id' => $data['tenant_id'],
            'manifest_number' => $data['manifest_number'] ?: $manifest->manifest_number,
            'warehouse_reference' => $data['warehouse_reference'] ?? null,
            'cargo_company' => $data['cargo_company'] ?? null,
            'received_at' => $data['received_at'] ?? null,
            'require_checkout_scan' => $request->boolean('require_checkout_scan'),
            'notes' => $data['notes'] ?? null,
        ]);
        $manifest->packages()->whereDoesntHave('invoiceItems')->update(['tenant_id' => $manifest->tenant_id]);

        ActivityLog::record('updated', 'superadmin_logistics', 'Updated logistics manifest '.$manifest->manifest_number);

        return back()->with('success', 'Manifest updated.');
    }

    public function destroyManifest(SuperadminLogisticsManifest $manifest)
    {
        if ($manifest->invoices()->exists()) {
            throw ValidationException::withMessages([
                'manifest' => 'This manifest has logistics invoices and cannot be deleted.',
            ]);
        }

        $number = $manifest->manifest_number;
        $manifest->delete();

        ActivityLog::record('deleted', 'superadmin_logistics', 'Deleted logistics manifest '.$number);

        return redirect()->route('superadmin.logistics.manifests')->with('success', 'Manifest deleted.');
    }

    public function show(SuperadminLogisticsManifest $manifest)
    {
        $this->refreshManifestOpenPackageCharges($manifest, true);
        $manifest->load(['tenant', 'packages.invoiceItems.invoice', 'invoices.items.package.courierPackage', 'invoices.payments']);
        $openPackages = $manifest->packages->filter(fn ($package) => $package->invoiceItems->isEmpty())->values();
        $invoicedPackages = $manifest->packages->filter(fn ($package) => $package->invoiceItems->isNotEmpty())->values();
        $rates = SuperadminLogisticsRate::where('is_active', true)->orderBy('shipment_type')->orderBy('region')->orderBy('weight_unit')->orderBy('min_weight')->get();
        $couriers = Tenant::orderBy('company_name')->get(['id', 'company_name']);

        return view('superadmin.logistics.show', compact('manifest', 'rates', 'openPackages', 'invoicedPackages', 'couriers'));
    }

    public function addPackages(Request $request, SuperadminLogisticsManifest $manifest)
    {
        if ($manifest->status === 'checked_out') {
            throw ValidationException::withMessages([
                'packages' => 'This manifest has already been checked out. New packages cannot be added.',
            ]);
        }

        $data = $request->validate([
            'packages' => 'required|string',
        ]);

        $rows = $this->packageRowsFromText($data['packages'], $manifest->shipment?->shipment_type ?? 'air');
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['packages' => 'Enter at least one package row.']);
        }

        $duplicateRows = collect();
        $seenTracking = collect();
        $uniqueRows = $rows->filter(function ($row) use ($seenTracking, $duplicateRows) {
            if ($seenTracking->contains($row['tracking_number'])) {
                $duplicateRows->push($row['tracking_number']);

                return false;
            }

            $seenTracking->push($row['tracking_number']);

            return true;
        })->values();

        $existingTracking = SuperadminLogisticsPackage::whereIn('tracking_number', $uniqueRows->pluck('tracking_number'))
            ->pluck('tracking_number')
            ->unique()
            ->values();

        $rowsToCreate = $uniqueRows
            ->reject(fn ($row) => $existingTracking->contains($row['tracking_number']))
            ->values();
        $skippedTracking = $duplicateRows->merge($existingTracking)->unique()->values();

        if ($rowsToCreate->isEmpty()) {
            throw ValidationException::withMessages([
                'packages' => 'No new packages were added. Duplicate tracking number(s): '.$skippedTracking->implode(', '),
            ]);
        }

        $created = 0;
        foreach ($rowsToCreate as $row) {
            $charges = $this->chargesForPackage($row['weight'], $row['weight_unit'], $manifest->shipment?->shipment_type ?? 'air', $manifest->shipment?->region ?? 'USA');
            SuperadminLogisticsPackage::create([
                'manifest_id' => $manifest->id,
                'tenant_id' => $manifest->tenant_id,
                'tracking_number' => $row['tracking_number'],
                'description' => $row['description'],
                'weight' => $row['weight'],
                'weight_unit' => $row['weight_unit'],
            ] + $charges);
            $created++;
        }

        ActivityLog::record('packages_added', 'superadmin_logistics', 'Added '.$created.' package rows to '.$manifest->manifest_number.($skippedTracking->isNotEmpty() ? '; skipped duplicates: '.$skippedTracking->implode(', ') : ''));
        $this->notifyCourier($manifest->tenant_id, 'Logistics packages added', $created.' package row'.($created === 1 ? '' : 's').' were added to manifest '.$manifest->manifest_number.'.');

        $message = 'Added '.$created.' package row'.($created === 1 ? '' : 's').' to manifest.';
        if ($skippedTracking->isNotEmpty()) {
            $message .= ' Skipped '.$skippedTracking->count().' duplicate'.($skippedTracking->count() === 1 ? '' : 's').': '.$skippedTracking->implode(', ');
        }

        return back()->with('success', $message);
    }

    public function manifestPackageTemplate(SuperadminLogisticsManifest $manifest): StreamedResponse
    {
        $columns = ['tracking_number', 'weight', 'weight_unit', 'description'];
        $filename = Str::slug($manifest->manifest_number ?: 'manifest').'-package-template.csv';
        $isSea = optional($manifest->shipment)->shipment_type === 'sea';

        return response()->streamDownload(function () use ($columns, $isSea) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $isSea ? ['ABC123', '12.5', 'cuft', 'Box of parts'] : ['ABC123', '10', 'lb', 'Box of parts']);
            fputcsv($handle, $isSea ? ['XYZ789', '0.35', 'cbm', 'Carton'] : ['XYZ789', '4.5', 'kg', 'Carton']);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportManifestPackages(SuperadminLogisticsManifest $manifest): StreamedResponse
    {
        $manifest->load('packages');
        $filename = Str::slug($manifest->manifest_number ?: 'manifest').'-packages.csv';

        return response()->streamDownload(function () use ($manifest) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'tracking_number',
                'weight',
                'weight_unit',
                'description',
                'warehouse_fee_usd',
                'service_fee_usd',
                'freight_charge_usd',
                'amount_usd',
                'exchange_rate_jmd',
                'amount_jmd',
                'status',
            ]);

            foreach ($manifest->packages as $package) {
                fputcsv($handle, [
                    $package->tracking_number,
                    $package->weight,
                    $package->weight_unit,
                    $package->description,
                    $package->warehouse_fee,
                    $package->service_fee,
                    $package->freight_charge,
                    $package->amount_usd,
                    $package->exchange_rate_jmd,
                    $package->amount,
                    $package->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function bulkAddManifestPackages(Request $request, SuperadminLogisticsManifest $manifest)
    {
        if ($manifest->status === 'checked_out') {
            throw ValidationException::withMessages([
                'file' => 'This manifest has already been checked out. New packages cannot be added.',
            ]);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $rows = $this->bulkPackageRowsFromCsv($request->file('file')->getRealPath())
            ->map(fn ($row) => [
                'row_number' => $row['row_number'] ?? null,
                'tracking_number' => $this->normalizeTrackingNumber((string) ($row['tracking_number'] ?? '')),
                'weight' => $row['weight'] ?? null,
                'weight_unit' => $this->normalizeMeasureUnit((string) ($row['weight_unit'] ?? 'lb'), $manifest->shipment?->shipment_type ?? 'air'),
                'description' => $row['description'] ?? null,
            ])
            ->filter(fn ($row) => $row['tracking_number'] !== '' || $row['weight'] !== null || trim((string) $row['description']) !== '')
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'Upload a CSV file with at least one package row.']);
        }

        $invalidRows = $rows->filter(fn ($row) => $row['tracking_number'] === '' || $row['weight'] === null || (float) $row['weight'] <= 0);
        $validRows = $rows->reject(fn ($row) => $row['tracking_number'] === '' || $row['weight'] === null || (float) $row['weight'] <= 0)->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'No packages were imported. Every row is missing a tracking number or valid weight.']);
        }

        $duplicateRows = collect();
        $seenTracking = collect();
        $uniqueRows = $validRows->filter(function ($row) use ($seenTracking, $duplicateRows) {
            if ($seenTracking->contains($row['tracking_number'])) {
                $duplicateRows->push($row['tracking_number']);

                return false;
            }

            $seenTracking->push($row['tracking_number']);

            return true;
        })->values();

        $existingTracking = SuperadminLogisticsPackage::whereIn('tracking_number', $uniqueRows->pluck('tracking_number'))
            ->pluck('tracking_number')
            ->unique()
            ->values();

        $rowsToCreate = $uniqueRows
            ->reject(fn ($row) => $existingTracking->contains($row['tracking_number']))
            ->values();

        if ($rowsToCreate->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'No new packages were imported. Duplicate tracking number(s): '.$duplicateRows->merge($existingTracking)->unique()->implode(', '),
            ]);
        }

        $created = 0;
        foreach ($rowsToCreate as $row) {
            $charges = $this->chargesForPackage((float) $row['weight'], $row['weight_unit'], $manifest->shipment?->shipment_type ?? 'air', $manifest->shipment?->region ?? 'USA');
            SuperadminLogisticsPackage::create([
                'manifest_id' => $manifest->id,
                'tenant_id' => $manifest->tenant_id,
                'tracking_number' => $row['tracking_number'],
                'description' => $row['description'],
                'weight' => (float) $row['weight'],
                'weight_unit' => $row['weight_unit'],
            ] + $charges);
            $created++;
        }

        $skippedCount = $invalidRows->count() + $duplicateRows->count() + $existingTracking->count();
        ActivityLog::record('packages_bulk_uploaded', 'superadmin_logistics', 'Packages uploaded'.($skippedCount ? '; skipped '.$skippedCount.' row(s)' : ''));
        $this->notifyCourier($manifest->tenant_id, 'Packages uploaded', $created.' package row'.($created === 1 ? '' : 's').' uploaded to manifest '.$manifest->manifest_number.'.');

        $skips = $invalidRows->map(fn ($row) => 'Row '.($row['row_number'] ?? '?').': missing tracking number or valid weight')
            ->merge($duplicateRows->map(fn ($tracking) => 'Duplicate in file: '.$tracking))
            ->merge($existingTracking->map(fn ($tracking) => 'Already exists: '.$tracking))
            ->take(12)
            ->values();

        $message = 'Bulk upload complete. Added '.$created.' package'.($created === 1 ? '' : 's').' to manifest.';
        if ($skippedCount) {
            $message .= ' Skipped '.$skippedCount.' row'.($skippedCount === 1 ? '' : 's').'.';
        }

        return back()
            ->with('success', $message)
            ->with('manifest_bulk_upload_skips', $skips->all());
    }

    public function bulkUploadTemplate(): StreamedResponse
    {
        $columns = [
            'mailbox',
            'tracking_number',
            'weight',
            'weight_unit',
            'description',
            'courier',
            'merchant',
            'freight_type',
            'delivery_type',
            'date_received',
            'notes',
        ];

        return response()->streamDownload(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, ['MBX-1001', 'SUP123456', '2.50', 'lb', 'Shoes', 'DHL', 'Amazon', 'sendx', 'pickup', now()->toDateString(), 'Warehouse bulk upload']);
            fclose($handle);
        }, 'superadmin-logistics-bulk-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkUploadPackages(Request $request, SubscriptionLimitService $limits)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:8192',
        ]);

        $rows = $this->bulkPackageRowsFromCsv($request->file('file')->getRealPath());
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'Upload a CSV file with at least one package row.']);
        }

        $rowsToCreate = collect();
        $skipped = collect();
        $seenTracking = collect();
        $mailboxCache = [];
        $tenantIncrements = [];

        foreach ($rows as $row) {
            $tracking = $this->normalizeTrackingNumber($row['tracking_number'] ?? '');
            $mailbox = Customer::normalizeMailbox($row['mailbox'] ?? null) ?? '';

            if ($tracking === '') {
                $skipped->push($this->bulkSkip($row, 'missing tracking number'));
                continue;
            }

            if ($row['weight'] === null || (float) $row['weight'] < 0) {
                $skipped->push($this->bulkSkip($row, 'missing or invalid weight'));
                continue;
            }

            if ($seenTracking->contains($tracking)) {
                $skipped->push($this->bulkSkip($row, 'duplicate tracking number in upload'));
                continue;
            }
            $seenTracking->push($tracking);

            $mailboxKey = $mailbox;
            if ($mailbox !== '' && ! array_key_exists($mailboxKey, $mailboxCache)) {
                $mailboxCache[$mailboxKey] = Customer::with('tenant')
                    ->whereNormalizedMailbox($mailbox)
                    ->get();
            }

            $customers = $mailbox === '' ? collect() : $mailboxCache[$mailboxKey];
            $customer = $customers->count() === 1 ? $customers->first() : null;
            $tenant = $customer?->tenant
                ?: $this->tenantForBulkMailboxPrefix($mailbox)
                ?: $this->tenantForUnknownBulkRow($row, $request->integer('fallback_tenant_id'));

            if (! $tenant) {
                $skipped->push($this->bulkSkip($row, $mailbox === '' ? 'missing mailbox number and no fallback courier' : 'mailbox not found and no courier prefix matched'));
                continue;
            }

            $tenantId = (int) $tenant->id;
            if (
                SuperadminLogisticsPackage::where('tracking_number', $tracking)->exists()
                || Package::where('tenant_id', $tenantId)
                    ->where(fn ($query) => $query->where('tracking_number', $tracking)->orWhere('supplier_tracking', $tracking))
                    ->exists()
            ) {
                $skipped->push($this->bulkSkip($row, 'tracking number already exists'));
                continue;
            }

            if ($customer) {
                try {
                    $nextIncrement = ($tenantIncrements[$tenantId] ?? 0) + 1;
                    $limits->ensureCanCreate($tenantId, 'packages_month', $nextIncrement);
                    $tenantIncrements[$tenantId] = $nextIncrement;
                } catch (ValidationException $exception) {
                    $skipped->push($this->bulkSkip($row, collect($exception->errors())->flatten()->first() ?: 'subscription limit blocked this package'));
                    continue;
                }
            }

            $row['tracking_number'] = $tracking;
            $row['mailbox'] = $mailbox;
            $row['tenant'] = $tenant;
            $row['customer'] = $customer;
            $row['assignment_note'] = $customer
                ? 'Assigned to customer '.$customer->mailbox
                : ($mailbox === '' ? 'Courier assigned from fallback courier; customer pending' : 'Courier assigned from mailbox prefix '.$mailbox.'; customer pending');
            $rowsToCreate->push($row);
        }

        if ($rowsToCreate->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'No packages were imported. '.$this->bulkSkipSummary($skipped),
            ]);
        }

        $created = 0;
        $assignedToCustomer = 0;
        $assignedToCourier = 0;

        DB::transaction(function () use ($rowsToCreate, &$created, &$assignedToCustomer, &$assignedToCourier) {
            foreach ($rowsToCreate as $row) {
                /** @var \App\Models\Tenant $tenant */
                $tenant = $row['tenant'];
                $tenantId = (int) $tenant->id;
                $customer = $row['customer'];
                $shipmentType = str_contains(strtolower((string) ($row['freight_type'] ?? '')), 'sea') ? 'sea' : 'air';
                $charges = $this->chargesForPackage((float) $row['weight'], $row['weight_unit'], $shipmentType, 'USA');

                $logisticsPackage = SuperadminLogisticsPackage::create([
                    'manifest_id' => null,
                    'tenant_id' => $tenantId,
                    'tracking_number' => $row['tracking_number'],
                    'description' => $row['description'],
                    'weight' => (float) $row['weight'],
                    'weight_unit' => $row['weight_unit'],
                    'location_status' => $customer ? 'in_house' : 'inhouse_unknown_room',
                    'location_notes' => trim(($row['assignment_note'] ?? '')."\n".($row['notes'] ?? '')),
                ] + $charges);

                if ($customer) {
                    $package = Package::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'outlet_id' => $customer->outlet_id,
                        'tracking_number' => $this->nextHawbNumber($tenantId),
                        'supplier_tracking' => $row['tracking_number'],
                        'mailbox' => $customer->mailbox,
                        'courier' => $row['courier'] ?: null,
                        'merchant' => $row['merchant'] ?: null,
                        'description' => $row['description'],
                        'weight' => (float) $row['weight'],
                        'price_usd' => (float) ($row['price_usd'] ?? 0),
                        'price_jmd' => (float) ($row['price_jmd'] ?? 0),
                        'freight_type' => $this->allowed($row['freight_type'] ?? 'sendx', ['air', 'sea', 'local', 'sendx'], 'sendx'),
                        'delivery_type' => $this->allowed($row['delivery_type'] ?? 'pickup', ['pickup', 'delivery'], 'pickup'),
                        'status_id' => $this->statusIdForTenant($tenantId, $row['status'] ?? null),
                        'storage_days' => 0,
                        'storage_fee' => 0,
                        'processing_fee_jmd' => $this->processingFee($tenantId),
                        'date_received' => $row['date_received'] ?: now()->toDateString(),
                        'notes' => $this->bulkPackageNotes($row, $charges),
                    ]);

                    $logisticsPackage->update(['courier_package_id' => $package->id]);

                    PackageLog::create([
                        'tenant_id' => $tenantId,
                        'package_id' => $package->id,
                        'status_id' => $package->status_id,
                        'note' => 'Package assigned from bulk upload',
                        'location' => null,
                        'added_by' => auth()->id(),
                        'created_at' => now(),
                    ]);

                    $assignedToCustomer++;
                } else {
                    $assignedToCourier++;
                }

                $created++;
            }
        });

        ActivityLog::record('bulk_imported', 'superadmin_logistics', 'Packages uploaded without manifest creation. '.($skipped->isNotEmpty() ? 'Skipped '.$skipped->count().' row(s).' : ''), [
            'created' => $created,
            'assigned_to_customer' => $assignedToCustomer,
            'assigned_to_courier' => $assignedToCourier,
            'skipped' => $skipped->count(),
        ]);

        $message = 'Bulk upload complete. Created '.$created.' package'.($created === 1 ? '' : 's').' without generating a manifest.';
        $message .= ' '.$assignedToCustomer.' assigned to customer'.($assignedToCustomer === 1 ? '' : 's').', '.$assignedToCourier.' assigned to courier'.($assignedToCourier === 1 ? '' : 's').' for customer assignment.';
        if ($skipped->isNotEmpty()) {
            $message .= ' Skipped '.$skipped->count().' row'.($skipped->count() === 1 ? '' : 's').'.';
        }

        return back()
            ->with('success', $message)
            ->with('bulk_upload_skips', $skipped->take(10)->values()->all());
    }
    public function updatePackage(Request $request, SuperadminLogisticsManifest $manifest, SuperadminLogisticsPackage $package)
    {
        $this->ensurePackageBelongsToManifest($manifest, $package);
        $this->ensurePackageIsEditable($package);

        $data = $request->validate([
            'tracking_number' => 'required|string|max:150|unique:superadmin_logistics_packages,tracking_number,'.$package->id,
            'description' => 'nullable|string|max:255',
            'weight' => 'required|numeric|min:0',
            'weight_unit' => 'required|in:lb,kg,cuft,cbm',
            'warehouse_fee' => 'nullable|numeric|min:0',
            'service_fee' => 'nullable|numeric|min:0',
            'freight_rate' => 'nullable|numeric|min:0',
            'freight_weight' => 'nullable|numeric|min:0',
            'freight_unit' => 'nullable|in:lb,kg,cuft,cbm',
            'freight_charge' => 'nullable|numeric|min:0',
            'exchange_rate_jmd' => 'nullable|numeric|min:0',
        ]);
        $data['tracking_number'] = $this->normalizeTrackingNumber($data['tracking_number']);

        $charges = $this->chargesForPackage((float) $data['weight'], $data['weight_unit'], $manifest->shipment?->shipment_type ?? 'air', $manifest->shipment?->region ?? 'USA');
        $charges = $this->applyPackageChargeOverrides($charges, $data);
        $package->update(collect($data)->only(['tracking_number', 'description', 'weight', 'weight_unit'])->all() + $charges);

        $package->invoiceItems()->update([
            'tracking_number' => $package->tracking_number,
            'weight' => $package->weight,
            'weight_unit' => $package->weight_unit,
            'rate' => $package->rate,
                    'warehouse_fee' => $package->warehouse_fee,
                    'service_fee' => $package->service_fee,
                    'freight_rate' => $package->freight_rate,
                    'freight_weight' => $package->freight_weight,
                    'freight_unit' => $package->freight_unit,
                    'freight_charge' => $package->freight_charge,
                    'amount_usd' => $package->amount_usd,
                    'exchange_rate_jmd' => $package->exchange_rate_jmd,
                    'amount' => $package->amount,
                    'updated_at' => now(),
        ]);

        $this->recalculatePackageInvoices($package);

        ActivityLog::record('updated', 'superadmin_logistics', 'Updated logistics package '.$package->tracking_number);

        return back()->with('success', 'Package updated.');
    }

    public function destroyPackage(SuperadminLogisticsManifest $manifest, SuperadminLogisticsPackage $package)
    {
        $this->ensurePackageBelongsToManifest($manifest, $package);
        $this->ensurePackageIsEditable($package);

        $invoiceIds = $package->invoiceItems()->pluck('invoice_id')->all();
        $tracking = $package->tracking_number;
        $package->delete();
        $this->recalculateInvoices($invoiceIds);

        ActivityLog::record('deleted', 'superadmin_logistics', 'Deleted logistics package '.$tracking);

        return back()->with('success', 'Package deleted.');
    }

    public function checkIn(Request $request, SuperadminLogisticsManifest $manifest)
    {
        $packages = $this->packagesFromScan($request, $manifest);
        foreach ($packages as $package) {
            $package->update(['checked_in_at' => now(), 'checked_in_by' => auth()->id()]);
        }
        $manifest->update(['status' => 'checked_in']);
        $this->notifyCourier($manifest->tenant_id, 'Logistics packages checked in', $packages->count().' package(s) were checked in on manifest '.$manifest->manifest_number.'.');

        return back()->with('success', 'Checked in '.$packages->count().' package(s).');
    }

    public function createInvoice(Request $request, SuperadminLogisticsManifest $manifest)
    {
        $data = $request->validate([
            'billing_scope' => 'required|in:manifest,packages',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer',
            'notes' => 'nullable|string',
        ]);

        $packages = $manifest->packages()
            ->when($data['billing_scope'] === 'packages', fn ($query) => $query->whereIn('id', $data['package_ids'] ?? []))
            ->whereDoesntHave('invoiceItems')
            ->get();

        if ($packages->isEmpty()) {
            throw ValidationException::withMessages(['package_ids' => 'Select at least one package to bill.']);
        }

        $invoice = DB::transaction(function () use ($manifest, $packages, $data) {
            $total = (float) $packages->sum('amount');
            $invoice = SuperadminLogisticsInvoice::create([
                'manifest_id' => $manifest->id,
                'tenant_id' => $manifest->tenant_id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'billing_scope' => $data['billing_scope'],
                'subtotal' => $total,
                'total' => $total,
                'balance_jmd' => $total,
                'status' => 'unpaid',
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($packages as $package) {
                $invoice->items()->create([
                    'package_id' => $package->id,
                    'tracking_number' => $package->tracking_number,
                    'weight' => $package->weight,
                    'weight_unit' => $package->weight_unit,
                    'rate' => $package->rate,
                    'warehouse_fee' => $package->warehouse_fee,
                    'service_fee' => $package->service_fee,
                    'freight_rate' => $package->freight_rate,
                    'freight_weight' => $package->freight_weight,
                    'freight_unit' => $package->freight_unit,
                    'freight_charge' => $package->freight_charge,
                    'amount_usd' => $package->amount_usd,
                    'exchange_rate_jmd' => $package->exchange_rate_jmd,
                    'amount' => $package->amount,
                ]);
            }

            return $this->applyCourierCreditToInvoice($invoice);
        });
        $this->notifyCourier($manifest->tenant_id, 'Logistics invoice created', 'Invoice '.$invoice->invoice_number.' is ready for review and payment.');

        return back()->with('success', 'Invoice '.$invoice->invoice_number.' created for courier pickup.');
    }

    private function createManifestShipmentInvoice(SuperadminLogisticsManifest $manifest, ?string $notes = null): ?SuperadminLogisticsInvoice
    {
        $packages = $manifest->packages()
            ->whereDoesntHave('invoiceItems')
            ->get();

        if ($packages->isEmpty()) {
            return null;
        }

        $subtotal = round((float) $packages->sum('amount'), 2);
        if ($subtotal <= 0) {
            return null;
        }

        return DB::transaction(function () use ($manifest, $packages, $subtotal, $notes) {
            $invoice = SuperadminLogisticsInvoice::create([
                'manifest_id' => $manifest->id,
                'tenant_id' => $manifest->tenant_id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'billing_scope' => 'manifest',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'balance_jmd' => $subtotal,
                'status' => 'unpaid',
                'created_by' => auth()->id(),
                'notes' => $notes,
            ]);

            foreach ($packages as $package) {
                $invoice->items()->create([
                    'package_id' => $package->id,
                    'tracking_number' => $package->tracking_number,
                    'weight' => $package->weight,
                    'weight_unit' => $package->weight_unit,
                    'rate' => $package->rate,
                    'warehouse_fee' => $package->warehouse_fee,
                    'service_fee' => $package->service_fee,
                    'freight_rate' => $package->freight_rate,
                    'freight_weight' => $package->freight_weight,
                    'freight_unit' => $package->freight_unit,
                    'freight_charge' => $package->freight_charge,
                    'amount_usd' => $package->amount_usd,
                    'exchange_rate_jmd' => $package->exchange_rate_jmd,
                    'amount' => $package->amount,
                ]);
            }

            return $this->applyCourierCreditToInvoice($invoice);
        });
    }

    public function downloadInvoice(SuperadminLogisticsInvoice $invoice)
    {
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);
        $platformSettings = SystemSetting::pluck('value', 'key');

        return response()
            ->view('superadmin.logistics.invoice-download', compact('invoice', 'platformSettings'))
            ->header('Content-Type', 'text/html');
    }

    public function downloadThermalInvoice(SuperadminLogisticsInvoice $invoice)
    {
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);
        $platformSettings = SystemSetting::pluck('value', 'key');

        return LogisticsInvoicePdf::inline($invoice, $platformSettings);
    }

    public function downloadThermalInvoiceFile(SuperadminLogisticsInvoice $invoice)
    {
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);
        $platformSettings = SystemSetting::pluck('value', 'key');

        return LogisticsInvoicePdf::download($invoice, $platformSettings);
    }

    public function viewInvoiceReceipt(SuperadminLogisticsInvoice $invoice)
    {
        return $this->inlinePublicFile($invoice->receipt_path, $invoice->receipt_original_name);
    }

    public function viewSupplierInvoice(SuperadminLogisticsInvoice $invoice)
    {
        return $this->inlinePublicFile($invoice->supplier_invoice_path, $invoice->supplier_invoice_original_name);
    }

    public function addInvoicePayment(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        $data = $request->validate([
            'amount_jmd' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:60',
            'reference_number' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        $amount = min((float) $data['amount_jmd'], max(0, (float) $invoice->balance_jmd));
        if ($amount <= 0.01) {
            return back()->withErrors(['amount_jmd' => 'This invoice does not have an outstanding balance.']);
        }

        SuperadminLogisticsInvoicePayment::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? null,
            'amount_jmd' => $amount,
            'source' => 'manual',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $invoice = $this->syncInvoicePayments($invoice->fresh());

        return back()->with('success', 'Payment applied. Outstanding balance: JMD '.number_format((float) $invoice->balance_jmd, 2));
    }

    public function applyInvoiceCredit(SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        $invoice = $this->applyCourierCreditToInvoice($invoice);

        return back()->with('success', 'Courier credit applied. Outstanding balance: JMD '.number_format((float) $invoice->balance_jmd, 2));
    }

    public function markPaid(SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        if ((float) $invoice->balance_jmd <= 0.01) {
            return back()->with('success', 'Logistics invoice is already paid.');
        }

        SuperadminLogisticsInvoicePayment::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'payment_method' => 'cash',
            'reference_number' => 'Marked paid by admin',
            'amount_jmd' => (float) $invoice->balance_jmd,
            'source' => 'manual',
            'created_by' => auth()->id(),
        ]);

        $this->syncInvoicePayments($invoice->fresh());

        return back()->with('success', 'Logistics invoice marked as paid.');
    }

    public function updateInvoice(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');

        $data = $request->validate([
            'discount_jmd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $discount = min((float) ($data['discount_jmd'] ?? 0), (float) $invoice->subtotal);
        $total = max(0, (float) $invoice->subtotal - $discount);
        $paid = min((float) $invoice->amount_paid_jmd, $total);
        $invoice->update([
            'discount_jmd' => $discount,
            'total' => $total,
            'amount_paid_jmd' => $paid,
            'balance_jmd' => max(0, $total - $paid),
            'status' => $total - $paid <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Logistics invoice updated.');
    }

    public function removeInvoiceItem(SuperadminLogisticsInvoice $invoice, SuperadminLogisticsInvoiceItem $item)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        abort_if((int) $item->invoice_id !== (int) $invoice->id, 404);
        $item->delete();
        $this->recalculateInvoices([$invoice->id]);

        return back()->with('success', 'Package removed from invoice and returned to open manifest packages.');
    }

    public function addInvoiceItems(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        $data = $request->validate(['tracking_numbers' => 'required|string']);
        $tracking = collect(preg_split('/[\r\n,]+/', $data['tracking_numbers']))
            ->map(fn ($value) => $this->normalizeTrackingNumber($value))
            ->filter()
            ->values();
        $duplicates = $tracking->duplicates()->unique()->values();
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['tracking_numbers' => 'Duplicated scan rejected: '.$duplicates->implode(', ')]);
        }

        $packages = SuperadminLogisticsPackage::where('manifest_id', $invoice->manifest_id)
            ->where('tenant_id', $invoice->tenant_id)
            ->whereDoesntHave('invoiceItems')
            ->where(function ($query) use ($tracking) {
                $query->whereIn('tracking_number', $tracking)
                    ->orWhereHas('courierPackage', function ($packageQuery) use ($tracking) {
                        $packageQuery->whereIn('tracking_number', $tracking)
                            ->orWhereIn('supplier_tracking', $tracking);
                    });
            })
            ->get();

        if ($packages->isEmpty()) {
            throw ValidationException::withMessages(['tracking_numbers' => 'No open manifest packages matched those tracking numbers.']);
        }

        foreach ($packages as $package) {
            $invoice->items()->create([
                'package_id' => $package->id,
                'tracking_number' => $package->tracking_number,
                'weight' => $package->weight,
                'weight_unit' => $package->weight_unit,
                'rate' => $package->rate,
                'warehouse_fee' => $package->warehouse_fee,
                'service_fee' => $package->service_fee,
                'freight_rate' => $package->freight_rate,
                'freight_weight' => $package->freight_weight,
                'freight_unit' => $package->freight_unit,
                'freight_charge' => $package->freight_charge,
                'amount_usd' => $package->amount_usd,
                'exchange_rate_jmd' => $package->exchange_rate_jmd,
                'amount' => $package->amount,
            ]);
        }
        $this->recalculateInvoices([$invoice->id]);

        return back()->with('success', 'Added '.$packages->count().' package(s) to invoice.');
    }

    public function reassignPackage(Request $request, SuperadminLogisticsManifest $manifest, SuperadminLogisticsPackage $package)
    {
        $this->ensurePackageBelongsToManifest($manifest, $package);
        $this->ensurePackageIsEditable($package);
        $data = $request->validate(['tenant_id' => 'required|exists:tenants,id']);
        $tenantId = (int) $data['tenant_id'];
        $package->update([
            'tenant_id' => $tenantId,
            'courier_package_id' => null,
            'location_status' => null,
            'location_notes' => null,
        ]);
        $manifest->update(['tenant_id' => $tenantId]);

        return back()->with('success', 'Package reassigned to courier.');
    }

    public function movePackageToUnknownRoom(Request $request, SuperadminLogisticsManifest $manifest, SuperadminLogisticsPackage $package)
    {
        $this->ensurePackageBelongsToManifest($manifest, $package);
        $package->loadMissing('courierPackage.invoiceItems.invoice');

        DB::transaction(function () use ($request, $package) {
            $courierPackage = $package->courierPackage;

            if ($courierPackage) {
                $hasActiveInvoice = $courierPackage->invoiceItems
                    ->contains(fn ($item) => $item->invoice
                        && $item->invoice->voided_at === null
                        && $item->invoice->invoice_status !== 'cancelled'
                        && $item->invoice->payment_status !== 'cancelled');

                if ($hasActiveInvoice || $courierPackage->date_delivered) {
                    throw ValidationException::withMessages([
                        'package' => 'This package already has courier billing or delivery history and cannot be moved back to Unknown Room automatically.',
                    ]);
                }

                $courierTracking = $courierPackage->tracking_number;
                $courierPackage->delete();

                ActivityLog::record('deleted', 'packages', 'Deleted courier package '.$courierTracking.' while moving logistics package '.$package->tracking_number.' to Unknown Room', [
                    'package_id' => $courierPackage->id,
                    'logistics_package_id' => $package->id,
                ]);
            }

            $package->update([
                'courier_package_id' => null,
                'location_status' => 'inhouse_unknown_room',
                'location_notes' => $request->input('notes') ?: 'Moved back to Unknown Room because courier ownership could not be confirmed.',
                'checked_out_at' => null,
                'checked_out_by' => null,
            ]);
        });

        return back()->with('success', 'Package moved to InHouse Unknown Room.');
    }

    public function uploadInvoiceReceipt(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is voided and permanently locked.');
        $data = $request->validate([
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096',
        ]);

        $file = $data['receipt'];
        $invoice->update([
            'receipt_path' => $file->store('logistics-invoice-receipts', 'public'),
            'receipt_original_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', 'Payment receipt uploaded.');
    }

    public function voidInvoice(SuperadminLogisticsInvoice $invoice)
    {
        abort_if($invoice->isLocked(), 422, 'This invoice is already voided and locked.');
        $invoice->update([
            'status' => 'void',
            'balance_jmd' => 0,
            'voided_at' => now(),
            'voided_by' => auth()->id(),
        ]);
        app(FinanceEntryService::class)->clearLogisticsInvoiceExpenses($invoice->fresh());

        return back()->with('success', 'Logistics invoice voided and permanently locked.');
    }

    public function sendInvoiceEmail(TenantMailService $mail, SuperadminLogisticsInvoice $invoice)
    {
        $invoice->load(['tenant', 'manifest']);
        $email = optional($invoice->tenant)->email;
        abort_if(blank($email), 422, 'Courier does not have a billing email.');

        $mail->send($invoice->tenant_id, $email, new TenantTemplateMail('Logistics invoice '.$invoice->invoice_number, [
            'tenant' => $invoice->tenant,
            'subject' => 'Logistics invoice '.$invoice->invoice_number,
            'headline' => 'Your shipment is ready for pickup or delivery',
            'bodyHtml' => '<p>A logistics invoice has been generated for your shipment. Please review the invoice and submit payment or proof of payment from your courier portal.</p>',
            'details' => [
                'Invoice Number' => $invoice->invoice_number,
                'Manifest' => optional($invoice->manifest)->manifest_number,
                'Total' => 'JMD '.number_format((float) $invoice->total, 2),
                'Balance' => 'JMD '.number_format((float) $invoice->balance_jmd, 2),
                'Status' => ucfirst($invoice->status),
            ],
            'buttonLabel' => 'Open Courier Portal',
            'buttonUrl' => optional($invoice->tenant)->portalUrl(),
            'attachmentUrl' => null,
            'footerHtml' => null,
            'footerText' => null,
        ]));

        $this->notifyCourier($invoice->tenant_id, 'Logistics invoice emailed', 'Invoice '.$invoice->invoice_number.' was emailed to your courier account.');

        return back()->with('success', 'Invoice email sent to '.$email.'.');
    }

    public function checkOut(Request $request, SuperadminLogisticsManifest $manifest)
    {
        $packages = $this->packagesFromScan($request, $manifest);
        $paidPackageIds = SuperadminLogisticsInvoice::where('manifest_id', $manifest->id)
            ->where('status', 'paid')
            ->with('items')
            ->get()
            ->flatMap(fn ($invoice) => $invoice->items->pluck('package_id'))
            ->unique();

        $unpaid = $packages->whereNotIn('id', $paidPackageIds);
        if ($unpaid->isNotEmpty()) {
            throw ValidationException::withMessages(['tracking_numbers' => 'Only paid packages can be checked out. Unpaid: '.$unpaid->pluck('tracking_number')->implode(', ')]);
        }

        foreach ($packages as $package) {
            $package->update(['checked_out_at' => now(), 'checked_out_by' => auth()->id()]);
        }

        if ($manifest->packages()->whereNull('checked_out_at')->doesntExist()) {
            $manifest->update(['status' => 'checked_out']);
        }
        $this->notifyCourier($manifest->tenant_id, 'Logistics packages checked out', $packages->count().' paid package(s) were checked out from manifest '.$manifest->manifest_number.'.');

        return back()->with('success', 'Checked out '.$packages->count().' paid package(s).');
    }

    public function storeRate(Request $request)
    {
        $data = $this->validateRate($request);

        SuperadminLogisticsRate::create($data + ['is_active' => $request->boolean('is_active', true)]);
        $updated = $this->refreshOpenLogisticsPackageCharges();

        return back()->with('success', 'Logistics rate saved. Refreshed '.$updated.' open package rate'.($updated === 1 ? '' : 's').'.');
    }

    public function updateRate(Request $request, SuperadminLogisticsRate $rate)
    {
        $data = $this->validateRate($request);

        $rate->update($data + ['is_active' => $request->boolean('is_active')]);
        $updated = $this->refreshOpenLogisticsPackageCharges();

        return back()->with('success', 'Logistics rate updated. Refreshed '.$updated.' open package rate'.($updated === 1 ? '' : 's').'.');
    }

    public function updatePricing(Request $request)
    {
        $data = $request->validate([
            'service_fee_type' => 'required|in:flat,percentage',
            'service_fee_value' => 'required|numeric|min:0',
            'freight_rate_lb' => 'nullable|numeric|min:0',
            'freight_rate_kg' => 'nullable|numeric|min:0',
            'exchange_rate_jmd' => 'nullable|numeric|min:0',
        ]);
        $serviceFeeValue = (float) $data['service_fee_value'];

        foreach ([
            'logistics_service_fee_type' => $data['service_fee_type'],
            'logistics_service_fee_flat_amount' => $data['service_fee_type'] === 'flat' ? $serviceFeeValue : 0,
            'logistics_service_fee_percentage' => $data['service_fee_type'] === 'percentage' ? $serviceFeeValue : 0,
            'logistics_freight_rate_lb' => $data['freight_rate_lb'] ?? 0,
            'logistics_freight_rate_kg' => $data['freight_rate_kg'] ?? 0,
            'logistics_exchange_rate_jmd' => $data['exchange_rate_jmd'] ?? 0,
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'group' => 'logistics']);
        }

        return back()->with('success', 'Logistics pricing settings updated.');
    }

    public function destroyRate(SuperadminLogisticsRate $rate)
    {
        $rate->delete();
        $updated = $this->refreshOpenLogisticsPackageCharges();

        return back()->with('success', 'Logistics rate deleted. Refreshed '.$updated.' open package rate'.($updated === 1 ? '' : 's').'.');
    }

    private function manifestData(Request $request, ?SuperadminLogisticsManifest $manifest = null): array
    {
        return $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'manifest_number' => 'nullable|string|max:100|unique:superadmin_logistics_manifests,manifest_number,'.optional($manifest)->id,
            'warehouse_reference' => 'nullable|string|max:150',
            'cargo_company' => 'nullable|string|max:150',
            'received_at' => 'nullable|date',
            'require_checkout_scan' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
    }

    private function validateRate(Request $request): array
    {
        $data = $request->validate([
            'shipment_type' => 'required|in:air,sea',
            'region' => 'nullable|string|max:40',
            'min_weight' => 'required|numeric|min:0',
            'max_weight' => 'nullable|numeric|gt:min_weight',
            'minimum_billable_measure' => 'nullable|numeric|min:0',
            'weight_unit' => 'required|in:lb,kg,cuft,cbm',
            'rate_per_unit' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['region'] = filled($data['region'] ?? null) ? $this->normalizeRegion($data['region']) : null;
        if ($data['shipment_type'] === 'air' && ! in_array($data['weight_unit'], ['lb', 'kg'], true)) {
            $data['weight_unit'] = 'lb';
        }
        if ($data['shipment_type'] === 'sea' && ! in_array($data['weight_unit'], ['cuft', 'cbm'], true)) {
            $data['weight_unit'] = 'cuft';
        }
        $data['minimum_billable_measure'] = $data['minimum_billable_measure'] ?? ($data['shipment_type'] === 'sea' ? $data['min_weight'] : 0);

        return $data;
    }

    private function refreshOpenLogisticsPackageCharges(): int
    {
        $updated = 0;

        SuperadminLogisticsPackage::whereDoesntHave('invoiceItems')
            ->whereNull('checked_out_at')
            ->chunkById(100, function ($packages) use (&$updated) {
                foreach ($packages as $package) {
                    $charges = $this->chargesForPackage((float) $package->weight, (string) $package->weight_unit, $package->shipment?->shipment_type ?? 'air', $package->shipment?->region ?? 'USA');
                    $package->update($charges);
                    $updated++;
                }
            });

        return $updated;
    }

    private function refreshShipmentOpenPackageCharges(SuperadminLogisticsShipment $shipment): int
    {
        $updated = 0;

        $shipment->packages()
            ->whereDoesntHave('invoiceItems')
            ->whereNull('checked_out_at')
            ->chunkById(100, function ($packages) use (&$updated, $shipment) {
                foreach ($packages as $package) {
                    $charges = $this->chargesForPackage((float) $package->weight, (string) $package->weight_unit, $shipment->shipment_type, $shipment->region ?? 'USA');
                    $package->update($charges);
                    $updated++;
                }
            });

        return $updated;
    }

    private function refreshManifestOpenPackageCharges(SuperadminLogisticsManifest $manifest, bool $onlyMissingWarehouseFee = false): int
    {
        $updated = 0;

        $manifest->packages()
            ->whereDoesntHave('invoiceItems')
            ->whereNull('checked_out_at')
            ->when($onlyMissingWarehouseFee, function ($query) {
                $query->where(function ($nested) {
                    $nested->whereNull('warehouse_fee')
                        ->orWhere('warehouse_fee', '<=', 0)
                        ->orWhereNull('rate')
                        ->orWhere('rate', '<=', 0);
                });
            })
            ->chunkById(100, function ($packages) use (&$updated) {
                foreach ($packages as $package) {
                    $charges = $this->chargesForPackage((float) $package->weight, (string) $package->weight_unit, $package->shipment?->shipment_type ?? 'air', $package->shipment?->region ?? 'USA');
                    if ((float) $charges['warehouse_fee'] <= 0) {
                        continue;
                    }

                    $package->update($charges);
                    $updated++;
                }
            });

        return $updated;
    }

    private function notifyCourier(int $tenantId, string $title, string $message): void
    {
        SystemNotification::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'message' => $message,
            'priority' => 'medium',
            'audience' => 'courier',
            'starts_at' => now(),
            'is_active' => true,
        ]);
    }

    private function emailCourierManifestIssued(SuperadminLogisticsManifest $manifest): void
    {
        $manifest->loadMissing('tenant');
        $email = optional($manifest->tenant)->email;
        if (blank($email)) {
            return;
        }

        try {
            app(TenantMailService::class)->send($manifest->tenant_id, $email, new TenantTemplateMail('Logistics manifest '.$manifest->manifest_number.' issued', [
                'tenant' => $manifest->tenant,
                'subject' => 'Logistics manifest '.$manifest->manifest_number.' issued',
                'headline' => 'A logistics manifest has been issued',
                'bodyHtml' => '<p>A new logistics manifest has been created for your courier account. Please review the manifest details from your courier portal.</p>',
                'details' => [
                    'Manifest Number' => $manifest->manifest_number,
                    'Warehouse Reference' => $manifest->warehouse_reference ?: '-',
                    'Cargo Company' => $manifest->cargo_company ?: '-',
                    'Received At' => optional($manifest->received_at)->format('M d, Y h:i A') ?: '-',
                    'Status' => ucfirst(str_replace('_', ' ', $manifest->status)),
                ],
                'buttonLabel' => 'Open Courier Portal',
                'buttonUrl' => optional($manifest->tenant)->portalUrl(),
                'attachmentUrl' => null,
                'footerHtml' => null,
                'footerText' => null,
            ]));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function inlinePublicFile(?string $path, ?string $filename = null)
    {
        abort_if(! $path || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($filename ?: basename($path)).'"',
        ]);
    }

    private function packagesFromScan(Request $request, SuperadminLogisticsManifest $manifest)
    {
        $data = $request->validate(['tracking_numbers' => 'required|string']);
        $rawTracking = collect(preg_split('/[\r\n,]+/', $data['tracking_numbers']))
            ->map(fn ($value) => $this->normalizeTrackingNumber((string) $value))
            ->filter();
        $duplicates = $rawTracking->duplicates()->unique()->values();
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['tracking_numbers' => 'Duplicated scan rejected: '.$duplicates->implode(', ')]);
        }

        $tracking = $rawTracking
            ->unique()
            ->values();

        if (! $manifest->require_checkout_scan && $tracking->isEmpty()) {
            return $manifest->packages()->get();
        }

        $matchedCourierPackages = Package::where('tenant_id', $manifest->tenant_id)
            ->where(function ($query) use ($tracking) {
                $query->whereIn('tracking_number', $tracking)
                    ->orWhereIn('supplier_tracking', $tracking);
            })
            ->get(['id', 'tracking_number', 'supplier_tracking']);
        $courierPackageIds = $matchedCourierPackages->pluck('id');
        $trackingAliases = $tracking
            ->merge($matchedCourierPackages->pluck('tracking_number')->map(fn ($value) => $this->normalizeTrackingNumber((string) $value)))
            ->merge($matchedCourierPackages->pluck('supplier_tracking')->map(fn ($value) => $this->normalizeTrackingNumber((string) $value)))
            ->filter()
            ->unique()
            ->values();

        $packages = $manifest->packages()
            ->where(function ($query) use ($tracking, $trackingAliases, $courierPackageIds) {
                $query->whereIn('tracking_number', $trackingAliases)
                    ->when($courierPackageIds->isNotEmpty(), fn ($nested) => $nested->orWhereIn('courier_package_id', $courierPackageIds))
                    ->orWhereHas('courierPackage', function ($packageQuery) use ($tracking) {
                        $packageQuery->whereIn('tracking_number', $tracking)
                            ->orWhereIn('supplier_tracking', $tracking);
                    });
            })
            ->with('courierPackage')
            ->get();
        $matched = $packages->flatMap(function ($package) use ($matchedCourierPackages) {
            $aliases = collect([
                $this->normalizeTrackingNumber((string) $package->tracking_number),
                $this->normalizeTrackingNumber((string) optional($package->courierPackage)->tracking_number),
                $this->normalizeTrackingNumber((string) optional($package->courierPackage)->supplier_tracking),
            ]);

            $relatedCourierPackage = $matchedCourierPackages->first(function ($courierPackage) use ($package) {
                return (int) $package->courier_package_id === (int) $courierPackage->id
                    || $this->normalizeTrackingNumber((string) $package->tracking_number) === $this->normalizeTrackingNumber((string) $courierPackage->supplier_tracking);
            });

            if ($relatedCourierPackage) {
                $aliases = $aliases
                    ->push($this->normalizeTrackingNumber((string) $relatedCourierPackage->tracking_number))
                    ->push($this->normalizeTrackingNumber((string) $relatedCourierPackage->supplier_tracking));
            }

            return $aliases->filter()->all();
        })->unique()->values();
        $missing = $tracking->diff($matched)->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['tracking_numbers' => 'Tracking number(s) not found on this manifest: '.$missing->implode(', ')]);
        }

        return $packages;
    }

    private function rateFor(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): float
    {
        $match = $this->rateMatchFor($weight, $unit, $shipmentType, $region);

        return $match ? round((float) $match['rate']->rate_per_unit, 2) : 0.0;
    }

    private function rateMatchFor(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): ?array
    {
        $shipmentType = $shipmentType === 'sea' ? 'sea' : 'air';
        $unit = $this->normalizeMeasureUnit($unit, $shipmentType);
        $rate = $this->rateForUnit($weight, $unit, $shipmentType, $region);
        if ($rate) {
            return ['rate' => $rate, 'measure' => $weight, 'unit' => $unit];
        }

        $convertedWeight = $shipmentType === 'sea'
            ? ($unit === 'cbm' ? $weight * 35.3147 : $weight / 35.3147)
            : ($unit === 'kg' ? $weight * 2.20462 : $weight / 2.20462);
        $convertedUnit = $shipmentType === 'sea'
            ? ($unit === 'cbm' ? 'cuft' : 'cbm')
            : ($unit === 'kg' ? 'lb' : 'kg');

        $convertedRate = $this->rateForUnit($convertedWeight, $convertedUnit, $shipmentType, $region);

        return $convertedRate ? ['rate' => $convertedRate, 'measure' => $convertedWeight, 'unit' => $convertedUnit] : null;
    }

    private function rateForUnit(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): ?SuperadminLogisticsRate
    {
        $region = strtolower($this->normalizeRegion($region));
        $rate = SuperadminLogisticsRate::where('is_active', true)
            ->where('shipment_type', $shipmentType)
            ->where('weight_unit', $unit)
            ->where(function ($query) use ($region) {
                $query->whereNull('region')->orWhere('region', '')->orWhereRaw('LOWER(region) = ?', [$region]);
            })
            ->where('min_weight', '<=', $weight)
            ->where(fn ($query) => $query->whereNull('max_weight')->orWhere('max_weight', '>=', $weight))
            ->orderByRaw("CASE WHEN LOWER(COALESCE(region, '')) = ? THEN 0 WHEN region IS NULL OR region = '' THEN 1 ELSE 2 END", [$region])
            ->orderByDesc('min_weight')
            ->first();

        if (! $rate) {
            $rate = SuperadminLogisticsRate::where('is_active', true)
                ->where('shipment_type', $shipmentType)
                ->where('weight_unit', $unit)
                ->where(function ($query) use ($region) {
                    $query->whereNull('region')->orWhere('region', '')->orWhereRaw('LOWER(region) = ?', [$region]);
                })
                ->where('min_weight', '>', $weight)
                ->orderByRaw("CASE WHEN LOWER(COALESCE(region, '')) = ? THEN 0 WHEN region IS NULL OR region = '' THEN 1 ELSE 2 END", [$region])
                ->orderBy('min_weight')
                ->first();
        }

        return $rate;
    }

    private function chargesForPackage(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): array
    {
        $shipmentType = $shipmentType === 'sea' ? 'sea' : 'air';
        $unit = $this->normalizeMeasureUnit($unit, $shipmentType);
        $rateMatch = $this->rateMatchFor($weight, $unit, $shipmentType, $region);
        $rateUsd = $rateMatch ? round((float) $rateMatch['rate']->rate_per_unit, 2) : 0.0;
        $billableWarehouseMeasure = $shipmentType === 'sea' && $rateMatch
            ? max((float) $rateMatch['measure'], $rateMatch['rate']->minimumBillableMeasure())
            : $weight;
        $warehouseFeeUsd = $shipmentType === 'sea' ? round($billableWarehouseMeasure * $rateUsd, 2) : $rateUsd;
        $pricing = $this->pricingSettings();
        $serviceFeeType = $pricing['service_fee_type'];
        $serviceFeeValue = (float) $pricing['service_fee_value'];
        $serviceFee = $serviceFeeType === 'percentage'
            ? round($warehouseFeeUsd * ($serviceFeeValue / 100), 2)
            : round($serviceFeeValue, 2);
        [$freightWeight, $freightUnit, $freightRate] = $this->freightBasis($weight, $unit, $pricing);
        $freightCharge = round($freightWeight * $freightRate, 2);
        $amountUsd = round($warehouseFeeUsd + $serviceFee + $freightCharge, 2);
        $exchangeRate = $this->usdToJmdRate();

        return [
            'rate' => $rateUsd,
            'warehouse_fee' => $warehouseFeeUsd,
            'service_fee_type' => $serviceFeeType,
            'service_fee_value' => $serviceFeeValue,
            'service_fee' => $serviceFee,
            'freight_rate' => $freightRate,
            'freight_weight' => $freightWeight,
            'freight_unit' => $freightUnit,
            'freight_charge' => $freightCharge,
            'amount_usd' => $amountUsd,
            'exchange_rate_jmd' => $exchangeRate,
            'amount' => round($amountUsd * $exchangeRate, 2),
        ];
    }

    private function applyPackageChargeOverrides(array $charges, array $data): array
    {
        $warehouseFee = array_key_exists('warehouse_fee', $data) && $data['warehouse_fee'] !== null && $data['warehouse_fee'] !== ''
            ? round((float) $data['warehouse_fee'], 2)
            : (float) $charges['warehouse_fee'];
        $serviceFee = array_key_exists('service_fee', $data) && $data['service_fee'] !== null && $data['service_fee'] !== ''
            ? round((float) $data['service_fee'], 2)
            : (float) $charges['service_fee'];
        $freightWeight = array_key_exists('freight_weight', $data) && $data['freight_weight'] !== null && $data['freight_weight'] !== ''
            ? round((float) $data['freight_weight'], 4)
            : (float) $charges['freight_weight'];
        $freightUnit = $data['freight_unit'] ?? $charges['freight_unit'];
        $freightRate = array_key_exists('freight_rate', $data) && $data['freight_rate'] !== null && $data['freight_rate'] !== ''
            ? round((float) $data['freight_rate'], 2)
            : (float) $charges['freight_rate'];
        $freightCharge = array_key_exists('freight_charge', $data) && $data['freight_charge'] !== null && $data['freight_charge'] !== ''
            ? round((float) $data['freight_charge'], 2)
            : round($freightWeight * $freightRate, 2);
        $exchangeRate = array_key_exists('exchange_rate_jmd', $data) && $data['exchange_rate_jmd'] !== null && $data['exchange_rate_jmd'] !== ''
            ? round((float) $data['exchange_rate_jmd'], 4)
            : (float) $charges['exchange_rate_jmd'];
        $amountUsd = round($warehouseFee + $serviceFee + $freightCharge, 2);

        return array_merge($charges, [
            'rate' => $warehouseFee,
            'warehouse_fee' => $warehouseFee,
            'service_fee_type' => 'manual',
            'service_fee_value' => $serviceFee,
            'service_fee' => $serviceFee,
            'freight_rate' => $freightRate,
            'freight_weight' => $freightWeight,
            'freight_unit' => $freightUnit,
            'freight_charge' => $freightCharge,
            'amount_usd' => $amountUsd,
            'exchange_rate_jmd' => $exchangeRate,
            'amount' => round($amountUsd * $exchangeRate, 2),
        ]);
    }

    private function normalizeMeasureUnit(string $unit, string $shipmentType): string
    {
        $unit = strtolower(trim($unit));
        if ($shipmentType === 'sea') {
            return in_array($unit, ['cbm', 'cuft'], true) ? $unit : 'cuft';
        }

        return $unit === 'kg' ? 'kg' : 'lb';
    }

    private function normalizeRegion(?string $region): string
    {
        $value = trim((string) $region);
        if ($value === '') {
            return 'USA';
        }

        $key = strtolower($value);
        return in_array($key, ['cn', 'china'], true) ? 'China' : (in_array($key, ['us', 'usa'], true) ? 'USA' : $value);
    }

    private function formatPackageMeasure(SuperadminLogisticsPackage $package): string
    {
        $measure = (float) $package->weight;
        if ($package->weight_unit === 'cbm') {
            return number_format($measure * 35.3147, 2).' cu.ft / '.number_format($measure, 3).' CBM';
        }
        if ($package->weight_unit === 'cuft') {
            return number_format($measure, 2).' cu.ft / '.number_format($measure / 35.3147, 3).' CBM';
        }

        $lb = $package->weight_unit === 'kg' ? $measure * 2.20462262 : $measure;
        $kg = $package->weight_unit === 'lb' ? $measure * 0.45359237 : $measure;

        return number_format($lb, 2).' lb / '.number_format($kg, 2).' kg';
    }

    private function freightBasis(float $weight, string $unit, array $pricing): array
    {
        if (in_array($unit, ['cuft', 'cbm'], true)) {
            return [$weight, $unit, 0.0];
        }

        if ((float) $pricing['freight_rate_kg'] > 0) {
            $freightWeight = $unit === 'kg' ? $weight : round($weight * 0.45359237, 4);

            return [$freightWeight, 'kg', (float) $pricing['freight_rate_kg']];
        }

        $matchingRate = (float) ($unit === 'kg' ? $pricing['freight_rate_kg'] : $pricing['freight_rate_lb']);
        if ($matchingRate > 0) {
            return [$weight, $unit, $matchingRate];
        }

        if ($unit === 'lb' && (float) $pricing['freight_rate_kg'] > 0) {
            return [round($weight * 0.45359237, 4), 'kg', (float) $pricing['freight_rate_kg']];
        }

        if ($unit === 'kg' && (float) $pricing['freight_rate_lb'] > 0) {
            return [round($weight * 2.20462262, 4), 'lb', (float) $pricing['freight_rate_lb']];
        }

        return [$weight, $unit, 0.0];
    }

    private function pricingSettings(): array
    {
        $settings = SystemSetting::whereIn('key', [
            'logistics_service_fee_type',
            'logistics_service_fee_flat_amount',
            'logistics_service_fee_percentage',
            'logistics_freight_rate_lb',
            'logistics_freight_rate_kg',
            'logistics_exchange_rate_jmd',
            'logistics_cached_exchange_rate_jmd',
            'logistics_cached_exchange_rate_date',
        ])->pluck('value', 'key');

        return [
            'service_fee_type' => in_array($settings['logistics_service_fee_type'] ?? 'flat', ['flat', 'percentage'], true) ? $settings['logistics_service_fee_type'] : 'flat',
            'service_fee_flat_amount' => (float) ($settings['logistics_service_fee_flat_amount'] ?? 0),
            'service_fee_percentage' => (float) ($settings['logistics_service_fee_percentage'] ?? 0),
            'service_fee_value' => ($settings['logistics_service_fee_type'] ?? 'flat') === 'percentage'
                ? (float) ($settings['logistics_service_fee_percentage'] ?? 0)
                : (float) ($settings['logistics_service_fee_flat_amount'] ?? 0),
            'freight_rate_lb' => (float) ($settings['logistics_freight_rate_lb'] ?? 0),
            'freight_rate_kg' => (float) ($settings['logistics_freight_rate_kg'] ?? 0),
            'exchange_rate_jmd' => (float) ($settings['logistics_exchange_rate_jmd'] ?? 0),
            'cached_exchange_rate_jmd' => (float) ($settings['logistics_cached_exchange_rate_jmd'] ?? 0),
            'cached_exchange_rate_date' => (string) ($settings['logistics_cached_exchange_rate_date'] ?? ''),
        ];
    }

    private function usdToJmdRate(): float
    {
        $pricing = $this->pricingSettings();
        if ((float) $pricing['exchange_rate_jmd'] > 0) {
            return round((float) $pricing['exchange_rate_jmd'], 4);
        }

        $today = now()->toDateString();
        if ($pricing['cached_exchange_rate_date'] === $today && (float) $pricing['cached_exchange_rate_jmd'] > 0) {
            return round((float) $pricing['cached_exchange_rate_jmd'], 4);
        }

        try {
            $response = Http::timeout(6)->acceptJson()->get('https://open.er-api.com/v6/latest/USD');
            $rate = (float) data_get($response->json(), 'rates.JMD', 0);
            if ($response->successful() && $rate > 0) {
                SystemSetting::updateOrCreate(['key' => 'logistics_cached_exchange_rate_jmd'], ['value' => (string) $rate, 'group' => 'logistics']);
                SystemSetting::updateOrCreate(['key' => 'logistics_cached_exchange_rate_date'], ['value' => $today, 'group' => 'logistics']);

                return round($rate, 4);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ((float) $pricing['cached_exchange_rate_jmd'] > 0) {
            return round((float) $pricing['cached_exchange_rate_jmd'], 4);
        }

        throw ValidationException::withMessages([
            'exchange_rate_jmd' => 'No USD to JMD exchange rate is configured and the daily online FX rate could not be fetched. Set an exchange rate in Logistics Pricing.',
        ]);
    }

    private function packageRowsFromText(string $text, string $shipmentType = 'air')
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(function ($line) use ($shipmentType) {
                $parts = array_map('trim', explode(',', $line));
                $tracking = $this->normalizeTrackingNumber($parts[0] ?? '');

                if ($tracking === '') {
                    return null;
                }

                return [
                    'tracking_number' => $tracking,
                    'weight' => (float) ($parts[1] ?? 0),
                    'weight_unit' => $this->normalizeMeasureUnit((string) ($parts[2] ?? ''), $shipmentType),
                    'description' => $parts[3] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    private function bulkPackageRowsFromCsv(string $path)
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $firstRow = fgetcsv($handle);
        if ($firstRow === false) {
            fclose($handle);

            return collect();
        }

        $header = array_map(fn ($column) => $this->normalizeBulkHeader($column), $firstRow);
        $defaultHeader = ['mailbox', 'tracking_number', 'weight', 'weight_unit', 'description', 'courier', 'merchant', 'freight_type', 'delivery_type', 'date_received', 'notes'];
        $hasHeader = collect($header)->intersect(['mailbox', 'mailbox_number', 'tracking_number', 'supplier_tracking', 'weight', 'weight_lb', 'weight_kg'])->isNotEmpty();
        $rows = collect();
        $line = 1;

        if (! $hasHeader) {
            $rows->push($this->normalizeBulkPackageRow($this->combineCsvRow($defaultHeader, $firstRow), $line));
            $header = $defaultHeader;
        }

        while (($csvRow = fgetcsv($handle)) !== false) {
            $line++;
            $rows->push($this->normalizeBulkPackageRow($this->combineCsvRow($header, $csvRow), $line));
        }

        fclose($handle);

        return $rows->filter(fn ($row) => trim((string) ($row['mailbox'] ?? '')) !== ''
            || trim((string) ($row['tracking_number'] ?? '')) !== ''
            || $row['weight'] !== null
            || trim((string) ($row['description'] ?? '')) !== ''
            || trim((string) ($row['courier'] ?? '')) !== ''
            || trim((string) ($row['merchant'] ?? '')) !== ''
            || trim((string) ($row['notes'] ?? '')) !== '')
            ->values();
    }

    private function normalizeBulkPackageRow(array $data, int $line): array
    {
        $weightUnit = strtolower((string) $this->bulkValue($data, ['weight_unit', 'unit'], 'lb'));
        $weight = $this->decimalOrNull($this->bulkValue($data, ['weight']));

        if ($weight === null && $this->bulkValue($data, ['weight_lb', 'lbs', 'pounds']) !== null) {
            $weight = $this->decimalOrNull($this->bulkValue($data, ['weight_lb', 'lbs', 'pounds']));
            $weightUnit = 'lb';
        }

        if ($weight === null && $this->bulkValue($data, ['weight_kg', 'kgs', 'kilograms']) !== null) {
            $weight = $this->decimalOrNull($this->bulkValue($data, ['weight_kg', 'kgs', 'kilograms']));
            $weightUnit = 'kg';
        }

        return [
            'row_number' => $line,
            'mailbox' => Customer::normalizeMailbox($this->bulkValue($data, ['mailbox', 'mailbox_number', 'mail_box', 'customer_mailbox', 'account_number'])) ?? '',
            'tracking_number' => $this->bulkValue($data, ['tracking_number', 'tracking', 'tracking_no', 'tracking_num', 'supplier_tracking', 'supplier_tracking_number', 'package_tracking', 'awb', 'airway_bill']),
            'weight' => $weight,
            'weight_unit' => in_array($weightUnit, ['kg', 'lb', 'cuft', 'cbm'], true) ? $weightUnit : 'lb',
            'description' => trim((string) $this->bulkValue($data, ['description', 'item_description', 'contents'])),
            'courier' => trim((string) $this->bulkValue($data, ['courier', 'carrier', 'shipper'])),
            'merchant' => trim((string) $this->bulkValue($data, ['merchant', 'vendor', 'store'])),
            'freight_type' => trim((string) $this->bulkValue($data, ['freight_type', 'shipping_type'], 'sendx')),
            'delivery_type' => trim((string) $this->bulkValue($data, ['delivery_type'], 'pickup')),
            'status' => trim((string) $this->bulkValue($data, ['status', 'package_status'])),
            'date_received' => $this->dateOrNull($this->bulkValue($data, ['date_received', 'received_at'])),
            'price_usd' => $this->decimalOrNull($this->bulkValue($data, ['price_usd', 'declared_value_usd'])),
            'price_jmd' => $this->decimalOrNull($this->bulkValue($data, ['price_jmd', 'declared_value_jmd'])),
            'notes' => trim((string) $this->bulkValue($data, ['notes', 'note'])),
        ];
    }

    private function normalizeBulkHeader($column): string
    {
        return Str::snake(trim((string) $column));
    }

    private function combineCsvRow(array $header, array $row): array
    {
        $row = array_slice(array_pad($row, count($header), null), 0, count($header));

        return array_combine($header, $row) ?: [];
    }

    private function bulkValue(array $data, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
                return $data[$key];
            }
        }

        return $default;
    }

    private function decimalOrNull($value): ?float
    {
        $value = trim(str_replace(['US$', 'JMD', ',', '$'], '', (string) $value));

        return is_numeric($value) ? (float) $value : null;
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function tenantForUnknownBulkRow(array $row, ?int $fallbackTenantId = null): ?Tenant
    {
        if ($fallbackTenantId) {
            $tenant = Tenant::whereKey($fallbackTenantId)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        $candidates = collect([
            $row['tenant_id'] ?? null,
            $row['courier_id'] ?? null,
        ])->map(fn ($value) => (int) $value)->filter()->unique();

        foreach ($candidates as $tenantId) {
            $tenant = Tenant::whereKey($tenantId)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        $terms = collect([
            $row['tenant'] ?? null,
            $row['courier'] ?? null,
            $row['company'] ?? null,
            $row['company_name'] ?? null,
            $row['subdomain'] ?? null,
        ])->map(fn ($value) => trim((string) $value))->filter()->unique()->values();

        foreach ($terms as $term) {
            $tenant = Tenant::where('company_name', $term)
                ->orWhere('subdomain', $term)
                ->orWhere('custom_domain', $term)
                ->first();

            if (! $tenant) {
                $tenant = Tenant::where('company_name', 'like', '%'.$term.'%')->first();
            }

            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }

    private function tenantForBulkMailboxPrefix(?string $mailbox): ?Tenant
    {
        $normalized = Customer::normalizeMailbox($mailbox);
        if (! $normalized) {
            return null;
        }

        $mailboxCore = preg_replace('/^OL-?/i', '', $normalized);
        if ($mailboxCore === '') {
            return null;
        }

        $settings = TenantSetting::whereIn('key', ['courier_code', 'hawb_prefix', 'mailbox_prefix'])
            ->whereNotNull('value')
            ->get(['tenant_id', 'value'])
            ->map(function ($setting) use ($normalized, $mailboxCore) {
                $prefix = Customer::normalizeMailbox($setting->value);
                $prefixCore = $prefix ? preg_replace('/^OL-?/i', '', $prefix) : '';

                return [
                    'tenant_id' => (int) $setting->tenant_id,
                    'prefix' => $prefix,
                    'prefix_core' => $prefixCore,
                    'score' => strlen((string) $prefixCore),
                    'matches' => $prefix
                        && strlen((string) $prefixCore) >= 2
                        && (
                            $normalized === $prefix
                            || $mailboxCore === $prefixCore
                            || Str::startsWith($mailboxCore, $prefixCore)
                        ),
                ];
            })
            ->filter(fn ($candidate) => $candidate['matches'])
            ->sortByDesc('score')
            ->values();

        $tenantId = $settings->first()['tenant_id'] ?? null;

        return $tenantId ? Tenant::whereKey($tenantId)->first() : null;
    }
    private function bulkSkip(array $row, string $reason): array
    {
        return [
            'row' => $row['row_number'] ?? null,
            'mailbox' => $row['mailbox'] ?? '',
            'tracking_number' => $row['tracking_number'] ?? '',
            'reason' => $reason,
        ];
    }

    private function bulkSkipSummary($skipped): string
    {
        if ($skipped->isEmpty()) {
            return '';
        }

        return 'Skipped rows: '.$skipped->take(8)->map(fn ($row) => 'row '.$row['row'].' ('.$row['reason'].')')->implode('; ').'.';
    }

    private function bulkPackageNotes(array $row, array $charges): ?string
    {
        $notes = trim((string) ($row['notes'] ?? ''));
        $systemNote = 'Packages uploaded. Logistics total: JMD '.number_format((float) ($charges['amount'] ?? 0), 2).'.';

        return trim($notes."\n".$systemNote);
    }

    private function normalizeTrackingNumber(string $tracking): string
    {
        return strtoupper(trim($tracking));
    }

    private function statusIdForTenant(int $tenantId, ?string $status): ?int
    {
        $query = PackageStatus::where('tenant_id', $tenantId);

        if ($status) {
            $statusId = (clone $query)
                ->where(fn ($statusQuery) => $statusQuery->where('slug', Str::slug($status))->orWhere('name', $status))
                ->value('id');
            if ($statusId) {
                return $statusId;
            }
        }

        return (clone $query)->orderByDesc('is_default')->orderBy('sort_order')->value('id');
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function processingFee(int $tenantId): float
    {
        return (float) TenantSetting::getValue($tenantId, 'processing_fee_jmd', 0);
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

        $companyName = optional(Tenant::find($tenantId))->company_name ?: 'COU';
        $fallback = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $companyName), 0, 4));

        return $fallback !== '' ? $fallback : 'COU';
    }

    private function ensurePackageBelongsToManifest(SuperadminLogisticsManifest $manifest, SuperadminLogisticsPackage $package): void
    {
        abort_if((int) $package->manifest_id !== (int) $manifest->id, 404);
    }

    private function ensurePackageIsEditable(SuperadminLogisticsPackage $package): void
    {
        if ($package->checked_out_at) {
            throw ValidationException::withMessages([
                'package' => 'This package has already been checked out and can no longer be changed.',
            ]);
        }
    }

    private function ensureShipmentIntakeIsOpen(SuperadminLogisticsShipment $shipment): void
    {
        if ($shipment->status === 'completed') {
            throw ValidationException::withMessages([
                'shipment' => 'This shipment intake is closed. Reopen it before scanning or uploading more packages.',
            ]);
        }
    }

    private function recalculatePackageInvoices(SuperadminLogisticsPackage $package): void
    {
        $this->recalculateInvoices($package->invoiceItems()->pluck('invoice_id')->all());
    }

    private function recalculateInvoices(array $invoiceIds): void
    {
        SuperadminLogisticsInvoice::whereIn('id', array_unique($invoiceIds))
            ->with('items')
            ->get()
            ->each(function (SuperadminLogisticsInvoice $invoice) {
                $total = (float) $invoice->items->sum('amount');
                $discount = min((float) $invoice->discount_jmd, $total);
                $invoice->update([
                    'subtotal' => $total,
                    'total' => max(0, $total - $discount),
                ]);
                $this->syncInvoicePayments($invoice->fresh());
            });
    }

    private function applyCourierCreditToInvoice(SuperadminLogisticsInvoice $invoice): SuperadminLogisticsInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = $invoice->fresh(['tenant']);
            if (! $invoice || $invoice->isLocked() || ! $invoice->tenant) {
                return $invoice;
            }

            $balance = max(0, (float) $invoice->balance_jmd);
            $credit = max(0, (float) $invoice->tenant->credit_balance_jmd);
            $amount = min($balance, $credit);
            if ($amount <= 0.01) {
                return $invoice;
            }

            SuperadminLogisticsInvoicePayment::create([
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'payment_method' => 'courier_credit',
                'reference_number' => $invoice->invoice_number,
                'amount_jmd' => $amount,
                'source' => 'credit_wallet',
                'notes' => 'Automatic courier credit applied to logistics invoice.',
                'created_by' => auth()->id(),
            ]);

            $newCreditBalance = round($credit - $amount, 2);
            $invoice->tenant->update(['credit_balance_jmd' => $newCreditBalance]);
            TenantCreditTransaction::create([
                'tenant_id' => $invoice->tenant_id,
                'type' => 'debit',
                'amount_jmd' => -$amount,
                'balance_after_jmd' => $newCreditBalance,
                'reference_type' => SuperadminLogisticsInvoice::class,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'notes' => 'Applied to logistics invoice '.$invoice->invoice_number,
                'created_by' => auth()->id(),
            ]);

            return $this->syncInvoicePayments($invoice->fresh());
        });
    }

    private function syncInvoicePayments(SuperadminLogisticsInvoice $invoice): SuperadminLogisticsInvoice
    {
        $paid = (float) $invoice->payments()->sum('amount_jmd');
        $total = max(0, (float) $invoice->total);
        $balance = max(0, $total - $paid);
        $invoice->update([
            'amount_paid_jmd' => min($paid, $total),
            'balance_jmd' => $balance,
            'status' => $balance <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'paid_at' => $balance <= 0.01 ? ($invoice->paid_at ?: now()) : null,
            'paid_by' => $balance <= 0.01 ? ($invoice->paid_by ?: auth()->id()) : null,
        ]);

        $invoice = $invoice->fresh(['payments', 'manifest.shipment']);
        app(FinanceEntryService::class)->syncLogisticsInvoiceExpenses($invoice);

        return $invoice;
    }

    private function nextShipmentNumber(): string
    {
        return 'SHIP-'.now()->format('Ymd').'-'.str_pad((string) (SuperadminLogisticsShipment::whereDate('created_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextShipmentMasterNumber(string $type, ?string $cargoCompany = null): string
    {
        $company = Str::lower(trim((string) $cargoCompany));
        $prefix = $type === 'sea' ? 'BOL' : 'AWB';
        if ($type === 'air' && Str::contains($company, 'cal')) {
            $prefix = '106';
        } elseif ($type === 'air' && Str::contains($company, 'amerijet')) {
            $prefix = '810';
        }

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (SuperadminLogisticsShipment::where('shipment_type', $type)->whereDate('created_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextManifestNumber(): string
    {
        return 'SALM-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    private function nextInvoiceNumber(): string
    {
        return 'MANF-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    private function intakePackageIntoShipment(SuperadminLogisticsShipment $shipment, string $rawTracking, ?float $weightOverride = null, ?string $unitOverride = null): array
    {
        $tracking = $this->normalizeTrackingNumber($rawTracking);
        if ($tracking === '') {
            return $this->shipmentScanResult($rawTracking, 'Not found', 'Blank tracking number skipped.');
        }

        $existingLogisticsPackage = SuperadminLogisticsPackage::with(['tenant', 'courierPackage.customer.user'])
            ->where(function ($query) use ($tracking) {
                $query->whereRaw('UPPER(tracking_number) = ?', [$tracking])
                    ->orWhereHas('courierPackage', function ($packageQuery) use ($tracking) {
                        $packageQuery->whereRaw('UPPER(tracking_number) = ?', [$tracking])
                            ->orWhereRaw('UPPER(supplier_tracking) = ?', [$tracking]);
                    });
            })
            ->first();

        if ($existingLogisticsPackage) {
            if ((int) $existingLogisticsPackage->shipment_id === (int) $shipment->id) {
                $this->recordOriginalShipmentIntakeIfMissing($shipment, $existingLogisticsPackage, $rawTracking);

                return $this->shipmentScanResult(
                    $rawTracking,
                    'Duplicate',
                    'Tracking already exists in logistics intake for this shipment.',
                    $existingLogisticsPackage->courierPackage,
                    $existingLogisticsPackage
                );
            }

            if (! blank($existingLogisticsPackage->shipment_id)) {
                return $this->shipmentScanResult(
                    $rawTracking,
                    'Duplicate',
                    'Tracking already exists in logistics intake for another shipment.',
                    $existingLogisticsPackage->courierPackage,
                    $existingLogisticsPackage
                );
            }

            $courierPackage = $existingLogisticsPackage->courierPackage;
            if (! $courierPackage) {
                $courierMatches = Package::with(['tenant', 'customer.user', 'status'])
                    ->where(function ($query) use ($tracking) {
                        $query->whereRaw('UPPER(tracking_number) = ?', [$tracking])
                            ->orWhereRaw('UPPER(supplier_tracking) = ?', [$tracking]);
                    })
                    ->get();

                if ($courierMatches->count() > 1) {
                    return $this->shipmentScanResult($rawTracking, 'Ambiguous', 'More than one courier package matched this scan. Correct duplicate tracking numbers before intake.');
                }

                $courierPackage = $courierMatches->first();
            }

            $tenantId = (int) ($courierPackage?->tenant_id ?: $existingLogisticsPackage->tenant_id);
            if ($tenantId <= 0) {
                return $this->shipmentScanResult(
                    $rawTracking,
                    'Unassigned',
                    'Tracking exists in logistics intake but is not assigned to a courier yet.',
                    $courierPackage,
                    $existingLogisticsPackage
                );
            }

            $previousStatus = optional($courierPackage?->status)->name;
            $targetStatus = $courierPackage ? $this->receivedAtPortStatusForTenant($tenantId) : null;
            if ($courierPackage && (int) $courierPackage->status_id !== (int) $targetStatus->id) {
                PackageLog::create([
                    'tenant_id' => $courierPackage->tenant_id,
                    'package_id' => $courierPackage->id,
                    'status_id' => $targetStatus->id,
                    'note' => 'Bulk shipment intake scan: received at the port for '.$shipment->shipment_number,
                    'location' => 'Warehouse / Port',
                    'added_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                $courierPackage->forceFill(['status_id' => $targetStatus->id])->save();
                $courierPackage->setRelation('status', $targetStatus);
            }

            $weight = $weightOverride !== null ? $weightOverride : (float) ($existingLogisticsPackage->weight ?: $courierPackage?->weight ?: 0);
            $unit = $this->normalizeMeasureUnit($unitOverride ?: ($weightOverride !== null ? ($shipment->shipment_type === 'sea' ? 'cuft' : 'lb') : ($existingLogisticsPackage->weight_unit ?: 'lb')), $shipment->shipment_type);
            $charges = ((float) $existingLogisticsPackage->amount <= 0 || $weightOverride !== null || $unit !== $existingLogisticsPackage->weight_unit)
                ? $this->chargesForPackage($weight, $unit, $shipment->shipment_type, $shipment->region ?? 'USA')
                : [];

            $existingLogisticsPackage->update(array_merge([
                'shipment_id' => $shipment->id,
                'tenant_id' => $tenantId,
                'courier_package_id' => $courierPackage?->id ?: $existingLogisticsPackage->courier_package_id,
                'tracking_number' => $existingLogisticsPackage->tracking_number ?: ($courierPackage?->tracking_number ?: $tracking),
                'description' => $existingLogisticsPackage->description ?: ($courierPackage?->description ?: 'Shipment intake package'),
                'weight' => $weight,
                'weight_unit' => $unit,
                'checked_in_at' => $existingLogisticsPackage->checked_in_at ?: now(),
                'checked_in_by' => $existingLogisticsPackage->checked_in_by ?: auth()->id(),
                'location_status' => 'in_house',
                'location_notes' => trim(($existingLogisticsPackage->location_notes ?: '')."\n".'Attached to shipment '.$shipment->shipment_number.'.'),
            ], $charges));

            return $this->shipmentScanResult(
                $rawTracking,
                $courierPackage && (int) $courierPackage->status_id === (int) $targetStatus->id && $previousStatus === $targetStatus->name ? 'Already received' : 'Successful',
                'Existing logistics package attached to shipment '.$shipment->shipment_number.'.',
                $courierPackage?->fresh(['tenant', 'customer.user', 'status']),
                $existingLogisticsPackage->fresh(['tenant', 'courierPackage.customer.user']),
                $previousStatus,
                $targetStatus?->name
            );
        }

        $matches = Package::with(['tenant', 'customer.user', 'status'])
            ->where(function ($query) use ($tracking) {
                $query->whereRaw('UPPER(tracking_number) = ?', [$tracking])
                    ->orWhereRaw('UPPER(supplier_tracking) = ?', [$tracking]);
            })
            ->get();

        if ($matches->isEmpty()) {
            return $this->shipmentScanResult($rawTracking, 'Not found', 'No courier package matched this tracking or supplier tracking number.');
        }

        if ($matches->count() > 1) {
            return $this->shipmentScanResult($rawTracking, 'Ambiguous', 'More than one courier package matched this scan. Correct duplicate tracking numbers before intake.');
        }

        $courierPackage = $matches->first();
        $targetStatus = $this->receivedAtPortStatusForTenant((int) $courierPackage->tenant_id);
        $previousStatus = optional($courierPackage->status)->name;

        if ((int) $courierPackage->status_id !== (int) $targetStatus->id) {
            PackageLog::create([
                'tenant_id' => $courierPackage->tenant_id,
                'package_id' => $courierPackage->id,
                'status_id' => $targetStatus->id,
                'note' => 'Bulk shipment intake scan: received at the port for '.$shipment->shipment_number,
                'location' => 'Warehouse / Port',
                'added_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $courierPackage->forceFill(['status_id' => $targetStatus->id])->save();
            $courierPackage->setRelation('status', $targetStatus);
        }

        $weight = $weightOverride !== null ? $weightOverride : (float) ($courierPackage->weight ?? 0);
        $unit = $this->normalizeMeasureUnit($unitOverride ?: ($shipment->shipment_type === 'sea' ? 'cuft' : 'lb'), $shipment->shipment_type);
        $charges = $this->chargesForPackage($weight, $unit, $shipment->shipment_type, $shipment->region ?? 'USA');

        $logisticsPackage = SuperadminLogisticsPackage::create(array_merge([
            'shipment_id' => $shipment->id,
            'tenant_id' => $courierPackage->tenant_id,
            'courier_package_id' => $courierPackage->id,
            'tracking_number' => $courierPackage->tracking_number ?: $tracking,
            'description' => $courierPackage->description ?: 'Shipment intake package',
            'weight' => $weight,
            'weight_unit' => $unit,
            'checked_in_at' => now(),
            'checked_in_by' => auth()->id(),
            'location_status' => 'in_house',
            'location_notes' => 'Scanned into shipment '.$shipment->shipment_number.'.',
        ], $charges));

        return $this->shipmentScanResult(
            $rawTracking,
            (int) $courierPackage->status_id === (int) $targetStatus->id && $previousStatus === $targetStatus->name ? 'Already received' : 'Successful',
            'Matched and assigned to shipment '.$shipment->shipment_number.'.',
            $courierPackage->fresh(['tenant', 'customer.user', 'status']),
            $logisticsPackage,
            $previousStatus,
            $targetStatus->name
        );
    }

    private function shipmentScanResult(string $scan, string $result, string $message, ?Package $courierPackage = null, ?SuperadminLogisticsPackage $logisticsPackage = null, ?string $previousStatus = null, ?string $newStatus = null): array
    {
        return [
            'scan' => $scan,
            'result' => $result,
            'message' => $message,
            'courier' => optional($courierPackage?->tenant ?? $logisticsPackage?->tenant)->company_name,
            'package' => $courierPackage?->tracking_number ?: $logisticsPackage?->tracking_number,
            'customer' => optional($courierPackage?->customer?->user)->name ?: $courierPackage?->mailbox,
            'weight' => $logisticsPackage ? $this->formatPackageMeasure($logisticsPackage) : null,
            'previous_status' => $previousStatus ?? optional($courierPackage?->status)->name,
            'new_status' => $newStatus,
            'logistics_package_id' => $logisticsPackage?->id,
            'courier_package_id' => $courierPackage?->id ?: $logisticsPackage?->courier_package_id,
            'tenant_id' => $courierPackage?->tenant_id ?: $logisticsPackage?->tenant_id,
            'weight_value' => $logisticsPackage ? (float) $logisticsPackage->weight : null,
            'weight_unit' => $logisticsPackage?->weight_unit,
        ];
    }


    private function recordShipmentScan(SuperadminLogisticsShipment $shipment, array $result): void
    {
        if (! Schema::hasTable('superadmin_logistics_shipment_scans')) {
            return;
        }

        DB::table('superadmin_logistics_shipment_scans')->insert([
            'shipment_id' => $shipment->id,
            'logistics_package_id' => $result['logistics_package_id'] ?? null,
            'courier_package_id' => $result['courier_package_id'] ?? null,
            'tenant_id' => $result['tenant_id'] ?? null,
            'scan_value' => (string) ($result['scan'] ?? ''),
            'result' => (string) ($result['result'] ?? 'Unknown'),
            'message' => $result['message'] ?? null,
            'previous_status' => $result['previous_status'] ?? null,
            'new_status' => $result['new_status'] ?? null,
            'weight' => $result['weight_value'] ?? null,
            'weight_unit' => $result['weight_unit'] ?? null,
            'scanned_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordOriginalShipmentIntakeIfMissing(SuperadminLogisticsShipment $shipment, SuperadminLogisticsPackage $logisticsPackage, string $scan): void
    {
        if (! Schema::hasTable('superadmin_logistics_shipment_scans')) {
            return;
        }

        $alreadyRecorded = DB::table('superadmin_logistics_shipment_scans')
            ->where('shipment_id', $shipment->id)
            ->where('logistics_package_id', $logisticsPackage->id)
            ->whereIn('result', ['Successful', 'Already received'])
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $createdAt = $logisticsPackage->checked_in_at ?: $logisticsPackage->created_at ?: now();
        DB::table('superadmin_logistics_shipment_scans')->insert([
            'shipment_id' => $shipment->id,
            'logistics_package_id' => $logisticsPackage->id,
            'courier_package_id' => $logisticsPackage->courier_package_id,
            'tenant_id' => $logisticsPackage->tenant_id,
            'scan_value' => (string) ($logisticsPackage->tracking_number ?: $scan),
            'result' => 'Successful',
            'message' => 'Matched and assigned to this shipment.',
            'previous_status' => null,
            'new_status' => optional($logisticsPackage->courierPackage?->status)->name,
            'weight' => $logisticsPackage->weight,
            'weight_unit' => $logisticsPackage->weight_unit,
            'scanned_by' => $logisticsPackage->checked_in_by ?: auth()->id(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
    private function parseBulkCheckInScans(string $input): array
    {
        $rows = preg_split('/[\r\n,;\t]+/', $input) ?: [];
        $scans = [];

        foreach ($rows as $row) {
            $raw = trim((string) $row);
            if ($raw === '') {
                continue;
            }

            $key = Str::upper($raw);
            $scans[] = [
                'raw' => $raw,
                'key' => $key,
            ];
        }

        return $scans;
    }

    private function packagesByBulkScanKey(array $scanKeys): array
    {
        if (empty($scanKeys)) {
            return [];
        }

        $packages = Package::with(['tenant', 'customer.user', 'status'])
            ->where(function ($query) use ($scanKeys) {
                $query->whereIn(DB::raw('UPPER(tracking_number)'), $scanKeys)
                    ->orWhereIn(DB::raw('UPPER(supplier_tracking)'), $scanKeys);
            })
            ->get();

        $map = [];
        foreach ($packages as $package) {
            foreach ([$package->tracking_number, $package->supplier_tracking] as $value) {
                $key = Str::upper(trim((string) $value));
                if ($key === '' || ! in_array($key, $scanKeys, true)) {
                    continue;
                }

                $map[$key] = $map[$key] ?? collect();
                if (! $map[$key]->contains('id', $package->id)) {
                    $map[$key]->push($package);
                }
            }
        }

        return $map;
    }

    private function receivedAtPortStatusForTenant(int $tenantId): ?PackageStatus
    {
        $status = PackageStatus::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->first(function (PackageStatus $status) {
                $slug = Str::lower((string) $status->slug);
                $name = Str::lower(trim((string) $status->name));

                return in_array($slug, ['received-at-the-port', 'received-at-port'], true)
                    || in_array($name, ['received at the port', 'received at port'], true);
            });

        if ($status) {
            return $status;
        }

        return PackageStatus::create([
            'tenant_id' => $tenantId,
            'name' => 'Received at the Port',
            'slug' => 'received-at-the-port',
            'color' => '#0ea5e9',
            'icon' => 'fa-warehouse',
            'is_default' => false,
            'sort_order' => ((int) PackageStatus::where('tenant_id', $tenantId)->max('sort_order')) + 10,
            'is_active' => true,
        ]);
    }

    private function bulkCheckInResult(string $scan, string $result, string $message, ?Package $package = null, ?PackageStatus $newStatus = null, ?string $previousStatus = null): array
    {
        return [
            'scan' => $scan,
            'result' => $result,
            'message' => $message,
            'courier' => optional($package?->tenant)->company_name,
            'package' => $package?->tracking_number ?: $package?->supplier_tracking,
            'customer' => optional($package?->customer?->user)->name ?: $package?->mailbox,
            'previous_status' => $previousStatus ?? optional($package?->status)->name,
            'new_status' => $newStatus?->name,
        ];
    }
}










