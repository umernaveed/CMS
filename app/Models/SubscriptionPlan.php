<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;

    public const MODULES = [
        'dashboard' => 'Dashboard',
        'customers' => 'Customers / CRM',
        'packages' => 'Packages, Pre-alerts & Purchase Requests',
        'invoices' => 'Invoices',
        'payments' => 'Payments & Payment Gateway Integration',
        'finance' => 'Income, Expenses & Transactions',
        'reports' => 'Reports',
        'manifests' => 'Manifests',
        'delivery' => 'Delivery Requests',
        'live_chat' => 'Live Chat / Support',
        'staff' => 'Staff Users',
        'branches' => 'Branches / Outlets',
        'settings' => 'Courier Settings & Rate Management',
        'roles' => 'Roles & Permissions',
        'system' => 'System Data Tools',
        'activity_logs' => 'Activity Logs',
    ];

    public const PERMISSION_MODULES = [
        'view_dashboard' => 'dashboard',
        'manage_customers' => 'customers',
        'manage_authorized_users' => 'customers',
        'manage_crm_emails' => 'customers',
        'manage_packages' => 'packages',
        'manage_pre_alerts' => 'packages',
        'manage_purchase_requests' => 'packages',
        'manage_package_exceptions' => 'packages',
        'manage_warehouse' => 'packages',
        'manage_invoices' => 'invoices',
        'manage_payments' => 'payments',
        'manage_billing' => 'finance',
        'manage_transactions' => 'finance',
        'view_reports' => 'reports',
        'manage_manifests' => 'manifests',
        'manage_delivery' => 'delivery',
        'manage_tickets' => 'live_chat',
        'manage_staff' => 'staff',
        'manage_branches' => 'branches',
        'manage_settings' => 'settings',
        'manage_notifications' => 'settings',
        'manage_rates' => 'settings',
        'manage_customs_rates' => 'settings',
        'manage_roles' => 'roles',
        'manage_system' => 'system',
        'manage_activity_logs' => 'activity_logs',
    ];

    protected $fillable = [
        'name',
        'description',
        'monthly_price',
        'yearly_price',
        'billing_model',
        'per_package_base_fee_jmd',
        'package_service_fee_jmd',
        'max_users',
        'max_customers',
        'max_packages_month',
        'max_outlets',
        'max_storage_days',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'per_package_base_fee_jmd' => 'decimal:2',
        'package_service_fee_jmd' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenantSubscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function setFeaturesAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : ['features' => collect(preg_split('/\r\n|\r|\n/', $value))->map(fn ($feature) => trim($feature))->filter()->values()->all()];
        }

        $features = is_array($value) ? $value : [];
        $features['features'] = collect($features['features'] ?? [])->map(fn ($feature) => trim((string) $feature))->filter()->values()->all();
        $features['modules'] = collect($features['modules'] ?? array_keys(self::MODULES))
            ->map(fn ($module) => Str::snake((string) $module))
            ->filter(fn ($module) => array_key_exists($module, self::MODULES))
            ->values()
            ->all();

        $this->attributes['features'] = json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function enabledModules(): array
    {
        $modules = data_get($this->features, 'modules');

        if ($modules === null) {
            return array_keys(self::MODULES);
        }

        return collect((array) $modules)
            ->filter(fn ($module) => array_key_exists($module, self::MODULES))
            ->values()
            ->all();
    }

    public function allowsModule(string $module): bool
    {
        return in_array($module, $this->enabledModules(), true);
    }

    public function allowsPermission(string $permission): bool
    {
        $module = self::PERMISSION_MODULES[$permission] ?? null;

        return $module === null || $this->allowsModule($module);
    }
}
