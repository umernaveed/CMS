<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PackageStatus;
use App\Models\PackageType;
use App\Models\PricingTier;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\CustomerShippingAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataMaintenanceController extends Controller
{
    private array $tenantBackupTables = [
        'tenant_settings',
        'outlets',
        'roles',
        'users',
        'customers',
        'pricing_tiers',
        'customer_shipping_addresses',
        'package_statuses',
        'package_types',
        'weight_rates',
        'customs_offices',
        'loading_ports',
        'hs_codes',
        'delivery_areas',
        'delivery_time_slots',
        'packages',
        'package_logs',
        'invoices',
        'invoice_items',
        'payment_methods',
        'payments',
        'wallet_transactions',
        'referrals',
        'delivery_requests',
        'manifests',
        'manifest_items',
        'finance_entries',
        'pre_alerts',
        'purchase_requests',
        'crm_email_drafts',
        'support_tickets',
        'email_logs',
        'activity_logs',
        'system_notifications',
    ];

    private array $tenantPivotTables = [
        'role_permissions' => ['roles', 'role_id'],
        'user_roles' => ['users', 'user_id'],
        'outlet_users' => ['users', 'user_id'],
        'authorized_users' => ['customers', 'customer_id'],
        'pre_alert_receipts' => ['pre_alerts', 'pre_alert_id'],
        'purchase_request_receipts' => ['purchase_requests', 'purchase_request_id'],
        'support_messages' => ['support_tickets', 'support_ticket_id'],
        'notification_dismissals' => ['system_notifications', 'system_notification_id'],
    ];

    private array $duplicateSafeRestoreTables = [
        'role_permissions',
        'user_roles',
        'outlet_users',
        'notification_dismissals',
    ];

    private array $restoreWithoutOriginalIds = [
        'authorized_users',
        'pre_alert_receipts',
        'purchase_request_receipts',
        'support_messages',
        'notification_dismissals',
    ];

    private array $tenantClearTables = [
        'notification_dismissals',
        'support_messages',
        'activity_logs',
        'email_logs',
        'sms_logs',
        'purchase_request_receipts',
        'purchase_requests',
        'pre_alert_receipts',
        'pre_alerts',
        'delivery_requests',
        'manifest_items',
        'manifests',
        'invoice_items',
        'payments',
        'invoices',
        'package_logs',
        'packages',
        'wallet_transactions',
        'referrals',
        'authorized_users',
        'customer_shipping_addresses',
        'customers',
    ];

    private array $platformBackupTables = [
        'system_settings',
        'subscription_plans',
        'permissions',
        'tenants',
        'tenant_subscriptions',
        'tenant_payments',
        'support_tickets',
        'support_messages',
        'system_notifications',
        'notification_dismissals',
        'superadmin_logistics_manifests',
        'superadmin_logistics_packages',
        'superadmin_logistics_invoices',
        'superadmin_logistics_invoice_items',
        'superadmin_logistics_rates',
        'superadmin_finance_entries',
        'superadmin_billing_charges',
        'activity_logs',
    ];

    private array $platformClearTables = [
        'notification_dismissals',
        'support_messages',
        'support_tickets',
        'tenant_payments',
        'superadmin_logistics_invoice_items',
        'superadmin_logistics_invoices',
        'superadmin_logistics_packages',
        'superadmin_logistics_manifests',
        'superadmin_finance_entries',
        'activity_logs',
    ];

    public function courierBackup(): StreamedResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $payload = [
            'type' => 'courier_portal_backup',
            'version' => 1,
            'tenant_id' => $tenantId,
            'generated_at' => now()->toIso8601String(),
            'tables' => $this->tenantSnapshot($tenantId),
        ];

        ActivityLog::record('backup_downloaded', 'data_maintenance', 'Downloaded courier portal backup');

        return $this->jsonDownload($payload, 'courier-backup-tenant-'.$tenantId.'-'.now()->format('Ymd-His').'.json');
    }

    public function courierRestore(Request $request)
    {
        $payload = $this->uploadedPayload($request);
        if (($payload['type'] ?? null) !== 'courier_portal_backup') {
            return back()->withErrors(['backup_file' => 'Upload a courier portal backup JSON file.']);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        DB::transaction(function () use ($payload, $tenantId) {
            $this->withoutForeignKeys(function () use ($payload, $tenantId) {
                $this->deleteTenantRows($tenantId, array_reverse($this->tenantBackupTables));
                $this->insertSnapshotRows($payload['tables'] ?? [], $tenantId);
            });
        });

        ActivityLog::record('backup_restored', 'data_maintenance', 'Restored courier portal backup');

        return back()->with('success', 'Courier backup restored successfully.');
    }

    public function courierClear(Request $request)
    {
        $this->validateConfirmation($request, 'FRESH START');
        $tenantId = (int) auth()->user()->tenant_id;
        $customerUserIds = Customer::where('tenant_id', $tenantId)->pluck('user_id')->filter()->all();

        DB::transaction(function () use ($tenantId, $customerUserIds) {
            $this->withoutForeignKeys(function () use ($tenantId, $customerUserIds) {
                foreach ($this->tenantClearTables as $table) {
                    $this->deleteTenantScopedTable($table, $tenantId);
                }

                if ($customerUserIds && Schema::hasTable('users')) {
                    User::whereIn('id', $customerUserIds)->where('user_type', 'customer')->delete();
                }
            });
        });

        ActivityLog::record('test_data_cleared', 'data_maintenance', 'Cleared courier testing data');

        return back()->with('success', 'Courier testing data cleared. Staff, settings, branches, statuses, and rates were kept.');
    }

    public function courierSync(CustomerShippingAddressService $shippingAddresses)
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $this->ensureTenantDefaults($tenantId);
        Customer::where('tenant_id', $tenantId)->with(['user', 'outlet'])->chunkById(100, function ($customers) use ($shippingAddresses) {
            foreach ($customers as $customer) {
                $shippingAddresses->syncDefaultAddresses($customer);
            }
        });

        ActivityLog::record('synced', 'data_maintenance', 'Synced courier defaults and customer shipping addresses');

        return back()->with('success', 'Courier defaults and customer shipping addresses synced.');
    }

    public function platformBackup(): StreamedResponse
    {
        $payload = [
            'type' => 'superadmin_platform_backup',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'tables' => $this->tableSnapshot($this->platformBackupTables),
        ];

        ActivityLog::record('backup_downloaded', 'data_maintenance', 'Downloaded System Admin platform backup');

        return $this->jsonDownload($payload, 'superadmin-platform-backup-'.now()->format('Ymd-His').'.json');
    }

    public function platformRestore(Request $request)
    {
        $payload = $this->uploadedPayload($request);
        if (($payload['type'] ?? null) !== 'superadmin_platform_backup') {
            return back()->withErrors(['backup_file' => 'Upload a System Admin platform backup JSON file.']);
        }

        DB::transaction(function () use ($payload) {
            $this->withoutForeignKeys(function () use ($payload) {
                foreach (array_reverse($this->platformBackupTables) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }
                $this->insertSnapshotRows($payload['tables'] ?? []);
            });
        });

        Cache::forget('platform_settings');
        ActivityLog::record('backup_restored', 'data_maintenance', 'Restored System Admin platform backup');

        return back()->with('success', 'System Admin platform backup restored successfully.');
    }

    public function platformClear(Request $request)
    {
        $this->validateConfirmation($request, 'FRESH START');

        DB::transaction(function () {
            $this->withoutForeignKeys(function () {
                foreach ($this->platformClearTables as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }
            });
        });

        ActivityLog::record('test_data_cleared', 'data_maintenance', 'Cleared System Admin testing data');

        return back()->with('success', 'System Admin testing data cleared. Couriers, plans, and system settings were kept.');
    }

    public function platformSync()
    {
        $this->ensurePlatformDefaults();
        Cache::forget('platform_settings');

        ActivityLog::record('synced', 'data_maintenance', 'Synced platform defaults and refreshed cached settings');

        return back()->with('success', 'Platform defaults synced and cached settings refreshed.');
    }

    public function automaticBackup(Request $request)
    {
        $data = $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
        ]);

        Artisan::call('backups:automatic', ['frequency' => $data['frequency']]);

        return back()->with('success', trim(Artisan::output()) ?: ucfirst($data['frequency']).' automatic backup completed.');
    }

    private function tenantSnapshot(int $tenantId): array
    {
        $tables = [];
        foreach ($this->tenantBackupTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            $tables[$table] = $query->get()->map(fn ($row) => (array) $row)->all();
        }

        foreach ($this->tenantPivotTables as $table => [$parentTable, $parentKey]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
                continue;
            }

            $parentIds = DB::table($parentTable)->where('tenant_id', $tenantId)->pluck('id');
            $tables[$table] = DB::table($table)->whereIn($parentKey, $parentIds)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $tables;
    }

    private function tableSnapshot(array $tables): array
    {
        $snapshot = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        return $snapshot;
    }

    private function insertSnapshotRows(array $tables, ?int $tenantId = null): void
    {
        foreach ($tables as $table => $rows) {
            if (! Schema::hasTable($table) || ! is_array($rows) || $rows === []) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $rows = collect($rows)->map(function ($row) use ($table, $columns, $tenantId) {
                $row = collect((array) $row)->only($columns)->all();
                if ($tenantId && in_array('tenant_id', $columns, true)) {
                    $row['tenant_id'] = $tenantId;
                }

                if ($tenantId && in_array($table, $this->restoreWithoutOriginalIds, true)) {
                    unset($row['id']);
                }

                return $row;
            })->filter();

            if (in_array($table, $this->duplicateSafeRestoreTables, true)) {
                $rows = $rows->unique(fn ($row) => json_encode($row));
            }

            $chunks = $rows->chunk(250);

            foreach ($chunks as $chunk) {
                $records = $chunk->values()->all();
                if ($records === []) {
                    continue;
                }

                if (in_array($table, $this->duplicateSafeRestoreTables, true)) {
                    DB::table($table)->insertOrIgnore($records);
                    continue;
                }

                DB::table($table)->insert($records);
            }
        }
    }

    private function deleteTenantRows(int $tenantId, array $tables): void
    {
        foreach ($this->tenantPivotTables as $table => [$parentTable, $parentKey]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
                continue;
            }

            $parentIds = DB::table($parentTable)->where('tenant_id', $tenantId)->pluck('id');
            DB::table($table)->whereIn($parentKey, $parentIds)->delete();
        }

        foreach ($tables as $table) {
            $this->deleteTenantScopedTable($table, $tenantId);
        }
    }

    private function deleteTenantScopedTable(string $table, int $tenantId): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'tenant_id')) {
            DB::table($table)->where('tenant_id', $tenantId)->delete();
            return;
        }

        $parentMap = [
            'support_messages' => ['support_tickets', 'support_ticket_id'],
            'notification_dismissals' => ['system_notifications', 'system_notification_id'],
            'pre_alert_receipts' => ['pre_alerts', 'pre_alert_id'],
            'purchase_request_receipts' => ['purchase_requests', 'purchase_request_id'],
        ];

        if (isset($parentMap[$table])) {
            [$parentTable, $parentKey] = $parentMap[$table];
            if (Schema::hasTable($parentTable) && Schema::hasColumn($parentTable, 'tenant_id')) {
                $ids = DB::table($parentTable)->where('tenant_id', $tenantId)->pluck('id');
                DB::table($table)->whereIn($parentKey, $ids)->delete();
            }
        }
    }

    private function uploadedPayload(Request $request): array
    {
        $request->validate(['backup_file' => 'required|file|mimes:json,txt|max:51200']);
        $payload = json_decode((string) file_get_contents($request->file('backup_file')->getRealPath()), true);

        if (! is_array($payload) || ! isset($payload['tables']) || ! is_array($payload['tables'])) {
            abort(422, 'The backup file is not a valid maintenance snapshot.');
        }

        return $payload;
    }

    private function validateConfirmation(Request $request, string $phrase): void
    {
        $request->validate(['confirm_text' => 'required|string']);
        abort_unless(strtoupper(trim($request->confirm_text)) === $phrase, 422, 'Confirmation phrase does not match.');
    }

    private function jsonDownload(array $payload, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    private function withoutForeignKeys(callable $callback): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $callback();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function ensureTenantDefaults(int $tenantId): void
    {
        foreach ([
            ['name' => 'Pending', 'color' => '#f59e0b', 'is_default' => true],
            ['name' => 'Received', 'color' => '#0ea5e9', 'is_default' => false],
            ['name' => 'In Transit', 'color' => '#2563eb', 'is_default' => false],
            ['name' => 'Ready for Pickup', 'color' => '#047857', 'is_default' => false],
            ['name' => 'Out for Delivery', 'color' => '#7c3aed', 'is_default' => false],
            ['name' => 'Delivered', 'color' => '#16a34a', 'is_default' => false],
            ['name' => 'Cancelled', 'color' => '#64748b', 'is_default' => false],
        ] as $index => $status) {
            PackageStatus::firstOrCreate([
                'tenant_id' => $tenantId,
                'slug' => Str::slug($status['name']),
            ], [
                'name' => $status['name'],
                'color' => $status['color'],
                'is_default' => $status['is_default'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach (['General Merchandise', 'Documents', 'Electronics', 'Clothing'] as $name) {
            PackageType::firstOrCreate(['tenant_id' => $tenantId, 'name' => $name], [
                'code' => strtoupper(substr($name, 0, 3)),
                'is_active' => true,
            ]);
        }

        foreach ([
            ['name' => 'Regular', 'min' => 0, 'discount' => 0, 'default' => true],
            ['name' => 'Silver', 'min' => 25, 'discount' => 2.5, 'default' => false],
            ['name' => 'Gold', 'min' => 75, 'discount' => 5, 'default' => false],
        ] as $tier) {
            PricingTier::firstOrCreate([
                'tenant_id' => $tenantId,
                'slug' => Str::slug($tier['name']),
            ], [
                'name' => $tier['name'],
                'min_packages_collected' => $tier['min'],
                'discount_percent' => $tier['discount'],
                'is_default' => $tier['default'],
                'is_active' => true,
            ]);
        }

        TenantSetting::firstOrCreate(['tenant_id' => $tenantId, 'key' => 'loyalty_enabled'], ['value' => '0']);
    }

    private function ensurePlatformDefaults(): void
    {
        foreach ([
            ['key' => 'courier_carriers', 'value' => "Amazon Logistics\nDHL\nFedEx\nUPS\nUSPS", 'group' => 'shipping'],
            ['key' => 'manifest_cargo_companies', 'value' => "DHL Aviation\nFedEx\nUPS Supply Chain\nAmerijet", 'group' => 'shipping'],
            ['key' => 'brand_primary_color', 'value' => '#135fd2', 'group' => 'general'],
            ['key' => 'brand_secondary_color', 'value' => '#073b70', 'group' => 'general'],
        ] as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], [
                'value' => $setting['value'],
                'group' => $setting['group'],
            ]);
        }

        Tenant::query()->chunkById(50, function ($tenants) {
            foreach ($tenants as $tenant) {
                $this->ensureTenantDefaults((int) $tenant->id);
            }
        });
    }
}
