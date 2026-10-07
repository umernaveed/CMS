<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Models\TenantPayment;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Outlet;
use App\Models\PackageStatus;
use App\Models\PackageType;
use App\Models\WeightRate;
use App\Models\ActivityLog;
use App\Models\MasterTechAccessCode;
use App\Models\TenantCreditTransaction;
use App\Models\SuperadminLogisticsInvoice;
use App\Models\SuperadminLogisticsInvoicePayment;
use App\Models\DeliveryArea;
use App\Models\DeliveryTimeSlot;
use App\Models\CustomsOffice;
use App\Models\LoadingPort;
use App\Models\TenantSetting;
use App\Services\SubscriptionLimitService;
use App\Services\SuperadminReportService;
use App\Services\CustomerNotificationService;
use App\Services\WmsSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    public function dashboard(SubscriptionLimitService $limits)
    {
        $tenants = Tenant::with('subscription.plan')->latest()->paginate($this->perPage(request(), 10));
        $tenantCount = Tenant::count();
        $tenantUsage = $tenants->getCollection()->mapWithKeys(fn ($tenant) => [$tenant->id => $limits->tenantUsageSummary($tenant)]);
        $totalRevenue = TenantSubscription::query()
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('tenant_subscriptions.status', 'active')
            ->sum(DB::raw("case when tenant_subscriptions.billing_cycle = 'yearly' then subscription_plans.yearly_price else subscription_plans.monthly_price end"));
        $thisMonthRevenue = DB::table('tenant_payments')
            ->where('status', 'successful')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');
        $shipmentRevenue = SuperadminLogisticsInvoice::query()
            ->where('status', '<>', 'void')
            ->sum('amount_paid_jmd');
        $thisMonthShipmentRevenue = SuperadminLogisticsInvoicePayment::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereHas('invoice', fn ($query) => $query->where('status', '<>', 'void'))
            ->sum('amount_jmd');
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $totalPackages = Package::whereNotNull('date_received')->count();
        $totalUsers = User::count();
        $adminUsers = User::where('user_type', 'super_admin')->count();
        $customerUsers = User::where('user_type', 'customer')->count();
        $activePlans = SubscriptionPlan::where('is_active', true)->count();
        $starterPlans = SubscriptionPlan::where('name', 'Starter')->where('is_active', true)->count();
        $businessPlans = SubscriptionPlan::where('name', 'Business')->where('is_active', true)->count();
        $enterprisePlans = SubscriptionPlan::where('name', 'Enterprise')->where('is_active', true)->count();
        $openTickets = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        $planBreakdown = SubscriptionPlan::withCount(['tenantSubscriptions as active_subscriptions_count' => function ($query) {
                $query->whereIn('status', ['trial', 'active']);
            }])
            ->orderBy('sort_order')
            ->get();
        $recentPayments = TenantPayment::with(['tenant', 'subscription.plan'])
            ->latest()
            ->limit(5)
            ->get();
        $recentTickets = SupportTicket::with(['tenant', 'user', 'latestMessage.user'])
            ->whereNotNull('tenant_id')
            ->where(function ($query) {
                $query
                    ->whereNull('user_id')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereIn('user_type', ['super_admin', 'tenant_admin', 'staff']));
            })
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $revenueMonths = collect(range(5, 0))->map(fn ($offset) => now()->copy()->subMonths($offset));
        $revenueChartLabels = $revenueMonths->map(fn ($month) => $month->format('M'))->all();
        $revenueChartValues = $revenueMonths->map(function ($month) {
            return (float) TenantPayment::where('status', 'successful')
                ->whereMonth('paid_at', $month->month)
                ->whereYear('paid_at', $month->year)
                ->sum('amount');
        })->all();

        return view('superadmin.dashboard', compact(
            'tenants',
            'tenantCount',
            'totalRevenue',
            'thisMonthRevenue',
            'shipmentRevenue',
            'thisMonthShipmentRevenue',
            'activeTenants',
            'suspendedTenants',
            'trialTenants',
            'totalPackages',
            'totalUsers',
            'adminUsers',
            'customerUsers',
            'activePlans',
            'starterPlans',
            'businessPlans',
            'enterprisePlans',
            'openTickets',
            'tenantUsage',
            'planBreakdown',
            'recentPayments',
            'recentTickets',
            'revenueChartLabels',
            'revenueChartValues'
        ));
    }

    public function tenants(Request $request, SubscriptionLimitService $limits)
    {
        $tenantsQuery = $this->filteredTenants($request);
        $perPage = $this->perPage($request, 15, (clone $tenantsQuery)->count());
        $tenants = $tenantsQuery
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
        $tenantUsage = $tenants->getCollection()->mapWithKeys(fn ($tenant) => [$tenant->id => $limits->tenantUsageSummary($tenant)]);
        $tenantCodes = TenantSetting::whereIn('tenant_id', $tenants->getCollection()->pluck('id'))
            ->where('key', 'courier_code')
            ->pluck('value', 'tenant_id');
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('name')->get();

        return view('superadmin.tenants.index', compact('tenants', 'tenantUsage', 'tenantCodes', 'plans'));
    }

    public function exportTenants(Request $request): StreamedResponse
    {
        return $this->tenantsCsv($request, 'superadmin-couriers-export-'.now()->format('Ymd-His').'.csv');
    }

    public function downloadTenants(Request $request): StreamedResponse
    {
        return $this->tenantsCsv($request, 'superadmin-couriers-'.now()->format('Ymd-His').'.csv');
    }

    public function printTenants(Request $request)
    {
        $tenants = $this->filteredTenants($request)->latest()->get();
        $tenantCodes = TenantSetting::whereIn('tenant_id', $tenants->pluck('id'))
            ->where('key', 'courier_code')
            ->pluck('value', 'tenant_id');

        return view('superadmin.tenants.print', compact('tenants', 'tenantCodes'));
    }

    private function tenantsCsv(Request $request, string $filename): StreamedResponse
    {
        $tenants = $this->filteredTenants($request)->latest()->get();
        $tenantCodes = TenantSetting::whereIn('tenant_id', $tenants->pluck('id'))
            ->where('key', 'courier_code')
            ->pluck('value', 'tenant_id');

        ActivityLog::record('exported', 'tenants', 'Exported courier workspaces CSV', [
            'filters' => $request->query(),
            'rows' => $tenants->count(),
        ]);

        return response()->streamDownload(function () use ($tenants, $tenantCodes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['company', 'courier_code', 'slug', 'portal_url', 'customer_signup_url', 'subdomain', 'custom_domain', 'plan', 'status', 'billing_model', 'email', 'phone', 'tax_identifier', 'city', 'parish', 'customers', 'packages', 'created_at']);

            foreach ($tenants as $tenant) {
                $portalUrl = ($tenant->subdomain || $tenant->custom_domain) ? $tenant->portalUrl() : '';

                fputcsv($out, [
                    $tenant->company_name,
                    $tenantCodes[$tenant->id] ?? $tenant->subdomain ?? $tenant->slug,
                    $tenant->slug,
                    $portalUrl,
                    $portalUrl ? $portalUrl.'/register-customer' : '',
                    $tenant->subdomain,
                    $tenant->custom_domain,
                    optional(optional($tenant->subscription)->plan)->name,
                    $tenant->status,
                    $tenant->billing_model ?: optional(optional($tenant->subscription)->plan)->billing_model,
                    $tenant->email,
                    $tenant->phone,
                    $tenant->tax_identifier,
                    $tenant->city,
                    $tenant->parish,
                    $tenant->customers_count,
                    $tenant->packages_count,
                    optional($tenant->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredTenants(Request $request)
    {
        return Tenant::with('subscription.plan')
            ->withCount(['customers', 'packages'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('billing_model'), fn ($query) => $query->where('billing_model', $request->billing_model))
            ->when($request->filled('plan_id'), fn ($query) => $query->whereHas('subscription', fn ($subscription) => $subscription->where('plan_id', $request->integer('plan_id'))))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';

                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('company_name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('subdomain', 'like', $term)
                        ->orWhere('custom_domain', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('tax_identifier', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('parish', 'like', $term)
                        ->orWhereExists(function ($settings) use ($term) {
                            $settings->select(DB::raw(1))
                                ->from('tenant_settings')
                                ->whereColumn('tenant_settings.tenant_id', 'tenants.id')
                                ->whereIn('tenant_settings.key', ['courier_code', 'hawb_prefix', 'mailbox_prefix'])
                                ->where('tenant_settings.value', 'like', $term);
                        })
                        ->orWhereHas('subscription.plan', function ($planQuery) use ($term) {
                            $planQuery->where('name', 'like', $term);
                        });
                });
            });
    }

    public function tenantCustomers(Request $request)
    {
        $customersQuery = $this->filteredTenantCustomers($request);
        $perPage = $this->perPage($request, 20, (clone $customersQuery)->count());
        $customers = $customersQuery
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $tenants = Tenant::orderBy('company_name')->get();

        return view('superadmin.tenants.customers', compact('customers', 'tenants'));
    }

    public function exportTenantCustomers(Request $request): StreamedResponse
    {
        return $this->tenantCustomersCsv($request, 'superadmin-courier-customers-export-'.now()->format('Ymd-His').'.csv');
    }

    public function downloadTenantCustomers(Request $request): StreamedResponse
    {
        return $this->tenantCustomersCsv($request, 'superadmin-courier-customers-'.now()->format('Ymd-His').'.csv');
    }

    public function printTenantCustomers(Request $request)
    {
        $customers = $this->filteredTenantCustomers($request)->latest()->get();

        return view('superadmin.tenants.customers-print', compact('customers'));
    }

    private function tenantCustomersCsv(Request $request, string $filename): StreamedResponse
    {
        $customers = $this->filteredTenantCustomers($request)->latest()->get();

        ActivityLog::record('exported', 'tenant_customers', 'Exported courier customers CSV', [
            'filters' => $request->query(),
            'rows' => $customers->count(),
        ]);

        return response()->streamDownload(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['customer', 'email', 'phone', 'courier', 'mailbox', 'outlet', 'packages', 'invoices', 'balance_jmd', 'status']);

            foreach ($customers as $customer) {
                fputcsv($out, [
                    optional($customer->user)->full_name ?: 'Unnamed Customer',
                    optional($customer->user)->email,
                    optional($customer->user)->phone,
                    optional($customer->tenant)->company_name,
                    $customer->mailbox,
                    optional($customer->outlet)->name,
                    $customer->packages_count,
                    $customer->invoices_count,
                    number_format((float) ($customer->current_balance ?? 0), 2, '.', ''),
                    $customer->is_active ? 'Active' : 'Inactive',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredTenantCustomers(Request $request)
    {
        return Customer::with(['tenant', 'user', 'outlet'])
            ->withCount(['packages', 'invoices'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';

                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('mailbox', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery
                                ->where('full_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        })
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term));
                });
            });
    }

    protected function perPage(Request $request, int $default, ?int $total = null): int
    {
        if ($request->input('per_page') === 'all') {
            return max((int) ($total ?? $default), 1);
        }

        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $default;
    }

    public function payments(Request $request)
    {
        $payments = TenantPayment::with(['tenant', 'subscription.plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->tenant_id))
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();
        $tenants = Tenant::orderBy('company_name')->get();
        $totals = [
            'successful' => TenantPayment::where('status', 'successful')->sum('amount'),
            'pending' => TenantPayment::where('status', 'pending')->sum('amount'),
            'failed' => TenantPayment::where('status', 'failed')->count(),
        ];
        $paymentMonths = collect(range(5, 0))->map(fn ($offset) => now()->copy()->subMonths($offset));
        $paymentChart = [
            'labels' => $paymentMonths->map(fn ($month) => $month->format('M'))->all(),
            'successful' => $paymentMonths->map(fn ($month) => (float) TenantPayment::where('status', 'successful')->whereMonth('paid_at', $month->month)->whereYear('paid_at', $month->year)->sum('amount'))->all(),
            'pending' => $paymentMonths->map(fn ($month) => (float) TenantPayment::where('status', 'pending')->whereMonth('created_at', $month->month)->whereYear('created_at', $month->year)->sum('amount'))->all(),
        ];

        return view('superadmin.payments.index', compact('payments', 'tenants', 'totals', 'paymentChart'));
    }

    public function supportTickets(Request $request)
    {
        $tickets = SupportTicket::with(['tenant', 'user', 'assignee'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();

        return view('superadmin.support.index', compact('tickets'));
    }

    public function createSupportTicket()
    {
        $tenants = Tenant::orderBy('company_name')->get();
        $users = User::orderBy('full_name')->orderBy('email')->get();
        $admins = User::where('user_type', 'super_admin')->orderBy('full_name')->orderBy('email')->get();

        return view('superadmin.support.create', compact('tenants', 'users', 'admins'));
    }

    public function showSupportTicket(SupportTicket $ticket)
    {
        $ticket->load(['tenant', 'user', 'assignee']);

        return view('superadmin.support.show', compact('ticket'));
    }

    public function storeSupportTicket(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'user_id' => 'nullable|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        SupportTicket::create(array_merge($data, [
            'ticket_number' => 'TCK-' . now()->format('YmdHis') . '-' . random_int(100, 999),
            'closed_at' => in_array($data['status'], ['resolved', 'closed']) ? now() : null,
        ]));

        return redirect()->route('superadmin.support.index')->with('success', 'Support ticket created successfully.');
    }

    public function editSupportTicket(SupportTicket $ticket)
    {
        $tenants = Tenant::orderBy('company_name')->get();
        $users = User::orderBy('full_name')->orderBy('email')->get();
        $admins = User::where('user_type', 'super_admin')->orderBy('full_name')->orderBy('email')->get();

        return view('superadmin.support.edit', compact('ticket', 'tenants', 'users', 'admins'));
    }

    public function updateSupportTicket(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'user_id' => 'nullable|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $data['closed_at'] = in_array($data['status'], ['resolved', 'closed']) ? ($ticket->closed_at ?? now()) : null;
        $ticket->update($data);

        return redirect()->route('superadmin.support.index')->with('success', 'Support ticket updated successfully.');
    }

    public function destroySupportTicket(SupportTicket $ticket)
    {
        $ticket->delete();

        return redirect()->route('superadmin.support.index')->with('success', 'Support ticket deleted successfully.');
    }

    public function settings()
    {
        if (! auth()->user()->security_code_hash) {
            $plainCode = Str::upper(Str::random(8));
            auth()->user()->forceFill([
                'security_code_hash' => Hash::make($plainCode),
                'security_code_regenerated_at' => now(),
                'security_code_reusable' => false,
            ])->save();
            session()->flash('fresh_security_code', $plainCode);
        }
        SystemSetting::firstOrCreate(
            ['key' => 'courier_carriers'],
            ['value' => "Amazon Logistics\nDHL\nFedEx\nUPS\nUSPS", 'group' => 'shipping']
        );
        SystemSetting::firstOrCreate(
            ['key' => 'manifest_cargo_companies'],
            ['value' => "DHL Aviation\nFedEx\nUPS Supply Chain\nAmerijet", 'group' => 'shipping']
        );
        SystemSetting::firstOrCreate(
            ['key' => 'courier_registration_enabled'],
            ['value' => '1', 'group' => 'registration']
        );
        foreach ([
            ['key' => 'registration_logo', 'value' => '', 'group' => 'registration'],
            ['key' => 'registration_background_image', 'value' => '', 'group' => 'registration'],
            ['key' => 'registration_primary_color', 'value' => '#2563eb', 'group' => 'registration'],
            ['key' => 'registration_accent_color', 'value' => '#16a34a', 'group' => 'registration'],
            ['key' => 'registration_background_color', 'value' => '#f8fafc', 'group' => 'registration'],
            ['key' => 'registration_platform_title', 'value' => 'Freitora Courier Platform', 'group' => 'registration'],
            ['key' => 'registration_portal_subtitle', 'value' => 'Partner Onboarding Portal', 'group' => 'registration'],
            ['key' => 'registration_secure_label', 'value' => 'Secure', 'group' => 'registration'],
            ['key' => 'registration_step_business', 'value' => 'Business Info', 'group' => 'registration'],
            ['key' => 'registration_step_documents', 'value' => 'Documents', 'group' => 'registration'],
            ['key' => 'registration_step_plan', 'value' => 'Plan & Billing', 'group' => 'registration'],
            ['key' => 'registration_step_review', 'value' => 'Review', 'group' => 'registration'],
            ['key' => 'registration_business_title', 'value' => 'Business Information', 'group' => 'registration'],
            ['key' => 'registration_business_intro', 'value' => "Let's start with the basic details of your courier company.", 'group' => 'registration'],
            ['key' => 'registration_documents_title', 'value' => 'Address & Documents', 'group' => 'registration'],
            ['key' => 'registration_documents_intro', 'value' => 'Provide operational locations and verify your business identity.', 'group' => 'registration'],
            ['key' => 'registration_plan_title', 'value' => 'Subscription & Billing', 'group' => 'registration'],
            ['key' => 'registration_plan_intro', 'value' => 'Choose the SaaS plan that fits your courier volume.', 'group' => 'registration'],
            ['key' => 'registration_review_title', 'value' => 'Review & Checkout', 'group' => 'registration'],
            ['key' => 'registration_review_intro', 'value' => 'Please review your setup details before continuing to secure payment.', 'group' => 'registration'],
            ['key' => 'registration_website_offer_title', 'value' => 'I need a courier website developed.', 'group' => 'registration'],
            ['key' => 'registration_website_offer_text', 'value' => "Don't have a website for your customers to log in? We can build a custom, integrated website for your brand.", 'group' => 'registration'],
            ['key' => 'registration_terms_text', 'value' => 'I agree to the SaaS Terms of Service and Data Processing Agreement.', 'group' => 'registration'],
            ['key' => 'registration_next_button', 'value' => 'Next Step', 'group' => 'registration'],
            ['key' => 'registration_back_button', 'value' => 'Back', 'group' => 'registration'],
            ['key' => 'registration_submit_button', 'value' => 'Continue to Payment', 'group' => 'registration'],
        ] as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
        SystemSetting::firstOrCreate(
            ['key' => 'courier_bill_payment_instructions'],
            ['value' => "Bank transfer / cash deposit details:\nAccount Name:\nBank:\nAccount Number:\nBranch:\nReference: Use the bill number.", 'group' => 'payment']
        );
        SystemSetting::firstOrCreate(
            ['key' => 'automatic_backups_enabled'],
            ['value' => '1', 'group' => 'general']
        );
        SystemSetting::firstOrCreate(
            ['key' => 'public_tenant_signup_url'],
            ['value' => url('/register-tenant'), 'group' => 'general']
        );
        foreach ([
            ['key' => 'smtp_host', 'value' => config('mail.mailers.smtp.host'), 'group' => 'email'],
            ['key' => 'smtp_port', 'value' => (string) config('mail.mailers.smtp.port', 587), 'group' => 'email'],
            ['key' => 'smtp_encryption', 'value' => config('mail.mailers.smtp.encryption') ?: 'none', 'group' => 'email'],
            ['key' => 'smtp_username', 'value' => config('mail.mailers.smtp.username'), 'group' => 'email'],
            ['key' => 'smtp_password', 'value' => config('mail.mailers.smtp.password'), 'group' => 'email'],
            ['key' => 'mail_from_address', 'value' => config('mail.from.address'), 'group' => 'email'],
            ['key' => 'mail_from_name', 'value' => config('mail.from.name'), 'group' => 'email'],
        ] as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
        $settings = SystemSetting::orderBy('group')->orderBy('key')->get()->groupBy('group');
        $customsOffices = CustomsOffice::whereNull('tenant_id')->orderBy('name')->get();
        $loadingPorts = LoadingPort::whereNull('tenant_id')->orderBy('name')->get();

        $masterTechCodes = MasterTechAccessCode::latest()->limit(20)->get();

        return view('superadmin.settings.index', compact('settings', 'customsOffices', 'loadingPorts', 'masterTechCodes'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'settings' => 'nullable|array',
            'settings.*' => 'nullable|string',
            'uploads' => 'nullable|array',
            'uploads.app_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'uploads.company_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'uploads.favicon' => 'nullable|file|mimes:jpg,jpeg,png,webp,ico|max:1024',
            'uploads.registration_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:4096',
            'uploads.registration_background_image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:6144',
        ]);

        foreach ($data['settings'] ?? [] as $key => $value) {
            $group = $this->settingGroupFor($key);
            if (! $this->canManageSettingGroup($group)) {
                continue;
            }

            if ((str_contains($key, 'password') || str_contains($key, 'api_key') || str_contains($key, 'secret')) && blank($value)) {
                continue;
            }

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group]
            );
        }

        foreach (['app_logo', 'company_logo', 'favicon', 'registration_logo', 'registration_background_image'] as $key) {
            $group = str_starts_with($key, 'registration_') ? 'registration' : 'general';
            if (! $this->canManageSettingGroup($group)) {
                continue;
            }

            if (! $request->hasFile("uploads.$key")) {
                continue;
            }

            $file = $request->file("uploads.$key");
            $directory = public_path('uploads/platform');
            File::ensureDirectoryExists($directory);

            $filename = Str::slug(str_replace('_', '-', $key)).'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => 'uploads/platform/'.$filename, 'group' => $group]
            );
        }

        Cache::forget('platform_settings');

        return redirect()->route('superadmin.settings.index')->with('success', 'Settings updated successfully.');
    }

    public function storeCustomsOffice(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'address' => 'nullable|string',
        ]);

        CustomsOffice::updateOrCreate([
            'tenant_id' => null,
            'code' => strtoupper($data['code']),
        ], [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Customs office preset saved.');
    }

    public function storeLoadingPort(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
        ]);

        LoadingPort::updateOrCreate([
            'tenant_id' => null,
            'code' => strtoupper($data['code']),
        ], [
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
        ]);

        return back()->with('success', 'Loading port preset saved.');
    }

    private function settingGroupFor(string $key): string
    {
        if (str_contains($key, 'registration')) {
            return 'registration';
        }

        if (str_starts_with($key, 'smtp_') || str_starts_with($key, 'mail_')) {
            return 'email';
        }

        if (str_starts_with($key, 'sms_')) {
            return 'sms';
        }

        if (
            str_contains($key, 'carrier')
            || str_contains($key, 'courier')
            || str_contains($key, 'shipping')
            || str_contains($key, 'manifest')
            || str_contains($key, 'cargo')
        ) {
            return 'shipping';
        }

        if (
            str_contains($key, 'currency')
            || str_contains($key, 'payment')
            || str_contains($key, 'gateway')
            || str_starts_with($key, 'paypal_')
            || str_starts_with($key, 'dime_')
            || str_starts_with($key, 'amber_pay_')
            || str_starts_with($key, 'subscription_')
        ) {
            return 'payment';
        }

        return 'general';
    }

    private function canManageSettingGroup(string $group): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasPermission('superadmin_settings')) {
            return true;
        }

        $permission = $this->settingPermissionForGroup($group);

        return $permission ? $user->hasPermission($permission) : false;
    }

    private function settingPermissionForGroup(string $group): ?string
    {
        return [
            'general' => 'superadmin_settings_general',
            'registration' => 'superadmin_settings_registration',
            'email' => 'superadmin_settings_email',
            'sms' => 'superadmin_settings_sms',
            'shipping' => 'superadmin_settings_shipping',
            'payment' => 'superadmin_settings_payment',
        ][$group] ?? null;
    }

    public function reports(Request $request, SuperadminReportService $reports)
    {
        $modules = $reports->modules();
        $moduleKey = $reports->selectedModule($request);
        $report = $reports->build($request, $moduleKey);
        $tenants = Tenant::orderBy('company_name')->get();
        $stats = $reports->platformStats($request);

        return view('superadmin.reports.index', compact('modules', 'moduleKey', 'report', 'tenants', 'stats'));
    }

    public function exportReports(Request $request, SuperadminReportService $reports): StreamedResponse
    {
        $moduleKey = $reports->selectedModule($request);
        $report = $reports->build($request, $moduleKey, false);

        ActivityLog::record('exported', 'reports', 'Exported '.$moduleKey.' report', [
            'filters' => $request->query(),
            'rows' => $report['rows']->count(),
        ]);

        return response()->streamDownload(function () use ($report, $request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report['title'].' Report']);
            fputcsv($out, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, ['Filters', json_encode($request->query())]);
            fputcsv($out, []);
            foreach ($report['summary'] as $label => $value) {
                fputcsv($out, [$label, $value]);
            }
            fputcsv($out, []);
            fputcsv($out, $report['columns']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'superadmin-'.$moduleKey.'-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function printReport(Request $request, SuperadminReportService $reports)
    {
        $moduleKey = $reports->selectedModule($request);

        ActivityLog::record('printed', 'reports', 'Printed '.$moduleKey.' report', [
            'filters' => $request->query(),
        ]);

        return view('superadmin.reports.print', [
            'report' => $reports->build($request, $moduleKey, false),
            'filters' => $request->query(),
        ]);
    }

    public function audit(Request $request)
    {
        $logs = $this->filteredAuditLogs($request)
            ->latest('created_at')
            ->paginate($this->perPage(request(), 30))
            ->withQueryString();
        $tenants = Tenant::orderBy('company_name')->get();
        $modules = ActivityLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');

        return view('superadmin.audit.index', compact('logs', 'tenants', 'modules'));
    }

    public function exportAudit(Request $request): StreamedResponse
    {
        $logs = $this->filteredAuditLogs($request)->latest('created_at')->get();

        ActivityLog::record('exported', 'audit', 'Exported audit trail', [
            'filters' => $request->query(),
            'rows' => $logs->count(),
        ]);

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['when', 'courier', 'user', 'module', 'action', 'description', 'ip_address', 'route', 'path']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    optional($log->created_at)->format('Y-m-d H:i:s'),
                    optional($log->tenant)->company_name ?: 'Platform',
                    optional($log->user)->full_name ?: optional($log->user)->email ?: 'System',
                    $log->module,
                    $log->action,
                    $log->description,
                    $log->ip_address,
                    $log->new_data['route'] ?? null,
                    $log->new_data['path'] ?? null,
                ]);
            }

            fclose($out);
        }, 'audit-trail-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function printAudit(Request $request)
    {
        ActivityLog::record('printed', 'audit', 'Printed audit trail', [
            'filters' => $request->query(),
        ]);

        return view('superadmin.audit.print', [
            'logs' => $this->filteredAuditLogs($request)->latest('created_at')->get(),
            'filters' => $request->query(),
        ]);
    }

    private function filteredAuditLogs(Request $request)
    {
        return ActivityLog::with(['tenant', 'user'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->module))
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->action.'%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('description', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            });
    }

    public function createTenant()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();
        return view('superadmin.tenants.create', compact('plans'));
    }

    public function showTenant(Tenant $tenant, SubscriptionLimitService $limits)
    {
        $tenant->load(['subscription.plan', 'users', 'outlets', 'creditTransactions' => fn ($query) => $query->latest()->limit(10)]);
        $usage = $limits->tenantUsageSummary($tenant);
        $onboardingDocuments = $this->tenantOnboardingDocuments($tenant);

        return view('superadmin.tenants.show', compact('tenant', 'usage', 'onboardingDocuments'));
    }

    public function downloadTenantOnboardingDocument(Tenant $tenant, string $field, int $index)
    {
        abort_unless(in_array($field, ['government_id_documents', 'tax_documents', 'business_documents'], true), 404);

        $documents = $this->tenantOnboardingDocuments($tenant);
        $path = $documents[$field][$index] ?? null;
        abort_unless(is_string($path) && $this->isSafeOnboardingPath($path), 404);

        $absolutePath = storage_path('app/'.$path);
        abort_unless(File::exists($absolutePath), 404);

        return response()->download($absolutePath, basename($path));
    }

    public function viewTenantOnboardingDocument(Tenant $tenant, string $field, int $index)
    {
        [$path, $absolutePath] = $this->tenantOnboardingDocumentPath($tenant, $field, $index);

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function printTenantOnboardingDocument(Tenant $tenant, string $field, int $index)
    {
        [$path] = $this->tenantOnboardingDocumentPath($tenant, $field, $index);

        return view('superadmin.tenants.document-print', compact('tenant', 'field', 'index', 'path'));
    }

    public function uploadTenantOnboardingDocument(Request $request, Tenant $tenant, string $field)
    {
        abort_unless(in_array($field, ['government_id_documents', 'tax_documents', 'business_documents'], true), 404);

        $data = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $documents = $this->tenantOnboardingDocuments($tenant);
        $documents[$field] = $documents[$field] ?? [];
        $file = $data['document'];
        $directory = 'private/tenant-registration/'.$tenant->id;
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        $documents[$field][] = $file->storeAs($directory, $filename);

        $tenant->forceFill(['onboarding_documents' => $documents])->save();
        TenantSetting::setValue($tenant->id, 'onboarding_documents', json_encode($documents));

        ActivityLog::record('uploaded', 'courier_documents', 'Uploaded registration document for '.$tenant->company_name, [
            'tenant_id' => $tenant->id,
            'field' => $field,
        ]);

        return back()->with('success', 'Registration document uploaded.');
    }


    public function creditTenant(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'action' => 'nullable|in:add,subtract',
            'amount_jmd' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ]);

        $action = $data['action'] ?? 'add';
        $amount = round((float) $data['amount_jmd'], 2);

        DB::transaction(function () use ($tenant, $data, $action, $amount) {
            $walletTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $currentBalance = round((float) $walletTenant->credit_balance_jmd, 2);

            if ($action === 'subtract' && $amount > $currentBalance) {
                throw ValidationException::withMessages([
                    'amount_jmd' => 'Adjustment cannot subtract more than the available courier credit balance.',
                ]);
            }

            $signedAmount = $action === 'subtract' ? -$amount : $amount;
            $balance = round($currentBalance + $signedAmount, 2);
            $type = $action === 'subtract' ? 'adjustment' : 'credit';

            $walletTenant->update(['credit_balance_jmd' => $balance]);

            TenantCreditTransaction::create([
                'tenant_id' => $walletTenant->id,
                'type' => $type,
                'amount_jmd' => $signedAmount,
                'balance_after_jmd' => $balance,
                'reference_number' => Str::upper($action === 'subtract' ? 'ADJ-' : 'CR-').now()->format('YmdHis'),
                'notes' => $data['notes'] ?? ($action === 'subtract' ? 'System Admin credit adjustment' : 'System Admin credit'),
                'created_by' => auth()->id(),
            ]);
        });

        ActivityLog::record($action === 'subtract' ? 'adjusted' : 'credited', 'courier_credits', ($action === 'subtract' ? 'Adjusted ' : 'Credited ').$tenant->company_name.' courier account', [
            'tenant_id' => $tenant->id,
            'amount_jmd' => $action === 'subtract' ? -$amount : $amount,
        ]);

        return back()->with('success', $action === 'subtract' ? 'Courier credit adjusted.' : 'Courier credit added.');
    }

    public function tenantWallet(Request $request, Tenant $tenant)
    {
        $transactions = $this->filteredTenantWalletTransactions($request, $tenant)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->withQueryString();

        return view('superadmin.tenants.wallet', compact('tenant', 'transactions'));
    }

    public function printTenantWallet(Request $request, Tenant $tenant)
    {
        $transactions = $this->filteredTenantWalletTransactions($request, $tenant)->latest()->get();

        return view('superadmin.tenants.wallet-print', compact('tenant', 'transactions'));
    }

    public function downloadTenantWallet(Request $request, Tenant $tenant)
    {
        $transactions = $this->filteredTenantWalletTransactions($request, $tenant)->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'type', 'reference', 'notes', 'amount_jmd', 'balance_after_jmd']);
            foreach ($transactions as $transaction) {
                fputcsv($out, [
                    optional($transaction->created_at)->format('Y-m-d H:i:s'),
                    $transaction->type,
                    $transaction->reference_number,
                    $transaction->notes,
                    number_format((float) $transaction->amount_jmd, 2, '.', ''),
                    number_format((float) $transaction->balance_after_jmd, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'courier-wallet-'.$tenant->slug.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filteredTenantWalletTransactions(Request $request, Tenant $tenant)
    {
        $request->validate([
            'q' => 'nullable|string|max:150',
            'type' => 'nullable|in:credit,debit,applied,adjustment',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        return $tenant->creditTransactions()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested->where('reference_number', 'like', $term)->orWhere('notes', 'like', $term));
            });
    }

    private function tenantOnboardingDocuments(Tenant $tenant): array
    {
        $documents = $tenant->onboarding_documents;
        if (! is_array($documents)) {
            $documents = json_decode((string) TenantSetting::getValue($tenant->id, 'onboarding_documents', '[]'), true);
        }

        return is_array($documents) ? $documents : [];
    }

    private function tenantOnboardingDocumentPath(Tenant $tenant, string $field, int $index): array
    {
        abort_unless(in_array($field, ['government_id_documents', 'tax_documents', 'business_documents'], true), 404);

        $documents = $this->tenantOnboardingDocuments($tenant);
        $path = $documents[$field][$index] ?? null;
        abort_unless(is_string($path) && $this->isSafeOnboardingPath($path), 404);

        $absolutePath = storage_path('app/'.$path);
        abort_unless(File::exists($absolutePath), 404);

        return [$path, $absolutePath];
    }


    private function isSafeOnboardingPath(string $path): bool
    {
        return ! str_contains($path, '..')
            && str_starts_with(str_replace('\\', '/', $path), 'private/tenant-registration/');
    }

    public function openTenantPortal(Request $request, Tenant $tenant)
    {
        $superAdmin = $request->user();
        $courierAdmin = $tenant->users()
            ->where('user_type', 'tenant_admin')
            ->where('is_active', true)
            ->oldest()
            ->first();

        if (! $courierAdmin) {
            return back()->withErrors(['portal' => 'This courier does not have an active courier admin account to open.']);
        }

        ActivityLog::record('impersonated', 'couriers', 'Opened '.$tenant->company_name.' courier portal', [
            'tenant_id' => $tenant->id,
            'tenant_admin_id' => $courierAdmin->id,
            'tenant_admin_email' => $courierAdmin->email,
        ]);

        Auth::login($courierAdmin);
        $request->session()->put('impersonated_by', $superAdmin->id);
        $request->session()->put('impersonated_by_email', $superAdmin->email);
        $request->session()->put('impersonated_tenant_id', $tenant->id);
        $request->session()->put('superadmin_open_portal_full_access', true);
        $request->session()->regenerateToken();

        $courierAdmin->update(['last_login_at' => now()]);

        $courierPath = route($this->courierLandingRoute($courierAdmin), [], false);

        return redirect()->to(rtrim($tenant->portalUrl(), '/').$courierPath);
    }

    public function returnToSuperadmin(Request $request)
    {
        $superAdminId = $request->session()->get('impersonated_by');
        $tenantId = $request->session()->get('impersonated_tenant_id');

        abort_unless($superAdminId, 403);

        $superAdmin = User::where('user_type', 'super_admin')
            ->where('is_active', true)
            ->findOrFail($superAdminId);

        ActivityLog::record('returned', 'couriers', 'Returned to System Admin portal', [
            'tenant_id' => $tenantId,
            'courier_user_id' => $request->user()?->id,
            'superadmin_id' => $superAdmin->id,
        ]);

        Auth::login($superAdmin);
        $request->session()->forget([
            'impersonated_by',
            'impersonated_by_email',
            'impersonated_tenant_id',
            'superadmin_open_portal_full_access',
        ]);
        $request->session()->regenerateToken();

        $superadminPath = route($this->superadminLandingRoute($superAdmin), [], false);

        return redirect()->to(rtrim(config('app.url'), '/').$superadminPath)
            ->with('success', 'Returned to System Admin.');
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'tax_identifier' => 'nullable|string|max:100',
            'package_service_fee_jmd' => 'nullable|numeric|min:0',
            'billing_model' => 'required|in:subscription,per_package,hybrid',
            'expected_monthly_packages' => 'nullable|integer|min:0',
            'per_package_base_fee_jmd' => 'nullable|numeric|min:0',
            'subdomain' => 'nullable|string|max:100|unique:tenants,subdomain',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'status' => 'required|in:trial,active,suspended,expired,cancelled',
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'admin_name' => 'required|string|max:255',
            'admin_password' => 'required|string|min:8',
        ]);

        $tenant = DB::transaction(function () use ($data) {
            $plan = SubscriptionPlan::findOrFail($data['plan_id']);
            $tenant = Tenant::create([
                'company_name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']) . '-' . Str::lower(Str::random(4)),
                'subdomain' => $data['subdomain'] ?: $this->uniqueSubdomain($data['company_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'tax_identifier' => $data['tax_identifier'] ?? null,
                'package_service_fee_jmd' => $data['package_service_fee_jmd'] ?? $plan->package_service_fee_jmd ?? 0,
                'billing_model' => $data['billing_model'] ?: ($plan->billing_model ?? 'subscription'),
                'expected_monthly_packages' => $data['expected_monthly_packages'] ?? null,
                'per_package_base_fee_jmd' => $data['per_package_base_fee_jmd'] ?? $plan->per_package_base_fee_jmd ?? 0,
                'currency' => 'JMD',
                'timezone' => 'America/Jamaica',
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'parish' => $data['parish'] ?? null,
                'status' => $data['status'],
                'trial_ends_at' => now()->addDays(14),
                'subscription_ends_at' => now()->addYear(),
            ]);

            $outlet = Outlet::create([
                'tenant_id' => $tenant->id,
                'outlet_code' => 'MAIN',
                'name' => $tenant->company_name . ' Main Office',
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'parish' => $tenant->parish,
                'is_main' => true,
                'is_active' => true,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'user_type' => 'tenant_admin',
                'full_name' => $data['admin_name'],
                'email' => $data['email'],
                'phone' => $tenant->phone,
                'password' => Hash::make($data['admin_password']),
                'is_active' => true,
            ]);

            TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $data['billing_cycle'],
                'status' => $data['status'] === 'trial' ? 'trial' : 'active',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
            ]);

            $this->seedTenantDefaults($tenant->id);
            app(CustomerNotificationService::class)->staffAccountCreated($user->fresh(['tenant', 'outlet']));

            return $tenant;
        });

        app(WmsSyncService::class)->syncTenant($tenant->fresh());

        return redirect()->route('superadmin.tenants')->with('success', 'Courier and courier admin created successfully.');
    }

    public function editTenant(Tenant $tenant)
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();
        $tenant->load('subscription');
        return view('superadmin.tenants.edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:50',
            'tax_identifier' => 'nullable|string|max:100',
            'package_service_fee_jmd' => 'nullable|numeric|min:0',
            'billing_model' => 'required|in:subscription,per_package,hybrid',
            'expected_monthly_packages' => 'nullable|integer|min:0',
            'per_package_base_fee_jmd' => 'nullable|numeric|min:0',
            'subdomain' => 'nullable|string|max:100|unique:tenants,subdomain,' . $tenant->id,
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'status' => 'required|in:trial,active,suspended,expired,cancelled',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'billing_cycle' => 'nullable|in:monthly,yearly',
        ]);

        $selectedPlan = ! empty($data['plan_id']) ? SubscriptionPlan::find($data['plan_id']) : optional($tenant->subscription)->plan;

        $tenant->update([
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'tax_identifier' => $data['tax_identifier'] ?? null,
            'package_service_fee_jmd' => $data['package_service_fee_jmd'] ?? optional($selectedPlan)->package_service_fee_jmd ?? 0,
            'billing_model' => $data['billing_model'] ?: (optional($selectedPlan)->billing_model ?? 'subscription'),
            'expected_monthly_packages' => $data['expected_monthly_packages'] ?? null,
            'per_package_base_fee_jmd' => $data['per_package_base_fee_jmd'] ?? optional($selectedPlan)->per_package_base_fee_jmd ?? 0,
            'subdomain' => $data['subdomain'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'parish' => $data['parish'] ?? null,
            'status' => $data['status'],
        ]);

        if (! empty($data['plan_id'])) {
            $tenant->subscription()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id' => $data['plan_id'],
                    'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                    'status' => $data['status'] === 'trial' ? 'trial' : 'active',
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addYear()->toDateString(),
                ]
            );
        }

        app(WmsSyncService::class)->syncTenant($tenant->fresh());

        return redirect()->route('superadmin.tenants')->with('success', 'Courier updated successfully.');
    }

    public function destroyTenant(Tenant $tenant)
    {
        $tenant->delete();
        return redirect()->route('superadmin.tenants')->with('success', 'Courier deleted successfully.');
    }

    private function seedTenantDefaults(int $tenantId): void
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
            ['min_weight' => 0, 'max_weight' => 2, 'price_jmd' => 900, 'express_price_jmd' => 1300],
            ['min_weight' => 2.01, 'max_weight' => 5, 'price_jmd' => 1600, 'express_price_jmd' => 2200],
            ['min_weight' => 5.01, 'max_weight' => 10, 'price_jmd' => 2800, 'express_price_jmd' => 3600],
            ['min_weight' => 10.01, 'max_weight' => 999, 'price_jmd' => 4500, 'express_price_jmd' => 5800],
        ] as $rate) {
            WeightRate::firstOrCreate([
                'tenant_id' => $tenantId,
                'min_weight' => $rate['min_weight'],
                'max_weight' => $rate['max_weight'],
                'freight_type' => 'air',
            ], array_merge($rate, ['tenant_id' => $tenantId, 'freight_type' => 'air', 'is_active' => true]));
        }

        foreach ([
            ['name' => 'Kingston Metro', 'base_cost' => 700],
            ['name' => 'Portmore', 'base_cost' => 950],
            ['name' => 'Spanish Town', 'base_cost' => 1200],
        ] as $areaData) {
            $area = DeliveryArea::firstOrCreate(['tenant_id' => $tenantId, 'name' => $areaData['name']], [
                'base_cost' => $areaData['base_cost'],
                'per_km_cost' => 0,
                'is_active' => true,
            ]);

            foreach ([1, 2, 3, 4, 5] as $day) {
                DeliveryTimeSlot::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'area_id' => $area->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '14:00:00',
                ], [
                    'max_requests' => 20,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function uniqueSubdomain(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'courier';
        $subdomain = $base;
        $counter = 2;

        while (Tenant::where('subdomain', $subdomain)->exists()) {
            $subdomain = $base.'-'.$counter++;
        }

        return $subdomain;
    }

    private function courierLandingRoute(User $user): string
    {
        foreach ([
            'view_dashboard' => 'courier.dashboard',
            'manage_customers' => 'courier.customers.index',
            'manage_tickets' => 'courier.support.index',
            'manage_crm_emails' => 'courier.crm.emails.create',
            'manage_authorized_users' => 'courier.authorized-users.index',
            'manage_packages' => 'courier.packages.index',
            'manage_pre_alerts' => 'courier.pre-alerts.index',
            'manage_purchase_requests' => 'courier.purchase-requests.index',
            'manage_package_exceptions' => 'courier.packages.aging',
            'manage_warehouse' => 'courier.warehouse.unmanifest',
            'manage_invoices' => 'courier.invoices.index',
            'manage_payments' => 'courier.finance.payments',
            'manage_transactions' => 'courier.finance.transactions',
            'manage_billing' => 'courier.finance.bills',
            'view_reports' => 'courier.finance.reports',
            'manage_delivery' => 'courier.delivery.index',
            'manage_manifests' => 'courier.manifests.index',
            'manage_staff' => 'courier.staff.index',
            'manage_roles' => 'courier.roles.index',
            'manage_branches' => 'courier.outlets.index',
            'manage_settings' => 'courier.settings.index',
            'manage_rates' => 'courier.weights.index',
            'manage_customs_rates' => 'courier.customs-rates.index',
            'manage_activity_logs' => 'courier.activity.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'profile.edit';
    }

    private function superadminLandingRoute(User $user): string
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


