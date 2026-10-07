<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\SuperadminLogisticsPackage;
use App\Models\SuperadminLogisticsRate;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WeightRate;
use App\Services\CustomerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WmsSyncController extends Controller
{
    public function customer(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'required|integer',
            'mailbox' => 'required|string|max:100',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        $tenantId = $this->tenantIdForCourier((int) $data['courier_id']);
        $mailbox = Customer::normalizeMailbox($data['mailbox']);
        $name = trim((string) ($data['name'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''))));

        $customer = DB::transaction(function () use ($tenantId, $mailbox, $name, $data) {
            $customer = Customer::where('tenant_id', $tenantId)->whereNormalizedMailbox($mailbox)->first();
            $user = $customer?->user ?: User::where('tenant_id', $tenantId)->where('mailbox', $mailbox)->first();

            if (! $user) {
                $user = User::create([
                    'tenant_id' => $tenantId,
                    'user_type' => 'customer',
                    'mailbox' => $mailbox,
                    'full_name' => $name ?: $mailbox,
                    'email' => $data['email'] ?? $mailbox.'@wms.local',
                    'phone' => $data['phone'] ?? null,
                    'address_line1' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'parish' => $data['state'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'is_active' => $data['active'] ?? true,
                ]);
            } else {
                $user->update([
                    'full_name' => $name ?: $user->full_name,
                    'email' => $data['email'] ?? $user->email,
                    'phone' => $data['phone'] ?? $user->phone,
                    'address_line1' => $data['address'] ?? $user->address_line1,
                    'city' => $data['city'] ?? $user->city,
                    'parish' => $data['state'] ?? $user->parish,
                    'is_active' => $data['active'] ?? $user->is_active,
                ]);
            }

            return Customer::updateOrCreate(
                ['tenant_id' => $tenantId, 'mailbox' => $mailbox],
                [
                    'user_id' => $user->id,
                    'is_active' => $data['active'] ?? true,
                ]
            );
        });

        return response()->json(['status' => 'success', 'data' => ['customer_id' => $customer->id]]);
    }

    public function exportCustomers(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'nullable|integer',
            'tenant_id' => 'nullable|integer',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $offset = (int) ($data['offset'] ?? 0);
        $limit = min(max((int) ($data['limit'] ?? 5000), 1), 5000);

        $tenantId = null;
        if (! empty($data['courier_id'])) {
            $tenantId = $this->tenantIdForCourier((int) $data['courier_id']);
        } elseif (! empty($data['tenant_id'])) {
            $tenantId = (int) $data['tenant_id'];
        }

        $customers = Customer::with(['user', 'outlet'])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereHas('tenant', fn ($query) => $query->whereIn('status', ['active', 'trial']))
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        $data = $customers->map(function (Customer $customer) {
            $user = $customer->user;
            $name = trim((string) optional($user)->full_name);
            [$firstName, $lastName] = $this->splitName($name);

            return [
                'courier_id' => (int) TenantSetting::getValue((int) $customer->tenant_id, 'wms_courier_id'),
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'mailbox' => $customer->mailbox,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $name,
                'email' => optional($user)->email,
                'phone' => optional($user)->phone,
                'address' => optional($user)->address_line1,
                'city' => optional($user)->city,
                'state' => optional($user)->parish,
                'outlet_id' => optional($customer->outlet)->outlet_code,
                'active' => (bool) $customer->is_active,
            ];
        })->filter(fn ($row) => ! empty($row['mailbox']))->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'offset' => $offset + $customers->count(),
            'count' => $customers->count(),
            'has_more' => $customers->count() === $limit,
        ]);
    }

    public function exportCouriers(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $offset = (int) ($data['offset'] ?? 0);
        $limit = min(max((int) ($data['limit'] ?? 5000), 1), 5000);

        $tenants = Tenant::whereIn('status', ['active', 'trial'])
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        $couriers = $tenants->map(function (Tenant $tenant) {
            return [
                'tenant_id' => $tenant->id,
                'wms_courier_id' => (int) TenantSetting::getValue((int) $tenant->id, 'wms_courier_id'),
                'company_name' => $tenant->company_name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'parish' => $tenant->parish,
                'portal_url' => $tenant->portalUrl(),
                'status' => $tenant->status,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $couriers,
            'offset' => $offset + $tenants->count(),
            'count' => $tenants->count(),
            'has_more' => $tenants->count() === $limit,
        ]);
    }

    public function package(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'nullable|integer',
            'courier_identifier' => 'nullable|string|max:150',
            'wms_package_id' => 'nullable|integer',
            'tracking_no' => 'nullable|string|max:150',
            'tracking_number' => 'nullable|string|max:150',
            'hawb' => 'nullable|string|max:150',
            'supplier_tracking_no' => 'nullable|string|max:150',
            'mailbox' => 'nullable|string|max:100',
            'user_name' => 'nullable|string|max:255',
            'courier' => 'nullable|string|max:100',
            'merchant' => 'nullable|string|max:255',
            'item_description' => 'nullable|string',
            'package_type' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'weight_unit' => 'nullable|string|max:10',
            'pieces' => 'nullable|integer|min:1',
            'volume' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'freight_type' => 'nullable|string|max:80',
            'shipment_type' => 'nullable|string|max:20',
            'region' => 'nullable|string|max:20',
            'label_type' => 'nullable|string|max:40',
            'entry_mode' => 'nullable|string|in:STANDARD,DROPOFF,FORCED_UNKNOWN',
            'routing_status' => 'nullable|string|in:VERIFIED,ASSIGNED_UNKNOWN,GENERAL_UNKNOWN',
            'warehouse_code' => 'nullable|string|max:20',
            'consignee_name_on_package' => 'nullable|string|max:255',
            'unknown_reason' => 'nullable|string|max:80',
            'internal_notes' => 'nullable|string',
            'package_image_paths' => 'nullable|array',
            'package_image_paths.*' => 'nullable|string|max:500',
            'dimension_length' => 'nullable|numeric|min:0',
            'dimension_width' => 'nullable|numeric|min:0',
            'dimension_height' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:in,cm,ft',
            'volume_cuft' => 'nullable|numeric|min:0',
            'volume_cbm' => 'nullable|numeric|min:0',
            'chargeable_weight' => 'nullable|numeric|min:0',
            'unknown_fee' => 'nullable|numeric|min:0',
            'unknown_fee_jmd' => 'nullable|numeric|min:0',
            'unknown_fee_reason' => 'nullable|string|max:255',
            'capture_method' => 'nullable|string|max:30',
            'idempotency_key' => 'nullable|string|max:120',
            'status_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'manifest_no' => 'nullable|string|max:255',
            'date_received' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        $tenantId = ! empty($data['courier_id'])
            ? $this->tenantIdForCourier((int) $data['courier_id'])
            : $this->tenantIdForCourierIdentifier($data['courier_identifier'] ?? null);
        $routingStatus = $this->wmsRoutingStatus($data);
        $customer = $this->shouldResolveWmsCustomer($routingStatus)
            ? $this->customerForMailbox($tenantId, $data['mailbox'] ?? null, $data['user_name'] ?? $data['consignee_name_on_package'] ?? null)
            : null;
        $statusId = $this->statusId($tenantId, $data['status_name'] ?? $data['status'] ?? null);
        $tracking = $this->packageHawb($data);
        abort_if($tracking === '', 422, 'A HAWB/tracking number is required.');
        $supplierTracking = $this->supplierTracking($data, $tracking);
        $freightType = $this->normalizedFreightType($data['shipment_type'] ?? $data['freight_type'] ?? null);
        $weight = (float) ($data['weight'] ?? 0);
        $region = $this->normalizePackageRegion($data['region'] ?? $data['warehouse_code'] ?? 'USA');
        $billableMeasure = $this->billableMeasureInput($weight, $freightType, $data);
        $volumeCuft = $freightType === 'sea' ? $billableMeasure : null;
        $volumeCbm = $freightType === 'sea' && $billableMeasure > 0 ? $billableMeasure / WeightRate::CBM_TO_CUFT : null;
        if ($freightType === 'sea' && (float) ($data['volume_cbm'] ?? 0) > 0) {
            $volumeCbm = (float) $data['volume_cbm'];
        }
        $unknownFee = max(0, (float) ($data['unknown_fee_jmd'] ?? $data['unknown_fee'] ?? 0));
        $billingData = [
            'customer_id' => optional($customer)->id,
            'weight' => $billableMeasure,
            'freight_type' => $freightType,
            'region' => $region,
            'delivery_type' => 'pickup',
        ];
        $priceJmd = $this->packagePrice($tenantId, $billingData);
        $processingFeeJmd = $this->processingFee($tenantId, $billingData);
        $storageDays = $this->storageDaysFromDate($data['date_received'] ?? null);
        $storageFee = $this->storageFee($tenantId, $storageDays);

        $package = Package::where('tenant_id', $tenantId)
            ->when(! empty($data['wms_package_id']), fn ($query) => $query->where('wms_package_id', $data['wms_package_id']))
            ->first()
            ?: Package::where('tenant_id', $tenantId)->where('tracking_number', $tracking)->first();

        $values = [
            'wms_package_id' => $data['wms_package_id'] ?? optional($package)->wms_package_id,
            'customer_id' => optional($customer)->id,
            'outlet_id' => optional($customer)->outlet_id,
            'mailbox' => optional($customer)->mailbox ?: ($data['mailbox'] ?? null),
            'supplier_tracking' => $supplierTracking,
            'courier' => $data['courier'] ?? null,
            'merchant' => $data['merchant'] ?? null,
            'description' => $data['item_description'] ?? null,
            'weight' => $freightType === 'sea' ? $billableMeasure : $weight,
            'price_usd' => $data['price'] ?? 0,
            'price_jmd' => $priceJmd,
            'processing_fee_jmd' => $processingFeeJmd,
            'unknown_fee_jmd' => $unknownFee,
            'unknown_fee_reason' => $unknownFee > 0 ? ($data['unknown_fee_reason'] ?? $data['unknown_reason'] ?? null) : null,
            'storage_days' => $storageDays,
            'storage_fee' => $storageFee,
            'freight_type' => $freightType,
            'region' => $region,
            'volume_cuft' => $volumeCuft ? round($volumeCuft, 4) : null,
            'volume_cbm' => $volumeCbm ? round($volumeCbm, 4) : null,
            'label_type' => $data['label_type'] ?? $this->regionalLabelType($region, $freightType),
            'delivery_type' => 'pickup',
            'status_id' => $statusId,
            'date_received' => $data['date_received'] ?? now()->toDateString(),
            'notes' => $this->packageSyncNotes($data, optional($package)->notes),
            'wms_last_synced_at' => now(),
        ];

        if ($package) {
            $oldStatus = $package->status_id;
            $package->update($values);
        } else {
            $oldStatus = null;
            $package = Package::create(array_merge($values, [
                'tenant_id' => $tenantId,
                'tracking_number' => $tracking,
            ]));
        }

        if ($oldStatus !== $package->status_id) {
            PackageLog::create([
                'tenant_id' => $tenantId,
                'package_id' => $package->id,
                'status_id' => $package->status_id,
                'note' => 'Synced from WMS',
                'created_at' => now(),
            ]);
        }

        $this->syncSuperadminLogisticsPackage($package->fresh(['customer.user']), $data, $tenantId, $supplierTracking, $freightType);

        return response()->json(['status' => 'success', 'data' => ['package_id' => $package->id]]);
    }

    public function packageUpload(Request $request)
    {
        return $this->package($request);
    }

    public function status(Request $request, CustomerNotificationService $notifications)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'nullable|integer',
            'courier_identifier' => 'nullable|string|max:150',
            'tracking_number' => 'nullable|string|max:150',
            'tracking_no' => 'nullable|string|max:150',
            'hawb' => 'nullable|string|max:150',
            'region' => 'nullable|string|max:20',
            'status' => 'required|string|max:255',
            'timestamp' => 'nullable|date',
        ]);

        $tenantId = ! empty($data['courier_id'])
            ? $this->tenantIdForCourier((int) $data['courier_id'])
            : $this->tenantIdForCourierIdentifier($data['courier_identifier'] ?? null);
        $hawb = strtoupper(trim((string) ($data['hawb'] ?? $data['tracking_no'] ?? '')));
        $tracking = strtoupper(trim((string) ($data['tracking_number'] ?? '')));

        $package = Package::where('tenant_id', $tenantId)
            ->where(function ($query) use ($hawb, $tracking) {
                if ($hawb !== '') {
                    $query->orWhere('tracking_number', $hawb);
                }
                if ($tracking !== '') {
                    $query->orWhere('tracking_number', $tracking)->orWhere('supplier_tracking', $tracking);
                }
            })
            ->first();

        abort_unless($package, 404, 'Package was not found for this courier.');

        $statusId = $this->statusId($tenantId, $data['status']);
        $package->update([
            'status_id' => $statusId,
            'notes' => $this->packageSyncNotes($data, $package->notes),
        ]);

        $log = PackageLog::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status_id' => $statusId,
            'note' => 'Status synced from WMS'.(! empty($data['region']) ? ' ('.$data['region'].')' : ''),
            'created_at' => ! empty($data['timestamp']) ? \Illuminate\Support\Carbon::parse($data['timestamp']) : now(),
        ]);

        $notifications->packageStatusUpdated($package->fresh(['tenant', 'customer.user', 'status']), $log);

        return response()->json(['status' => 'success', 'data' => ['package_id' => $package->id, 'status_id' => $statusId]]);
    }

    public function label(Request $request, string $trackingNumber)
    {
        $this->authorizeSync($request);
        $package = $this->packageForLabel($trackingNumber, ['tenant', 'customer.user']);

        abort_unless($package, 404, 'Package label data was not found.');

        $logisticsPackage = Schema::hasTable('superadmin_logistics_packages')
            ? SuperadminLogisticsPackage::with('manifest')->where('courier_package_id', $package->id)->first()
            : null;
        $metadata = $this->packageSyncMetadata($package->notes);
        $shipmentType = strtoupper($package->freight_type === 'sea' ? 'SEA' : 'AIR');
        $weight = (float) ($package->weight ?? 0);
        $volume = (float) ($metadata['volume'] ?? ($shipmentType === 'SEA' ? optional($logisticsPackage)->weight : 0));
        $tenant = $package->tenant;
        $tenantId = (int) optional($tenant)->id;
        $tenantName = optional($tenant)->company_name ?: 'System Admin Logistics';
        $tenantLogo = optional($tenant)->logo;
        $tenantLogoUrl = $tenantLogo
            ? (Str::startsWith($tenantLogo, ['http://', 'https://']) ? $tenantLogo : asset(ltrim($tenantLogo, '/')))
            : null;
        $tenantLogoDataUri = $this->tenantLogoDataUri($tenantLogo);
        $tenantAddress = collect([
            optional($tenant)->address,
            trim(collect([optional($tenant)->city, optional($tenant)->parish])->filter()->implode(', ')),
        ])->filter()->implode('<br>');
        $configuredPrefix = $tenantId
            ? (TenantSetting::getValue($tenantId, 'hawb_prefix')
                ?: TenantSetting::getValue($tenantId, 'courier_code')
                ?: TenantSetting::getValue($tenantId, 'mailbox_prefix'))
            : null;
        $configuredDisplayPrefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configuredPrefix)));
        $courierCodePrefix = $configuredDisplayPrefix;

        if ($courierCodePrefix === '') {
            $courierCodePrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $tenantName), 0, 4)) ?: 'COU';
        }
        if ($configuredDisplayPrefix === '') {
            $configuredDisplayPrefix = $courierCodePrefix;
        }
        if (preg_match('/^OL-?(.+)$/i', $configuredDisplayPrefix, $prefixMatch)) {
            $configuredDisplayPrefix = 'OL-'.$prefixMatch[1];
        } else {
            $configuredDisplayPrefix = 'OL-'.$configuredDisplayPrefix;
        }

        $rawMailbox = strtoupper(trim((string) ($package->mailbox ?: optional($package->customer)->mailbox)));
        if ($rawMailbox === '') {
            $accountDisplay = 'N/A';
        } else {
            $mailboxWithoutOl = preg_replace('/^OL-?/i', '', $rawMailbox);
            $accountDisplay = preg_match('/^\d+$/', $mailboxWithoutOl)
                ? $configuredDisplayPrefix.str_pad($mailboxWithoutOl, max(4, strlen($mailboxWithoutOl)), '0', STR_PAD_LEFT)
                : 'OL-'.$mailboxWithoutOl;
        }

        $rawTrackingNumber = strtoupper(trim((string) $package->tracking_number));
        if (preg_match('/^(.+?)(\d+)$/', $rawTrackingNumber, $trackingParts)) {
            $trackingPrefix = strtoupper(trim($trackingParts[1], " -\t\n\r\0\x0B"));
            $referenceNumber = $trackingParts[2];
        } else {
            $trackingPrefix = null;
            $referenceNumber = str_pad((string) $package->id, 6, '0', STR_PAD_LEFT);
        }
        $displayPrefix = $trackingPrefix
            ? (preg_match('/^OL-?/i', $trackingPrefix) ? preg_replace('/^OL-?/i', 'OL-', $trackingPrefix) : 'OL-'.preg_replace('/[^A-Z0-9-]/i', '', $trackingPrefix))
            : $configuredDisplayPrefix;
        $olReference = $displayPrefix.$referenceNumber;
        $hawbValue = preg_replace('/[^\x20-\x7E]/', '', $olReference) ?: $courierCodePrefix.'000001';
        $weightDisplay = $shipmentType === 'SEA' && $volume > 0
            ? rtrim(rtrim(number_format($volume, 2), '0'), '.').' CU.FT'
            : number_format($weight, 1).' LBS';
        $createdDate = optional($package->date_received)->format('M j, Y')
            ?: optional(optional($logisticsPackage)->checked_in_at)->format('M j, Y')
            ?: optional($package->created_at)->format('M j, Y')
            ?: now()->format('M j, Y');

        return response()->json([
            'status' => 'success',
            'data' => [
                'tracking_number' => $package->supplier_tracking ?: $package->tracking_number,
                'hawb' => $package->tracking_number,
                'region' => $metadata['region'] ?? null,
                'weight' => $shipmentType === 'AIR' ? $weight : null,
                'volume' => $shipmentType === 'SEA' ? $volume : null,
                'shipment_type' => $shipmentType,
                'mailbox' => $package->mailbox,
                'customer_name' => optional(optional($package->customer)->user)->full_name,
                'description' => $package->description,
                'destination' => optional(optional($logisticsPackage)->manifest)->manifest_number,
                'label_layout' => 'cms-default',
                'label' => [
                    'tenant_name' => $tenantName,
                    'tenant_logo_url' => $tenantLogoUrl,
                    'tenant_logo_data_uri' => $tenantLogoDataUri,
                    'tenant_address_html' => $tenantAddress,
                    'consignee' => optional(optional($package->customer)->user)->full_name ?: optional(optional($package->customer)->user)->email ?: 'Unassigned Customer',
                    'account_display' => $accountDisplay,
                    'shipper_name' => $tenantName,
                    'weight_display' => $weightDisplay,
                    'tracking_other' => $package->supplier_tracking ?: $package->tracking_number,
                    'destination' => optional(optional($logisticsPackage)->manifest)->manifest_number ?: 'N/A',
                    'freight_type' => $shipmentType,
                    'package_contents' => optional($logisticsPackage)->description ?: $package->description ?: 'Package',
                    'created_date' => $createdDate,
                    'display_generator' => $hawbValue,
                    'barcode_value' => $hawbValue,
                    'reference_number' => $referenceNumber,
                    'ol_reference' => $olReference,
                ],
            ],
        ]);
    }

    public function labelHtml(Request $request, string $trackingNumber)
    {
        $this->authorizeSync($request);
        $package = $this->packageForLabel($trackingNumber, ['tenant', 'customer.user', 'status', 'packageType', 'outlet']);

        abort_unless($package, 404, 'Package label was not found.');

        return response()
            ->view('courier.packages.label', compact('package'))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function nextPackageNumbers(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'required|integer',
            'count' => 'nullable|integer|min:1|max:5000',
        ]);

        $tenantId = $this->tenantIdForCourier((int) $data['courier_id']);
        $count = (int) ($data['count'] ?? 1);
        $prefix = $this->hawbPrefix($tenantId);
        $sequence = Package::where('tenant_id', $tenantId)
            ->where('tracking_number', 'like', $prefix.'%')
            ->count() + 1;
        $numbers = [];

        while (count($numbers) < $count) {
            $hawb = $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;

            if (Package::where('tenant_id', $tenantId)->where('tracking_number', $hawb)->exists() || in_array($hawb, $numbers, true)) {
                continue;
            }

            $numbers[] = $hawb;
        }

        return response()->json(['status' => 'success', 'data' => ['tracking_numbers' => $numbers]]);
    }

    public function deletePackage(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'required|integer',
            'wms_package_id' => 'nullable|integer',
            'tracking_no' => 'nullable|string|max:150',
        ]);

        $tenantId = $this->tenantIdForCourier((int) $data['courier_id']);
        $tracking = strtoupper(trim((string) ($data['tracking_no'] ?? '')));

        $package = Package::where('tenant_id', $tenantId)
            ->when(! empty($data['wms_package_id']), fn ($query) => $query->where('wms_package_id', $data['wms_package_id']))
            ->first();

        if (! $package && $tracking !== '') {
            $package = Package::where('tenant_id', $tenantId)->where('tracking_number', $tracking)->first();
        }

        if (! $package) {
            return response()->json(['status' => 'success', 'message' => 'Package was already absent.', 'data' => ['deleted' => false]]);
        }

        DB::transaction(function () use ($package) {
            if (Schema::hasTable('superadmin_logistics_packages') && Schema::hasColumn('superadmin_logistics_packages', 'courier_package_id')) {
                DB::table('superadmin_logistics_packages')
                    ->where('courier_package_id', $package->id)
                    ->update(['courier_package_id' => null]);
            }

            $package->delete();
        });

        return response()->json(['status' => 'success', 'data' => ['deleted' => true]]);
    }

    public function manifest(Request $request)
    {
        $this->authorizeSync($request);
        $data = $request->validate([
            'courier_id' => 'required|integer',
            'wms_manifest_id' => 'nullable|integer',
            'manifest_number' => 'required|string|max:100',
            'master_air_billing_no' => 'nullable|string|max:255',
            'cargo_company' => 'nullable|string|max:255',
            'date_of_departure' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'package_tracking_numbers' => 'nullable|array',
            'package_tracking_numbers.*' => 'string|max:150',
        ]);

        $tenantId = $this->tenantIdForCourier((int) $data['courier_id']);
        $manifest = Manifest::where('tenant_id', $tenantId)
            ->when(! empty($data['wms_manifest_id']), fn ($query) => $query->where('wms_manifest_id', $data['wms_manifest_id']))
            ->first()
            ?: Manifest::where('tenant_id', $tenantId)->where('manifest_number', $data['manifest_number'])->first();

        $values = [
            'wms_manifest_id' => $data['wms_manifest_id'] ?? optional($manifest)->wms_manifest_id,
            'manifest_number' => $data['manifest_number'],
            'master_air_bill' => $data['master_air_billing_no'] ?? null,
            'cargo_company' => $data['cargo_company'] ?? null,
            'date_of_departure' => $data['date_of_departure'] ?? null,
            'status' => in_array($data['status'] ?? 'draft', ['draft', 'submitted', 'approved', 'rejected'], true) ? $data['status'] : 'draft',
            'wms_last_synced_at' => now(),
        ];

        $manifest = $manifest
            ? tap($manifest)->update($values)
            : Manifest::create(array_merge($values, ['tenant_id' => $tenantId]));

        $trackingNumbers = collect($data['package_tracking_numbers'] ?? [])->map(fn ($value) => strtoupper(trim($value)))->filter()->all();
        $packageIds = Package::where('tenant_id', $tenantId)->whereIn('tracking_number', $trackingNumbers)->pluck('id');

        foreach ($packageIds as $packageId) {
            ManifestItem::firstOrCreate([
                'tenant_id' => $tenantId,
                'manifest_id' => $manifest->id,
                'package_id' => $packageId,
            ]);
        }

        Package::where('tenant_id', $tenantId)->whereIn('id', $packageIds)->update([
            'manifest_id' => $manifest->id,
            'is_in_manifest' => true,
        ]);

        return response()->json(['status' => 'success', 'data' => ['manifest_id' => $manifest->id]]);
    }

    private function authorizeSync(Request $request): void
    {
        $token = (string) config('services.wms.token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 401);
    }

    private function tenantIdForCourier(int $courierId): int
    {
        $tenantId = TenantSetting::where('key', 'wms_courier_id')->where('value', (string) $courierId)->value('tenant_id');

        abort_unless($tenantId, 404, 'Courier is not linked to a SaaS tenant.');

        return (int) $tenantId;
    }

    private function tenantIdForCourierIdentifier(?string $identifier): int
    {
        $identifier = trim((string) $identifier);
        abort_if($identifier === '', 422, 'Courier identifier is required.');

        $tenantId = TenantSetting::whereIn('key', ['wms_courier_id', 'courier_code', 'hawb_prefix', 'mailbox_prefix'])
            ->where('value', $identifier)
            ->value('tenant_id');

        if (! $tenantId) {
            $tenantId = Tenant::where('slug', $identifier)
                ->orWhere('company_name', $identifier)
                ->orWhere('email', $identifier)
                ->value('id');
        }

        abort_unless($tenantId, 404, 'Courier is not linked to a SaaS tenant.');

        return (int) $tenantId;
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

    private function customerForMailbox(int $tenantId, ?string $mailbox, ?string $name): ?Customer
    {
        $mailbox = Customer::normalizeMailbox($mailbox);

        if ($mailbox) {
            $customer = Customer::where('tenant_id', $tenantId)->whereNormalizedMailbox($mailbox)->first();
            if ($customer) {
                return $customer;
            }

            $user = User::where('tenant_id', $tenantId)
                ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(mailbox, ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '')) = ?", [$mailbox])
                ->first();

            $customer = $user ? Customer::where('tenant_id', $tenantId)->where('user_id', $user->id)->first() : null;
            if ($customer) {
                return $customer;
            }
        }

        return $this->customerForWmsName($tenantId, $name);
    }

    private function customerForWmsName(int $tenantId, ?string $name): ?Customer
    {
        $normalized = $this->normalizedWmsName($name);
        if ($normalized === '') {
            return null;
        }

        $tokens = collect(explode(' ', $normalized))
            ->filter(fn ($token) => strlen($token) >= 2)
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return null;
        }

        $candidates = Customer::with('user')
            ->where('tenant_id', $tenantId)
            ->whereHas('user', function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where('full_name', 'like', '%'.$token.'%');
                }
            })
            ->limit(5)
            ->get();

        $exactMatches = $candidates
            ->filter(fn (Customer $customer) => $this->normalizedWmsName(optional($customer->user)->full_name) === $normalized)
            ->values();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function statusId(int $tenantId, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return PackageStatus::where('tenant_id', $tenantId)->orderByDesc('is_default')->orderBy('sort_order')->value('id');
        }

        return PackageStatus::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => Str::slug($name)],
            ['name' => $name, 'color' => '#2563eb', 'is_active' => true]
        )->id;
    }

    private function packageHawb(array $data): string
    {
        return strtoupper(trim((string) ($data['hawb'] ?? $data['tracking_no'] ?? $data['manifest_no'] ?? $data['tracking_number'] ?? '')));
    }

    private function supplierTracking(array $data, string $hawb): ?string
    {
        $supplier = strtoupper(trim((string) ($data['supplier_tracking_no'] ?? '')));
        if ($supplier !== '') {
            return $supplier;
        }

        $tracking = strtoupper(trim((string) ($data['tracking_number'] ?? '')));

        return $tracking !== '' && $tracking !== $hawb ? $tracking : null;
    }

    private function normalizedFreightType(?string $value): string
    {
        return str_contains(strtolower((string) $value), 'sea') ? 'sea' : 'air';
    }

    private function billableMeasureInput(float $weight, string $freightType, array $data): float
    {
        if ($freightType === 'sea' && (float) ($data['volume_cuft'] ?? 0) > 0) {
            return (float) $data['volume_cuft'];
        }
        if ($freightType === 'sea' && (float) ($data['volume_cbm'] ?? 0) > 0) {
            return (float) $data['volume_cbm'] * WeightRate::CBM_TO_CUFT;
        }
        if ($freightType === 'sea' && (float) ($data['volume'] ?? 0) > 0) {
            return (float) $data['volume'];
        }

        return $weight;
    }

    private function packagePrice(int $tenantId, array $data): float
    {
        $rate = $this->packageRate($tenantId, $data);
        if (! $rate) {
            return 0.0;
        }

        $measure = $this->billableWeight((float) ($data['weight'] ?? 0), $this->billingWeightMode($tenantId));

        return $rate->priceFor($measure, $data['delivery_type'] ?? 'pickup');
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

    private function packageRate(int $tenantId, array $data): ?WeightRate
    {
        $actualWeight = (float) ($data['weight'] ?? 0);
        $freightType = $data['freight_type'] ?? $this->tenantDefaultFreightType($tenantId);
        $freightCategory = $this->shippingModeCategory($tenantId, $freightType);
        if ($actualWeight <= 0) {
            return null;
        }

        $measure = $this->billableWeight($actualWeight, $this->billingWeightMode($tenantId));
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $outletId = optional($customer)->outlet_id;
        $useBranchRates = false;

        if ($outletId && in_array($freightCategory, ['air', 'sea'], true) && Schema::hasTable('outlets') && Schema::hasColumn('outlets', 'rate_group_mode')) {
            $useBranchRates = DB::table('outlets')
                ->where('tenant_id', $tenantId)
                ->where('id', $outletId)
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
            ->where(function ($query) use ($data) {
                $region = strtolower($this->normalizePackageRegion($data['region'] ?? 'USA'));
                $query->whereNull('region')->orWhere('region', '')->orWhereRaw('LOWER(region) = ?', [$region]);
            })
            ->orderByRaw("CASE WHEN LOWER(COALESCE(region, '')) = ? THEN 0 WHEN region IS NULL OR region = '' THEN 1 ELSE 2 END", [strtolower($this->normalizePackageRegion($data['region'] ?? 'USA'))])
            ->orderByRaw('outlet_id is null')
            ->orderByRaw('pricing_tier_id is null');

        $rate = (clone $base)
            ->where($this->rateRangeClause($freightCategory, $measure))
            ->orderBy('min_weight')
            ->first();

        return $rate ?: (clone $base)
            ->where($this->finalContinuousRateClause($freightCategory, $measure))
            ->orderByDesc('min_weight')
            ->first();
    }

    private function tenantDefaultFreightType(int $tenantId): string
    {
        if (class_exists(\App\Models\ShippingMode::class)) {
            \App\Models\ShippingMode::ensureDefaultsForTenant($tenantId);

            return \App\Models\ShippingMode::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->value('code') ?: 'air';
        }

        return 'air';
    }

    private function shippingModeCategory(int $tenantId, string $freightType): string
    {
        if (class_exists(\App\Models\ShippingMode::class)) {
            return \App\Models\ShippingMode::where('tenant_id', $tenantId)->where('code', $freightType)->value('category')
                ?: ($freightType === 'sea' ? 'sea' : ($freightType === 'local' ? 'local' : 'air'));
        }

        return $freightType === 'sea' ? 'sea' : ($freightType === 'local' ? 'local' : 'air');
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

    private function regionalLabelType(?string $region, string $freightType): string
    {
        $normalized = $this->normalizePackageRegion($region);
        $code = strtolower($normalized) === 'china' ? 'CN' : (strtolower($normalized) === 'usa' ? 'US' : strtoupper($normalized));
        $method = $freightType === 'sea' ? 'Sea' : 'Air';

        return trim($code.' '.$method);
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

    private function storageDaysFromDate($date): int
    {
        if (! $date) {
            return 0;
        }

        try {
            return max(0, (int) Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay()));
        } catch (\Throwable $e) {
            return 0;
        }
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

    private function packageSyncNotes(array $data, ?string $existingNotes): ?string
    {
        $metadata = $this->packageSyncMetadata($existingNotes);
        if ($metadata === [] && trim((string) $existingNotes) !== '' && json_decode((string) $existingNotes, true) === null) {
            $metadata['existing_notes'] = $existingNotes;
        }
        foreach ([
            'region',
            'label_type',
            'volume',
            'shipment_type',
            'courier_identifier',
            'entry_mode',
            'routing_status',
            'warehouse_code',
            'weight_unit',
            'pieces',
            'consignee_name_on_package',
            'unknown_reason',
            'internal_notes',
            'package_image_paths',
            'dimension_length',
            'dimension_width',
            'dimension_height',
            'dimension_unit',
            'volume_cuft',
            'volume_cbm',
            'chargeable_weight',
            'unknown_fee',
            'unknown_fee_jmd',
            'unknown_fee_reason',
            'capture_method',
            'idempotency_key',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $metadata[$key] = $data[$key];
            }
        }
        if (! empty($data['metadata']) && is_array($data['metadata'])) {
            $metadata['metadata'] = $data['metadata'];
        }

        return $metadata ? json_encode(['wms_sync' => $metadata]) : $existingNotes;
    }

    private function packageSyncMetadata(?string $notes): array
    {
        $decoded = json_decode((string) $notes, true);

        return is_array($decoded) && isset($decoded['wms_sync']) && is_array($decoded['wms_sync'])
            ? $decoded['wms_sync']
            : [];
    }

    private function wmsRoutingStatus(array $data): string
    {
        $status = strtoupper(trim((string) ($data['routing_status'] ?? '')));
        if (in_array($status, ['VERIFIED', 'ASSIGNED_UNKNOWN', 'GENERAL_UNKNOWN'], true)) {
            return $status;
        }

        $mailbox = trim((string) ($data['mailbox'] ?? ''));
        $name = trim((string) ($data['user_name'] ?? $data['consignee_name_on_package'] ?? ''));
        if ($mailbox !== '' && $mailbox !== '0' && $name !== '') {
            return 'VERIFIED';
        }

        return 'GENERAL_UNKNOWN';
    }

    private function shouldResolveWmsCustomer(string $routingStatus): bool
    {
        return in_array($routingStatus, ['VERIFIED', 'ASSIGNED_UNKNOWN'], true);
    }

    private function normalizedWmsName(?string $name): string
    {
        $name = preg_replace('/[^a-z0-9]+/i', ' ', (string) $name);
        $name = preg_replace('/\s+/', ' ', trim((string) $name));

        return strtolower($name);
    }

    private function syncSuperadminLogisticsPackage(Package $package, array $data, int $tenantId, ?string $supplierTracking, string $freightType): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages') || ! Schema::hasColumn('superadmin_logistics_packages', 'courier_package_id')) {
            return;
        }

        $tracking = $supplierTracking ?: $package->tracking_number;
        [$weight, $unit] = $this->syncedPackageLogisticsMeasure($package, $data, $freightType);
        $region = $this->normalizePackageRegion($data['region'] ?? $data['warehouse_code'] ?? $package->region ?? 'USA');
        $charges = $this->logisticsChargesForPackage($weight, $unit, $freightType, $region);

        $logisticsPackage = SuperadminLogisticsPackage::where('courier_package_id', $package->id)->first()
            ?: SuperadminLogisticsPackage::where('tracking_number', $tracking)->first()
            ?: new SuperadminLogisticsPackage();

        $routingStatus = $this->wmsRoutingStatus($data);
        $locationStatus = $routingStatus === 'VERIFIED' && $package->customer_id ? 'in_house' : 'inhouse_unknown_room';
        $notes = [
            'Synced from WMS API',
        ];
        if (! empty($data['region'])) {
            $notes[] = 'Region: '.$data['region'];
        }
        if ($routingStatus !== '') {
            $notes[] = 'Routing: '.$routingStatus;
        }
        if (! empty($data['consignee_name_on_package'])) {
            $notes[] = 'Name on package: '.$data['consignee_name_on_package'];
        }
        if (! empty($data['unknown_reason'])) {
            $notes[] = 'Unknown reason: '.$data['unknown_reason'];
        }

        $logisticsPackage->fill(array_merge([
            'tenant_id' => $tenantId,
            'courier_package_id' => $package->id,
            'tracking_number' => $tracking,
            'description' => $package->description,
            'weight' => $weight,
            'weight_unit' => $unit,
            'location_status' => $locationStatus,
            'location_notes' => implode("\n", $notes),
        ], $charges))->save();
    }

    private function syncedPackageLogisticsMeasure(Package $package, array $data, string $freightType): array
    {
        if ($freightType === 'sea') {
            if ((float) ($data['volume_cuft'] ?? 0) > 0) {
                return [(float) $data['volume_cuft'], 'cuft'];
            }
            if ((float) ($data['volume_cbm'] ?? 0) > 0) {
                return [(float) $data['volume_cbm'], 'cbm'];
            }
            if ((float) ($package->volume_cuft ?? 0) > 0) {
                return [(float) $package->volume_cuft, 'cuft'];
            }
            if ((float) ($package->volume_cbm ?? 0) > 0) {
                return [(float) $package->volume_cbm, 'cbm'];
            }

            return [(float) ($package->weight ?? 0), $this->normalizeLogisticsMeasureUnit((string) ($data['weight_unit'] ?? 'cuft'), 'sea')];
        }

        return [(float) ($package->weight ?? 0), $this->normalizeLogisticsMeasureUnit((string) ($data['weight_unit'] ?? 'lb'), 'air')];
    }

    private function logisticsChargesForPackage(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): array
    {
        $shipmentType = $shipmentType === 'sea' ? 'sea' : 'air';
        $unit = $this->normalizeLogisticsMeasureUnit($unit, $shipmentType);
        $rateMatch = $this->logisticsRateMatchFor($weight, $unit, $shipmentType, $region);
        $rateUsd = $rateMatch ? round((float) $rateMatch['rate']->rate_per_unit, 2) : 0.0;
        $billableWarehouseMeasure = $shipmentType === 'sea' && $rateMatch
            ? max((float) $rateMatch['measure'], $rateMatch['rate']->minimumBillableMeasure())
            : $weight;
        $warehouseFeeUsd = $shipmentType === 'sea' ? round($billableWarehouseMeasure * $rateUsd, 2) : $rateUsd;
        $pricing = $this->logisticsPricingSettings();
        $serviceFeeType = $pricing['service_fee_type'];
        $serviceFeeValue = (float) $pricing['service_fee_value'];
        $serviceFee = $serviceFeeType === 'percentage'
            ? round($warehouseFeeUsd * ($serviceFeeValue / 100), 2)
            : round($serviceFeeValue, 2);
        [$freightWeight, $freightUnit, $freightRate] = $this->logisticsFreightBasis($weight, $unit, $pricing);
        $freightCharge = round($freightWeight * $freightRate, 2);
        $amountUsd = round($warehouseFeeUsd + $serviceFee + $freightCharge, 2);
        $exchangeRate = $this->logisticsUsdToJmdRate();

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

    private function logisticsRateFor(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): float
    {
        $match = $this->logisticsRateMatchFor($weight, $unit, $shipmentType, $region);

        return $match ? round((float) $match['rate']->rate_per_unit, 2) : 0.0;
    }

    private function logisticsRateMatchFor(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): ?array
    {
        $shipmentType = $shipmentType === 'sea' ? 'sea' : 'air';
        $unit = $this->normalizeLogisticsMeasureUnit($unit, $shipmentType);
        $rate = $this->logisticsRateForUnit($weight, $unit, $shipmentType, $region);
        if ($rate) {
            return ['rate' => $rate, 'measure' => $weight, 'unit' => $unit];
        }

        $convertedWeight = $shipmentType === 'sea'
            ? ($unit === 'cbm' ? $weight * WeightRate::CBM_TO_CUFT : $weight / WeightRate::CBM_TO_CUFT)
            : ($unit === 'kg' ? $weight * 2.20462 : $weight / 2.20462);
        $convertedUnit = $shipmentType === 'sea'
            ? ($unit === 'cbm' ? 'cuft' : 'cbm')
            : ($unit === 'kg' ? 'lb' : 'kg');
        $convertedRate = $this->logisticsRateForUnit($convertedWeight, $convertedUnit, $shipmentType, $region);

        return $convertedRate ? ['rate' => $convertedRate, 'measure' => $convertedWeight, 'unit' => $convertedUnit] : null;
    }

    private function logisticsRateForUnit(float $weight, string $unit, string $shipmentType = 'air', ?string $region = 'USA'): ?SuperadminLogisticsRate
    {
        $region = strtolower($this->normalizePackageRegion($region));
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

    private function logisticsFreightBasis(float $weight, string $unit, array $pricing): array
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

    private function normalizeLogisticsMeasureUnit(string $unit, string $shipmentType): string
    {
        $unit = strtolower(trim($unit));
        if ($shipmentType === 'sea') {
            return in_array($unit, ['cuft', 'cbm'], true) ? $unit : 'cuft';
        }

        return $unit === 'kg' ? 'kg' : 'lb';
    }

    private function logisticsPricingSettings(): array
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

    private function logisticsUsdToJmdRate(): float
    {
        $pricing = $this->logisticsPricingSettings();
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

        return (float) $pricing['cached_exchange_rate_jmd'] > 0
            ? round((float) $pricing['cached_exchange_rate_jmd'], 4)
            : 0.0;
    }

    private function packageForLabel(string $trackingNumber, array $relations): ?Package
    {
        $tracking = strtoupper(trim($trackingNumber));

        return Package::with($relations)
            ->where('tracking_number', $tracking)
            ->orWhere('supplier_tracking', $tracking)
            ->first();
    }

    private function tenantLogoDataUri(?string $logo): ?string
    {
        $logo = trim((string) $logo);
        if ($logo === '' || Str::startsWith($logo, ['http://', 'https://', 'data:'])) {
            return Str::startsWith($logo, 'data:') ? $logo : null;
        }

        $path = public_path(ltrim($logo, '/'));
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
