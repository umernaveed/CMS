<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SubscriptionLimitService
{
    public function ensureCanCreate(int $tenantId, string $resource, int $increment = 1): void
    {
        $tenant = Tenant::with('subscription.plan')->findOrFail($tenantId);
        $subscription = $tenant->subscription;
        $plan = optional($subscription)->plan;

        if (! $subscription || ! $plan) {
            throw ValidationException::withMessages([
                'subscription' => 'This tenant does not have an active subscription plan.',
            ]);
        }

        if (! in_array($subscription->status, ['trial', 'active']) || ($subscription->ends_at && $subscription->ends_at->isPast())) {
            throw ValidationException::withMessages([
                'subscription' => 'Your subscription is not active. Please renew or upgrade your plan.',
            ]);
        }

        $limit = $this->limitFor($plan, $resource);
        if ($limit === null) {
            return;
        }

        $current = $this->usageFor($tenantId, $resource);
        if (($current + $increment) > $limit) {
            throw ValidationException::withMessages([
                'subscription' => $this->limitMessage($resource, $limit, $current),
            ]);
        }
    }

    public function usage(int $tenantId): array
    {
        $tenant = Tenant::with('subscription.plan')->findOrFail($tenantId);
        $plan = optional($tenant->subscription)->plan;

        return collect(['users', 'customers', 'packages_month', 'outlets'])
            ->mapWithKeys(fn ($resource) => [$resource => [
                'used' => $this->usageFor($tenantId, $resource),
                'limit' => $plan ? $this->limitFor($plan, $resource) : 0,
                'percent' => $this->percent($this->usageFor($tenantId, $resource), $plan ? $this->limitFor($plan, $resource) : 0),
            ]])
            ->all();
    }

    public function tenantUsageSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('subscription.plan');
        $plan = optional($tenant->subscription)->plan;
        $usage = $this->usage($tenant->id);

        return [
            'plan_name' => optional($plan)->name ?: 'No plan',
            'subscription_status' => optional($tenant->subscription)->status ?: 'none',
            'users' => $usage['users'],
            'customers' => $usage['customers'],
            'packages_month' => $usage['packages_month'],
            'outlets' => $usage['outlets'],
            'storage_days_limit' => optional($plan)->max_storage_days,
        ];
    }

    public function ensureStorageDaysAllowed(int $tenantId, ?int $storageDays): void
    {
        if ($storageDays === null) {
            return;
        }

        $tenant = Tenant::with('subscription.plan')->findOrFail($tenantId);
        $plan = optional($tenant->subscription)->plan;
        $limit = optional($plan)->max_storage_days;

        if ($limit !== null && $storageDays > $limit) {
            throw ValidationException::withMessages([
                'storage_days' => 'Your subscribed package allows up to '.$limit.' storage days per package.',
            ]);
        }
    }

    private function limitFor($plan, string $resource): ?int
    {
        return match ($resource) {
            'users' => $plan->max_users,
            'customers' => $plan->max_customers,
            'packages_month' => $plan->max_packages_month,
            'outlets' => $plan->max_outlets,
            default => null,
        };
    }

    private function usageFor(int $tenantId, string $resource): int
    {
        return match ($resource) {
            'users' => User::where('tenant_id', $tenantId)->whereIn('user_type', ['tenant_admin', 'staff'])->count(),
            'customers' => Customer::where('tenant_id', $tenantId)->count(),
            'packages_month' => Package::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->count(),
            'outlets' => Outlet::where('tenant_id', $tenantId)->count(),
            default => 0,
        };
    }

    private function percent(int $used, ?int $limit): int
    {
        if ($limit === null) {
            return 0;
        }

        if ($limit <= 0) {
            return $used > 0 ? 100 : 0;
        }

        return min(100, (int) round(($used / $limit) * 100));
    }

    private function limitMessage(string $resource, int $limit, int $current): string
    {
        $label = match ($resource) {
            'packages_month' => 'packages this month',
            'users' => 'admin/staff users',
            default => str_replace('_', ' ', $resource),
        };

        return 'Your subscribed package allows '.$limit.' '.$label.'. You already have '.$current.'. Please upgrade your plan to add more.';
    }
}
