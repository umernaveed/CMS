<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'user_type',
        'mailbox',
        'first_name',
        'last_name',
        'full_name',
        'email',
        'phone',
        'phone_2',
        'password',
        'trn',
        'tcc',
        'dob',
        'image',
        'address_line1',
        'address_line2',
        'city',
        'parish',
        'country',
        'postal_code',
        'referral_code',
        'referred_by',
        'email_verified_at',
        'is_active',
        'last_login_at',
        'remember_token',
        'security_code_hash',
        'security_code_regenerated_at',
        'security_code_reusable',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'security_code_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'security_code_regenerated_at' => 'datetime',
        'security_code_reusable' => 'boolean',
    ];

    public function setMailboxAttribute($value): void
    {
        $this->attributes['mailbox'] = Customer::normalizeMailbox($value);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function managedCustomers()
    {
        return $this->hasMany(Customer::class, 'account_manager_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function subscriptionAllowsModule(string $module): bool
    {
        $module = trim($module);
        if ($module === '') {
            return false;
        }

        if (in_array($this->user_type, ['super_admin', 'tenant_admin'], true)) {
            return true;
        }

        $tenant = $this->relationLoaded('tenant') ? $this->tenant : $this->tenant()->with('subscription.plan')->first();
        $subscription = $tenant?->relationLoaded('subscription') ? $tenant->subscription : $tenant?->subscription()->with('plan')->first();
        $plan = $subscription?->relationLoaded('plan') ? $subscription->plan : $subscription?->plan;

        return $plan ? $plan->allowsModule($module) : true;
    }
    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }

        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        if ($this->isUnrestrictedSuperAdmin() || $this->isTenantAdminPermission($permission)) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function hasAnyPermission(array|string $permissions): bool
    {
        $permissions = $this->normalizePermissionNames($permissions);
        if ($permissions === []) {
            return false;
        }

        if ($this->isUnrestrictedSuperAdmin() || $this->tenantAdminHasAnyPermission($permissions)) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissions))
            ->exists();
    }

    public function hasAllPermissions(array|string $permissions): bool
    {
        $permissions = $this->normalizePermissionNames($permissions);
        if ($permissions === []) {
            return false;
        }

        if ($this->isUnrestrictedSuperAdmin() || $this->tenantAdminHasAllPermissions($permissions)) {
            return true;
        }

        $matched = $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissions))
            ->with(['permissions' => fn ($query) => $query->whereIn('name', $permissions)])
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values();

        return $matched->intersect($permissions)->count() === count($permissions);
    }

    private function isUnrestrictedSuperAdmin(): bool
    {
        if ($this->user_type !== 'super_admin') {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->isEmpty();
        }

        return ! $this->roles()->exists();
    }
    private function tenantAdminHasAnyPermission(array $permissions): bool
    {
        return $this->user_type === 'tenant_admin'
            && collect($permissions)->contains(fn ($permission) => $this->isCourierPermission($permission));
    }

    private function tenantAdminHasAllPermissions(array $permissions): bool
    {
        return $this->user_type === 'tenant_admin'
            && collect($permissions)->every(fn ($permission) => $this->isCourierPermission($permission));
    }

    private function isTenantAdminPermission(string $permission): bool
    {
        return $this->user_type === 'tenant_admin' && $this->isCourierPermission($permission);
    }

    private function isCourierPermission(string $permission): bool
    {
        return $permission !== '' && ! str_starts_with($permission, 'superadmin_');
    }
    private function normalizePermissionNames(array|string $permissions): array
    {
        $values = is_array($permissions) ? $permissions : [$permissions];

        return collect($values)
            ->flatMap(fn ($permission) => explode(',', (string) $permission))
            ->map(fn ($permission) => trim($permission))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?: trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->email;
    }
}









