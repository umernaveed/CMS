<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DeliveryTimeSlot;
use App\Models\Outlet;
use App\Models\PackageStatus;
use App\Models\PackageType;
use App\Models\PricingTier;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSetting;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\WeightRate;
use App\Services\CustomerNotificationService;
use App\Services\SubscriptionGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class TenantRegisterController extends Controller
{
    public function create()
    {
        if (! $this->courierRegistrationEnabled()) {
            return redirect()->route('login')
                ->withErrors(['registration' => 'Courier registration is currently disabled. Please contact the system administrator.']);
        }

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

        $registrationSettings = SystemSetting::where('group', 'registration')->pluck('value', 'key');

        return view('auth.register-tenant', compact('plans', 'registrationSettings'));
    }

    public function store(Request $request)
    {
        if (! $this->courierRegistrationEnabled()) {
            return redirect()->route('login')
                ->withErrors(['registration' => 'Courier registration is currently disabled. Please contact the system administrator.']);
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email|unique:users,email',
            'legal_representative' => 'required|string|max:255',
            'company_code' => 'required|string|max:20|alpha_dash|unique:tenants,subdomain',
            'website_url' => 'nullable|url|max:500',
            'phone' => 'required|string|max:50',
            'whatsapp_number' => 'required|string|max:50',
            'address' => 'required|string|max:1000',
            'billing_address' => 'required|string|max:1000',
            'tax_identifier' => 'required|string|max:100',
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'billing_method' => 'required|string|max:100',
            'website_development_requested' => 'nullable|boolean',
            'admin_name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'company_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120',
            'government_id_documents' => 'required|array|min:1',
            'government_id_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tax_documents' => 'required|array|min:1',
            'tax_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'business_documents' => 'required|array|min:1',
            'business_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'terms' => 'accepted',
        ]);

        $data['subdomain'] = Str::lower($data['company_code']);
        $data['website_development_requested'] = (bool) ($data['website_development_requested'] ?? false);
        $data['onboarding_files'] = $this->storeRegistrationFiles($request);
        $data['password_hash'] = Hash::make($data['password']);
        unset(
            $data['password'],
            $data['password_confirmation'],
            $data['company_logo'],
            $data['government_id_documents'],
            $data['tax_documents'],
            $data['business_documents']
        );

        session(['tenant_registration' => $data]);

        return redirect()->route('tenant.register.payment');
    }

    public function payment(SubscriptionGatewayService $gateways)
    {
        if (! $this->courierRegistrationEnabled()) {
            session()->forget('tenant_registration');

            return redirect()->route('login')
                ->withErrors(['registration' => 'Courier registration is currently disabled. Please contact the system administrator.']);
        }

        $registration = session('tenant_registration');
        if (! $registration) {
            return redirect()->route('tenant.register')->withErrors(['registration' => 'Please complete the registration form first.']);
        }

        $plan = SubscriptionPlan::findOrFail($registration['plan_id']);
        $amount = $registration['billing_cycle'] === 'yearly'
            ? ($plan->yearly_price ?: $plan->monthly_price * 12)
            : $plan->monthly_price;
        $trialDays = 0;
        $todayAmount = $amount;
        $availableGateways = $gateways->registrationGateways();
        $gatewayLabels = $gateways->gatewayLabels();
        $activeGateway = $gateways->activeRegistrationGateway();

        return view('auth.register-payment', compact('registration', 'plan', 'amount', 'trialDays', 'todayAmount', 'availableGateways', 'gatewayLabels', 'activeGateway'));
    }

    public function completePayment(Request $request, SubscriptionGatewayService $gateways)
    {
        if (! $this->courierRegistrationEnabled()) {
            session()->forget('tenant_registration');

            return redirect()->route('login')
                ->withErrors(['registration' => 'Courier registration is currently disabled. Please contact the system administrator.']);
        }

        $registration = session('tenant_registration');
        if (! $registration) {
            return redirect()->route('tenant.register')->withErrors(['registration' => 'Please complete the registration form first.']);
        }

        $data = $request->validate([
            'gateway' => 'required|string',
            'authorize_subscription' => 'accepted',
        ]);

        if (! in_array($data['gateway'], $gateways->registrationGateways(), true)) {
            throw ValidationException::withMessages(['gateway' => 'Please select a configured live payment gateway.']);
        }

        [$tenant, $subscription, $payment] = $this->createPendingRegistration($registration, $gateways, $data['gateway']);
        $admin = User::where('tenant_id', $tenant->id)->where('user_type', 'tenant_admin')->first();
        if ($admin) {
            app(CustomerNotificationService::class)->courierAccountCreated($admin->fresh(['tenant']));
        }

        session()->forget('tenant_registration');

        return redirect()->away($gateways->checkoutUrl($payment, $tenant, $subscription->plan, $data['gateway']));
    }

    public function paymentSuccess(Request $request, TenantPayment $payment)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring([
            'status',
            'payment_status',
            'result',
            'reference',
            'payment_reference',
            'order_reference',
            'amount',
            'currency',
            'receipt_url',
        ]), 403);

        if (! $this->gatewayReturnIsPaid($request, $payment)) {
            $payment->update(['status' => 'failed']);

            return redirect()->to(\Illuminate\Support\Facades\URL::temporarySignedRoute(
                'tenant.register.payment.pending',
                now()->addHours(2),
                $payment
            ))
                ->withErrors(['payment' => 'Payment could not be verified. Please contact support with your payment reference.']);
        }

        return $this->activatePaidSubscription($payment, 'Subscription payment completed successfully.');
    }

    public function paymentPending(Request $request, TenantPayment $payment)
    {
        abort_unless($request->hasValidSignature(), 403);
        $payment->load(['tenant', 'subscription.plan']);

        return view('auth.payment-pending', compact('payment'));
    }

    public function paymentCancel(Request $request, TenantPayment $payment)
    {
        abort_unless($request->hasValidSignature(), 403);
        $payment->update(['status' => 'failed']);

        return redirect()->route('tenant.register')->withErrors(['payment' => 'Payment was cancelled. Please register again or contact support if a payment was already taken.']);
    }

    private function createPendingRegistration(array $registration, SubscriptionGatewayService $gateways, string $gateway): array
    {
        return DB::transaction(function () use ($registration, $gateways, $gateway) {
            $data = $registration;
            if (Tenant::where('email', $data['email'])->exists() || User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email has already been registered. Please sign in or use another email.']);
            }

            if (! empty($data['subdomain'])) {
                if (Tenant::where('subdomain', $data['subdomain'])->exists()) {
                    throw ValidationException::withMessages(['subdomain' => 'This subdomain is already taken. Please choose another one.']);
                }
            }

            $tenant = Tenant::create([
                'company_name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(4)),
                'subdomain' => $data['subdomain'] ?: $this->uniqueSubdomain($data['company_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'logo' => data_get($data, 'onboarding_files.company_logo'),
                'onboarding_documents' => $data['onboarding_files'] ?? [],
                'tax_identifier' => $data['tax_identifier'] ?? null,
                'currency' => 'JMD',
                'timezone' => 'America/Jamaica',
                'address' => $data['address'] ?? null,
                'status' => 'suspended',
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
            ]);

            $outlet = Outlet::create([
                'tenant_id' => $tenant->id,
                'outlet_code' => 'MAIN',
                'name' => $tenant->company_name.' Main Office',
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
                'password' => $data['password_hash'],
                'is_active' => true,
            ]);

            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $data['plan_id'],
                'billing_cycle' => $data['billing_cycle'],
                'status' => 'expired',
                'starts_at' => now()->toDateString(),
                'ends_at' => null,
            ]);

            $subscription->load('plan');
            $planAmount = $data['billing_cycle'] === 'yearly'
                ? ($subscription->plan->yearly_price ?: $subscription->plan->monthly_price * 12)
                : $subscription->plan->monthly_price;
            $amountDue = $planAmount;

            TenantPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'transaction_id' => 'SUB-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'amount' => $amountDue,
                'currency' => 'JMD',
                'payment_method' => $gateway,
                'status' => 'pending',
                'paid_at' => null,
            ]);

            $payment = $tenant->payments()->latest()->first();

            TenantSetting::putValue($tenant->id, 'billing_gateway', $gateway);
            TenantSetting::putValue($tenant->id, 'billing_authorized_at', now()->toDateTimeString());
            foreach ([
                'legal_representative' => $data['legal_representative'],
                'courier_code' => Str::upper($data['company_code']),
                'mailbox_prefix' => Str::upper($data['company_code']),
                'hawb_prefix' => Str::upper($data['company_code']),
                'website_url' => $data['website_url'] ?? '',
                'whatsapp_number' => $data['whatsapp_number'],
                'billing_address' => $data['billing_address'],
                'tax_identifier' => $data['tax_identifier'],
                'preferred_billing_method' => $data['billing_method'],
                'website_development_requested' => $data['website_development_requested'] ? '1' : '0',
                'onboarding_documents' => json_encode($data['onboarding_files'] ?? [], JSON_UNESCAPED_SLASHES),
            ] as $key => $value) {
                TenantSetting::putValue($tenant->id, $key, (string) $value);
            }

            $this->seedTenantDefaults($tenant->id);

            return [$tenant, $subscription, $payment];
        });
    }

    private function courierRegistrationEnabled(): bool
    {
        $value = SystemSetting::where('key', 'courier_registration_enabled')->value('value');

        if ($value === null) {
            return true;
        }

        return ! in_array(strtolower(trim((string) $value)), ['0', 'false', 'off', 'no'], true);
    }

    private function storeRegistrationFiles(Request $request): array
    {
        $token = (string) Str::uuid();
        $stored = [];

        foreach ([
            'company_logo' => false,
            'government_id_documents' => true,
            'tax_documents' => true,
            'business_documents' => true,
        ] as $field => $multiple) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $isPublic = $field === 'company_logo';
            $relativeDirectory = ($isPublic ? 'uploads/tenant-registration/' : 'private/tenant-registration/').$token;
            $directory = $isPublic
                ? public_path($relativeDirectory)
                : storage_path('app/'.$relativeDirectory);
            File::ensureDirectoryExists($directory);
            $files = $multiple ? $request->file($field) : [$request->file($field)];
            $paths = [];

            foreach ($files as $index => $file) {
                $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: $field;
                $filename = $field.'-'.($index + 1).'-'.$base.'.'.$file->getClientOriginalExtension();
                $file->move($directory, $filename);
                $paths[] = $relativeDirectory.'/'.$filename;
            }

            $stored[$field] = $multiple ? $paths : $paths[0];
        }

        return $stored;
    }

    private function activatePaidSubscription(TenantPayment $payment, string $message)
    {
        $user = DB::transaction(function () use ($payment) {
            $payment->load(['tenant', 'subscription.plan']);
            $tenant = $payment->tenant;
            $subscription = $payment->subscription;
            $endsAt = $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();

            $payment->update([
                'status' => 'successful',
                'paid_at' => $payment->paid_at ?? now(),
            ]);

            $tenant->update([
                'status' => 'active',
                'trial_ends_at' => null,
                'subscription_ends_at' => $endsAt,
            ]);

            $subscription->update([
                'status' => 'active',
                'starts_at' => now()->toDateString(),
                'ends_at' => $endsAt->toDateString(),
            ]);

            return User::where('tenant_id', $tenant->id)->where('user_type', 'tenant_admin')->firstOrFail();
        });

        Auth::login($user);
        app(CustomerNotificationService::class)->staffAccountCreated($user->fresh(['tenant', 'outlet']));

        return redirect()->route('courier.dashboard')->with('success', $message);
    }

    private function gatewayReturnIsPaid(Request $request, TenantPayment $payment): bool
    {
        if ($payment->status === 'successful') {
            return true;
        }

        if ($payment->status !== 'pending') {
            return false;
        }

        if (in_array($payment->payment_method, ['test_gateway', 'manual', 'free_trial'], true)) {
            return false;
        }

        $status = strtolower((string) ($request->query('status') ?: $request->query('payment_status') ?: $request->query('result')));
        if (! in_array($status, ['paid', 'successful', 'success', 'completed', 'approved'], true)) {
            return false;
        }

        $reference = (string) ($request->query('reference') ?: $request->query('payment_reference') ?: $request->query('order_reference'));
        if (! hash_equals($payment->transaction_id, $reference)) {
            return false;
        }

        $amount = $request->query('amount');
        if ($amount === null || (float) $amount < (float) $payment->amount) {
            return false;
        }

        $currency = strtoupper((string) $request->query('currency', $payment->currency));
        if ($currency !== strtoupper($payment->currency)) {
            return false;
        }

        $payment->update([
            'receipt_url' => $request->query('receipt_url'),
        ]);

        return true;
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
            ['name' => 'Regular', 'min_packages_collected' => 0, 'discount_percent' => 0, 'is_default' => true],
            ['name' => 'Silver', 'min_packages_collected' => 25, 'discount_percent' => 2.5, 'is_default' => false],
            ['name' => 'Gold', 'min_packages_collected' => 75, 'discount_percent' => 5, 'is_default' => false],
        ] as $tier) {
            PricingTier::firstOrCreate([
                'tenant_id' => $tenantId,
                'slug' => Str::slug($tier['name']),
            ], $tier + ['tenant_id' => $tenantId, 'is_active' => true]);
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
            ], $rate + ['tenant_id' => $tenantId, 'freight_type' => 'air', 'is_active' => true]);
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
}

