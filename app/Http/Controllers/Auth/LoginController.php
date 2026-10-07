<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showCustomerLoginForm()
    {
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $currentTenant) {
            return redirect()->route('login')->withErrors(['email' => 'Use the courier login page to sign in.']);
        }

        return view('auth.customer-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required',
        ]);

        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $isLocalHost = app()->bound('currentHostIsLocal') && app('currentHostIsLocal');
        $identifier = trim((string) $request->email);
        $users = User::query()
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('mailbox', $identifier);
            })
            ->when($currentTenant, fn ($query) => $query->where('tenant_id', $currentTenant->id))
            ->when(! $currentTenant && ! $isLocalHost, fn ($query) => $query->where('user_type', 'super_admin'))
            ->when(! $currentTenant && $isLocalHost, fn ($query) => $query->whereIn('user_type', ['super_admin', 'tenant_admin', 'staff', 'customer']))
            ->orderByRaw("case user_type when 'super_admin' then 1 when 'tenant_admin' then 2 when 'staff' then 3 when 'customer' then 4 else 5 end")
            ->get();
        $user = $users->first(fn ($candidate) => Hash::check($request->password, $candidate->password));

        if ($user) {
            if (! $user->is_active) {
                return back()->withErrors(['email' => 'This account is inactive.']);
            }

            if ($currentTenant && (int) $user->tenant_id !== (int) $currentTenant->id) {
                return back()->withErrors(['email' => 'This account does not belong to '.$currentTenant->company_name.'.']);
            }

            Auth::login($user, $request->boolean('remember'));
            $this->clearImpersonationSession($request);

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Redirect based on user type
            switch ($user->user_type) {
                case 'super_admin':
                    return redirect()->route($this->superadminLandingRoute($user));
                case 'tenant_admin':
                case 'staff':
                    return redirect()->route($this->courierLandingRoute($user));
                case 'customer':
                    return redirect()->route('customer.dashboard');
                default:
                    Auth::logout();
                    return back()->withErrors(['email' => 'Invalid user type.']);
            }
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function customerLogin(Request $request)
    {
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $currentTenant) {
            return redirect()->route('login')->withErrors(['email' => 'Use the courier login page to sign in.']);
        }

        $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required',
        ]);
        $identifier = trim((string) $request->email);

        $user = User::where('tenant_id', $currentTenant->id)
            ->where('user_type', 'customer')
            ->where(fn ($query) => $query->where('email', $identifier)->orWhere('mailbox', $identifier))
            ->first();

        if ($user && Hash::check($request->password, $user->password)) {
            if (! $user->is_active) {
                return back()->withErrors(['email' => 'This account is inactive.']);
            }

            if ($user->user_type !== 'customer') {
                return back()->withErrors(['email' => 'Courier admin and staff accounts must use the admin login page.']);
            }

            if ((int) $user->tenant_id !== (int) $currentTenant->id) {
                return back()->withErrors(['email' => 'This customer account does not belong to '.$currentTenant->company_name.'.']);
            }

            Auth::login($user, $request->boolean('remember'));
            $this->clearImpersonationSession($request);
            $user->update(['last_login_at' => now()]);

            return redirect()->route('customer.dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $this->clearImpersonationSession($request);

        return redirect()->route('login');
    }

    private function clearImpersonationSession(Request $request): void
    {
        $request->session()->forget([
            'impersonated_by',
            'impersonated_by_email',
            'impersonated_tenant_id',
            'superadmin_open_portal_full_access',
            'support_access_code_id',
            'support_access_permissions',
            'support_access_tenant_id',
            'master_tech_access',
            'master_tech_code_id',
        ]);
    }

    private function courierLandingRoute($user): string
    {
        foreach ([
            'view_dashboard' => 'courier.dashboard',
            'manage_customers' => 'courier.customers.index',
            'manage_packages' => 'courier.packages.index',
            'manage_invoices' => 'courier.invoices.index',
            'manage_payments' => 'courier.finance.payments',
            'manage_transactions' => 'courier.finance.transactions',
            'view_reports' => 'courier.finance.reports',
            'manage_delivery' => 'courier.delivery.index',
            'manage_manifests' => 'courier.manifests.index',
            'manage_tickets' => 'courier.support.index',
            'manage_staff' => 'courier.staff.index',
            'manage_roles' => 'courier.roles.index',
            'manage_settings' => 'courier.settings.index',
            'manage_activity_logs' => 'courier.activity.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'profile.edit';
    }

    private function superadminLandingRoute($user): string
    {
        foreach ([
            'superadmin_dashboard' => 'superadmin.dashboard',
            'superadmin_courier_workspaces' => 'superadmin.tenants',
            'superadmin_courier_workspaces_create' => 'superadmin.tenants.create',
            'superadmin_courier_workspaces_edit' => 'superadmin.tenants',
            'superadmin_courier_workspaces_delete' => 'superadmin.tenants',
            'superadmin_courier_workspaces_open_portal' => 'superadmin.tenants',
            'superadmin_courier_workspaces_credit' => 'superadmin.tenants',
            'superadmin_courier_customers' => 'superadmin.tenants.customers',
            'superadmin_staff_employees' => 'superadmin.staff.employees',
            'superadmin_staff_roles' => 'superadmin.staff.roles-branches',
            'superadmin_logistics' => 'superadmin.logistics.index',
            'superadmin_logistics_overview' => 'superadmin.logistics.index',
            'superadmin_logistics_manifests' => 'superadmin.logistics.manifests',
            'superadmin_logistics_bulk_check_in' => 'superadmin.logistics.bulk-check-in',
            'superadmin_logistics_bulk_upload' => 'superadmin.logistics.bulk-upload',
            'superadmin_logistics_packages' => 'superadmin.logistics.packages',
            'superadmin_logistics_rates' => 'superadmin.logistics.rates',
            'superadmin_logistics_invoices' => 'superadmin.logistics.invoices',
            'superadmin_crm_chat' => 'superadmin.support.index',
            'superadmin_crm_messaging' => 'superadmin.notifications.index',
            'superadmin_payments' => 'superadmin.payments.index',
            'superadmin_courier_billing' => 'superadmin.finance.bills',
            'superadmin_subscription_plans' => 'superadmin.plans.index',
            'superadmin_expense_income' => 'superadmin.finance.entries',
            'superadmin_profit_losses' => 'superadmin.finance.profit-loss',
            'superadmin_billing_rates' => 'superadmin.finance.billing-rates',
            'superadmin_settings' => 'superadmin.settings.index',
            'superadmin_settings_general' => 'superadmin.settings.index',
            'superadmin_settings_registration' => 'superadmin.settings.index',
            'superadmin_settings_email' => 'superadmin.settings.index',
            'superadmin_settings_sms' => 'superadmin.settings.index',
            'superadmin_settings_shipping' => 'superadmin.settings.index',
            'superadmin_settings_payment' => 'superadmin.settings.index',
            'superadmin_maintenance' => 'superadmin.settings.index',
            'superadmin_settings_master_tech' => 'superadmin.settings.index',
            'superadmin_settings_references' => 'superadmin.settings.index',
            'superadmin_reports' => 'superadmin.reports.index',
            'superadmin_audit' => 'superadmin.audit.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'profile.edit';
    }
}
