<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GenerateAutomaticBackups extends Command
{
    protected $signature = 'backups:automatic {frequency=daily : daily, weekly, or monthly}';

    protected $description = 'Create automatic platform and courier backup snapshots.';

    public function handle(): int
    {
        $frequency = strtolower((string) $this->argument('frequency'));
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $this->error('Frequency must be daily, weekly, or monthly.');

            return self::FAILURE;
        }

        if (SystemSetting::where('key', 'automatic_backups_enabled')->value('value') === '0') {
            $this->info('Automatic backups are disabled in platform settings.');

            return self::SUCCESS;
        }

        $timestamp = now()->format('Ymd-His');
        $disk = Storage::disk('local');
        $basePath = 'backups/'.$frequency.'/'.$timestamp;

        $disk->put($basePath.'/platform.json', json_encode([
            'type' => 'superadmin_platform_backup',
            'frequency' => $frequency,
            'generated_at' => now()->toIso8601String(),
            'tables' => $this->snapshotTables($this->platformTables()),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Tenant::query()->select('id', 'company_name')->chunkById(50, function ($tenants) use ($disk, $basePath, $frequency) {
            foreach ($tenants as $tenant) {
                $disk->put($basePath.'/courier-'.$tenant->id.'.json', json_encode([
                    'type' => 'courier_portal_backup',
                    'frequency' => $frequency,
                    'tenant_id' => $tenant->id,
                    'courier' => $tenant->company_name,
                    'generated_at' => now()->toIso8601String(),
                    'tables' => $this->tenantSnapshot((int) $tenant->id),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        });

        ActivityLog::record('automatic_backup_created', 'data_maintenance', 'Created '.$frequency.' automatic backup snapshot', [
            'path' => $basePath,
        ]);

        $this->info('Automatic '.$frequency.' backup created at storage/app/'.$basePath);

        return self::SUCCESS;
    }

    private function platformTables(): array
    {
        return [
            'system_settings',
            'subscription_plans',
            'permissions',
            'roles',
            'role_permissions',
            'users',
            'tenants',
            'tenant_subscriptions',
            'tenant_payments',
            'tenant_credit_transactions',
            'support_tickets',
            'support_messages',
            'system_notifications',
            'notification_dismissals',
            'superadmin_logistics_manifests',
            'superadmin_logistics_packages',
            'superadmin_logistics_invoices',
            'superadmin_logistics_invoice_items',
            'superadmin_logistics_invoice_payments',
            'superadmin_logistics_rates',
            'superadmin_finance_entries',
            'superadmin_billing_charges',
            'superadmin_bills',
            'superadmin_bill_payments',
            'courier_support_access_codes',
            'master_tech_access_codes',
            'activity_logs',
        ];
    }

    private function tenantTables(): array
    {
        return [
            'tenant_settings',
            'outlets',
            'roles',
            'role_permissions',
            'user_roles',
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
            'support_tickets',
            'support_messages',
            'email_logs',
            'system_notifications',
            'notification_dismissals',
            'activity_logs',
        ];
    }

    private function tenantSnapshot(int $tenantId): array
    {
        $snapshot = [];
        foreach ($this->tenantTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            } elseif ($table === 'role_permissions') {
                $roleIds = DB::table('roles')->where('tenant_id', $tenantId)->pluck('id');
                $query->whereIn('role_id', $roleIds);
            } elseif ($table === 'user_roles') {
                $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id');
                $query->whereIn('user_id', $userIds);
            } elseif ($table === 'support_messages') {
                $ticketIds = DB::table('support_tickets')->where('tenant_id', $tenantId)->pluck('id');
                $query->whereIn('support_ticket_id', $ticketIds);
            } elseif ($table === 'notification_dismissals') {
                $notificationIds = DB::table('system_notifications')->where('tenant_id', $tenantId)->pluck('id');
                $query->whereIn('system_notification_id', $notificationIds);
            } else {
                continue;
            }

            $snapshot[$table] = $query->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    private function snapshotTables(array $tables): array
    {
        $snapshot = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        return $snapshot;
    }
}
