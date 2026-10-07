<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ClientNotification;
use App\Models\DeliveryArea;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Manifest;
use App\Models\Outlet;
use App\Models\Package;
use App\Models\PackageStatus;
use App\Models\PackageType;
use App\Models\Permission;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PricingTier;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\SuperadminLogisticsManifest;
use App\Models\Tenant;
use App\Models\TenantCreditTransaction;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WeightRate;
use App\Services\LoyaltyService;
use App\Services\CourierNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private User $superAdmin;
    private User $tenantAdmin;
    private User $staff;
    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::create([
            'company_name' => 'Smoke Courier',
            'slug' => 'smoke-courier',
            'subdomain' => 'smoke',
            'email' => 'admin@smoke.test',
            'status' => 'active',
            'tax_identifier' => 'TRN-SMOKE',
            'package_service_fee_jmd' => 25,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Smoke Full Access',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => ['modules' => array_keys(SubscriptionPlan::MODULES)],
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $outlet = Outlet::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Branch',
            'outlet_code' => 'MAIN',
            'address' => '123 Smoke Street',
            'is_main' => true,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Default',
            'slug' => 'default',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@smoke.test',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'user_type' => 'tenant_admin',
            'email' => 'tenant-admin@smoke.test',
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'user_type' => 'staff',
            'email' => 'staff@smoke.test',
            'is_active' => true,
        ]);
        $staffRole = Role::where('name', 'staff')->firstOrFail();
        $this->staff->roles()->attach($staffRole->id);

        $this->customerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet->id,
            'user_type' => 'customer',
            'email' => 'customer@smoke.test',
            'mailbox' => 'SMK001',
            'is_active' => true,
        ]);
        $customerRole = Role::where('name', 'customer')->firstOrFail();
        $this->customerUser->roles()->attach($customerRole->id);

        Customer::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customerUser->id,
            'mailbox' => 'SMK001',
            'outlet_id' => $outlet->id,
            'pricing_tier_id' => $tier->id,
            'is_active' => true,
        ]);
    }

    public function test_public_auth_and_registration_pages_render_without_server_errors(): void
    {
        foreach ([
            route('login'),
            route('customer.login'),
            route('password.request'),
            route('tenant.register'),
            route('customer.register', ['courier' => $this->tenant->id]),
        ] as $url) {
            $this->assertSmokeOk($this->get($url), $url);
        }
    }

    public function test_registration_page_has_stable_first_paint_and_initialization_fallback(): void
    {
        $this->get(route('tenant.register'))
            ->assertOk()
            ->assertSee('registration-loading', false)
            ->assertSee('html.registration-loading body', false)
            ->assertSee("document.documentElement.classList.remove('registration-loading')", false);
    }

    public function test_customer_submission_notifications_are_tenant_and_outlet_aware(): void
    {
        $otherOutlet = Outlet::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Branch',
            'outlet_code' => 'OTHER',
            'is_main' => false,
            'is_active' => true,
        ]);
        $otherStaff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $otherOutlet->id,
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        app(CourierNotificationService::class)->notify(
            $this->tenant->id,
            $this->customerUser->outlet_id,
            'delivery_request',
            'New Delivery Request',
            'A customer submitted a delivery request.',
            route('courier.delivery.index')
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->tenantAdmin->id,
            'type' => 'delivery_request',
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staff->id,
            'type' => 'delivery_request',
            'is_read' => false,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherStaff->id,
            'type' => 'delivery_request',
        ]);

        $this->actingAs($this->staff);
        app(CourierNotificationService::class)->markRead('delivery_request');

        $this->assertSame(0, ClientNotification::where('user_id', $this->staff->id)->where('is_read', false)->count());
        $this->assertSame(1, ClientNotification::where('user_id', $this->tenantAdmin->id)->where('is_read', false)->count());
    }

    public function test_calculator_uses_invoice_weight_rounding_and_only_matching_rates(): void
    {
        WeightRate::where('tenant_id', $this->tenant->id)->where('freight_type', 'air')->delete();
        TenantSetting::updateOrCreate([
            'tenant_id' => $this->tenant->id,
            'key' => 'billing_weight_mode',
        ], [
            'value' => 'round_up',
        ]);
        WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'pricing_tier_id' => $this->customerUser->customer->pricing_tier_id,
            'freight_type' => 'air',
            'min_weight' => 1,
            'max_weight' => 1,
            'minimum_billable_measure' => 0,
            'price_jmd' => 500,
            'express_price_jmd' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->customerUser)
            ->get(route('calculator', [
                'freight_type' => 'air',
                'weight' => 0.5,
                'delivery_type' => 'pickup',
            ]))
            ->assertOk()
            ->assertSee('JMD 500.00')
            ->assertSee('Billable: 1.00 lb')
            ->assertSee('Weight billing: Round up')
            ->assertDontSee('JMD 325.00');

        $this->actingAs($this->customerUser)
            ->get(route('calculator', [
                'freight_type' => 'air',
                'weight' => 1.2,
                'delivery_type' => 'pickup',
            ]))
            ->assertOk()
            ->assertSee('Rate unavailable.')
            ->assertSee('No active rate is configured for 2.00 lb.')
            ->assertDontSee('JMD 1,300.00');
    }

    public function test_package_creation_prices_from_the_rounded_billing_weight(): void
    {
        $customer = $this->customerUser->customer;
        WeightRate::where('tenant_id', $this->tenant->id)->where('freight_type', 'air')->delete();
        TenantSetting::updateOrCreate([
            'tenant_id' => $this->tenant->id,
            'key' => 'billing_weight_mode',
        ], [
            'value' => 'round_up',
        ]);
        WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'pricing_tier_id' => $customer->pricing_tier_id,
            'freight_type' => 'air',
            'min_weight' => 1,
            'max_weight' => 1,
            'minimum_billable_measure' => 0,
            'price_jmd' => 500,
            'express_price_jmd' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.packages.store'), [
                'customer_id' => $customer->id,
                'weight' => 0.5,
                'freight_type' => 'air',
                'delivery_type' => 'pickup',
            ])
            ->assertRedirect(route('courier.packages.index'));

        $package = Package::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertSame(0.5, (float) $package->weight);
        $this->assertSame(500.0, (float) $package->price_jmd);
    }

    public function test_courier_and_system_admin_can_view_and_export_wallet_history(): void
    {
        $this->tenant->update(['credit_balance_jmd' => 750]);
        TenantCreditTransaction::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'credit',
            'amount_jmd' => 1000,
            'balance_after_jmd' => 1000,
            'notes' => 'System Admin credit',
            'created_by' => $this->superAdmin->id,
        ]);
        TenantCreditTransaction::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'applied',
            'amount_jmd' => -250,
            'balance_after_jmd' => 750,
            'reference_number' => 'BILL-TEST',
            'notes' => 'Applied to bill',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.finance.wallet'))
            ->assertOk()
            ->assertSee('JMD 750.00')
            ->assertSee('BILL-TEST');
        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.finance.wallet.download', ['type' => 'applied']))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.tenants.wallet', $this->tenant))
            ->assertOk()
            ->assertSee('System Admin credit');
        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.tenants.wallet.download', [$this->tenant, 'type' => 'credit']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_sea_rates_convert_cuft_and_cbm_and_local_areas_store_pricing_mode(): void
    {
        $seaRate = WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'region' => 'China',
            'volume_unit' => 'cbm',
            'freight_type' => 'sea',
            'min_weight' => 1,
            'max_weight' => 2,
            'minimum_billable_measure' => 1,
            'price_jmd' => 1000,
            'express_price_jmd' => 0,
            'is_active' => true,
        ]);

        $this->assertSame('CBM', $seaRate->unitLabel());
        $this->assertEqualsWithDelta(1.0, $seaRate->billableMeasure(35.3147), 0.001);
        $this->assertSame(1000.0, $seaRate->priceFor(35.3147));
        $this->assertEqualsWithDelta(1982.18, $seaRate->priceFor(70.0), 0.01);

        $cuftSeaRate = WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'region' => 'China Sea',
            'volume_unit' => 'cuft',
            'freight_type' => 'sea',
            'min_weight' => 8,
            'max_weight' => null,
            'minimum_billable_measure' => 8,
            'price_jmd' => 3812.80,
            'express_price_jmd' => 0,
            'is_active' => true,
        ]);

        $this->assertSame('cu.ft', $cuftSeaRate->unitLabel());
        $this->assertEqualsWithDelta(30502.40, $cuftSeaRate->priceFor(4), 0.01);
        $this->assertEqualsWithDelta(45753.60, $cuftSeaRate->priceFor(12), 0.01);

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.delivery-areas.store'), [
                'name' => 'Main Pickup',
                'base_cost' => 500,
                'per_km_cost' => 100,
                'pricing_mode' => 'distance',
                'geofence_type' => 'none',
                'center_lat' => 18.0179,
                'center_lng' => -76.8099,
                'is_active' => 1,
            ])
            ->assertRedirect(route('courier.delivery-areas.index'));

        $this->assertDatabaseHas('delivery_areas', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Pickup',
            'pricing_mode' => 'distance',
        ]);
    }

    public function test_outlet_can_be_created_when_main_checkbox_is_unchecked(): void
    {
        $response = $this->actingAs($this->tenantAdmin)->post(route('courier.outlets.store'), [
            'outlet_code' => 'SECOND',
            'name' => 'Second Branch',
            'rate_group_mode' => 'custom',
        ]);

        $response->assertRedirect(route('courier.outlets.index'));
        $this->assertDatabaseHas('outlets', [
            'tenant_id' => $this->tenant->id,
            'outlet_code' => 'SECOND',
            'is_main' => false,
        ]);
    }

    public function test_customer_and_staff_cannot_be_assigned_to_another_tenant_outlet(): void
    {
        $otherTenant = Tenant::create([
            'company_name' => 'Other Courier',
            'slug' => 'other-courier',
            'subdomain' => 'other-courier',
            'email' => 'admin@other-courier.test',
            'status' => 'active',
        ]);
        $otherOutlet = Outlet::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Main',
            'outlet_code' => 'OTHER',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->tenantAdmin)->post(route('courier.customers.store'), [
            'full_name' => 'Cross Tenant Customer',
            'email' => 'cross-customer@test.local',
            'password' => 'password123',
            'outlet_id' => $otherOutlet->id,
        ])->assertStatus(422);

        $this->actingAs($this->tenantAdmin)->post(route('courier.staff.store'), [
            'full_name' => 'Cross Tenant Staff',
            'email' => 'cross-staff@test.local',
            'password' => 'password123',
            'user_type' => 'staff',
            'outlet_id' => $otherOutlet->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'cross-customer@test.local']);
        $this->assertDatabaseMissing('users', ['email' => 'cross-staff@test.local']);
    }

    public function test_staff_cannot_create_tenant_administrator(): void
    {
        $this->actingAs($this->staff)->post(route('courier.staff.store'), [
            'full_name' => 'Unauthorized Admin',
            'email' => 'unauthorized-admin@test.local',
            'password' => 'password123',
            'user_type' => 'tenant_admin',
            'outlet_id' => $this->staff->outlet_id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-admin@test.local']);
    }

    public function test_package_cannot_use_another_tenant_package_type(): void
    {
        $otherTenant = Tenant::create([
            'company_name' => 'Package Type Courier',
            'slug' => 'package-type-courier',
            'subdomain' => 'package-type-courier',
            'email' => 'admin@package-type-courier.test',
            'status' => 'active',
        ]);
        $otherType = PackageType::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Type',
            'code' => 'OTT',
            'is_active' => true,
        ]);

        $this->actingAs($this->tenantAdmin)->post(route('courier.packages.store'), [
            'package_type_id' => $otherType->id,
            'freight_type' => 'air',
            'delivery_type' => 'pickup',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('packages', [
            'tenant_id' => $this->tenant->id,
            'package_type_id' => $otherType->id,
        ]);
    }

    public function test_registration_payment_cancel_requires_a_signed_url(): void
    {
        $subscription = TenantSubscription::where('tenant_id', $this->tenant->id)->firstOrFail();
        $payment = TenantPayment::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'transaction_id' => 'QA-CANCEL-'.uniqid(),
            'amount' => 100,
            'currency' => 'JMD',
            'payment_method' => 'paypal',
            'status' => 'pending',
        ]);

        $this->get(route('tenant.register.payment.cancel', $payment))->assertForbidden();

        $signedUrl = URL::temporarySignedRoute('tenant.register.payment.cancel', now()->addMinutes(5), $payment);
        $this->get($signedUrl)->assertRedirect(route('tenant.register'));
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_courier_admin_can_view_customer_wallet_top_up_receipt(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('wallet-top-up-receipts/top-up.pdf', 'test receipt');
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        $transaction = WalletTransaction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'transaction_type' => 'credit',
            'source_type' => 'manual',
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 0,
            'description' => 'Top-up review',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'receipt_path' => 'wallet-top-up-receipts/top-up.pdf',
            'receipt_original_name' => 'top-up.pdf',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.customers.wallet-top-ups.receipt', [$customer, $transaction]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="top-up.pdf"');
    }

    public function test_approved_customer_top_up_is_converted_to_points_without_crediting_cash_wallet(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        TenantSetting::putValue($this->tenant->id, 'loyalty_point_value_jmd', '2');
        $transaction = WalletTransaction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'transaction_type' => 'credit',
            'source_type' => 'manual',
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 0,
            'description' => 'Top-up review',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch(route('courier.customers.wallet-top-ups.approve', [$customer, $transaction]))
            ->assertSessionHasNoErrors();

        $transaction->refresh();
        $loyalty = app(LoyaltyService::class);

        $this->assertTrue($transaction->converted_to_points);
        $this->assertSame('250.00', $transaction->points_awarded);
        $this->assertSame(250.0, $loyalty->pointsBalance($customer));
        $this->assertSame(0.0, $loyalty->cashBalance($customer));
    }

    public function test_courier_can_add_funds_directly_to_customer_cash_wallet(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.customers.wallet-credit', $customer), [
                'amount' => 325.50,
                'description' => 'Counter credit',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(325.5, app(LoyaltyService::class)->cashBalance($customer));
    }

    public function test_cash_invoice_change_is_credited_when_wallet_checkbox_is_checked(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        $cash = PaymentMethod::firstOrCreate([
            'tenant_id' => null,
            'code' => 'CASH',
        ], [
            'name' => 'Cash',
            'is_active' => true,
        ]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'invoice_number' => 'INV-CHANGE-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'subtotal_jmd' => 900,
            'total_jmd' => 900,
            'payment_status' => 'unpaid',
            'invoice_status' => 'issued',
            'require_handoff_verification' => false,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.invoices.pay', $invoice), [
                'payment_method_id' => $cash->id,
                'amount_received' => 1000,
                'credit_change_to_wallet' => 1,
                'require_handoff_verification' => 0,
            ])
            ->assertSessionHasNoErrors();

        $payment = Payment::where('invoice_id', $invoice->id)->latest()->firstOrFail();
        $this->assertTrue($payment->change_to_wallet);
        $this->assertSame('100.00', $payment->change_due);
        $this->assertSame(100.0, app(LoyaltyService::class)->cashBalance($customer));
    }

    public function test_sendx_zero_weight_uses_flat_rate_management_charge(): void
    {
        $rate = WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'min_weight' => 0,
            'max_weight' => 2,
            'price_jmd' => 900,
            'express_price_jmd' => 1300,
            'freight_type' => 'sendx',
            'is_active' => true,
        ]);

        $this->assertSame(900.0, $rate->priceFor(0, 'pickup'));
        $this->assertSame(1300.0, $rate->priceFor(0, 'delivery'));
    }

    public function test_invoice_create_page_fetches_sendx_package_charge_from_rate_management(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        WeightRate::create([
            'tenant_id' => $this->tenant->id,
            'min_weight' => 0,
            'max_weight' => 2,
            'price_jmd' => 900,
            'express_price_jmd' => 1300,
            'freight_type' => 'sendx',
            'is_active' => true,
        ]);
        $package = Package::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'tracking_number' => 'RATE-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'description' => 'Rate test package',
            'weight' => 0,
            'price_jmd' => 0,
            'freight_type' => 'sendx',
            'delivery_type' => 'pickup',
            'date_received' => now()->toDateString(),
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.invoices.create', ['package_id' => $package->id]))
            ->assertOk()
            ->assertSee('value="900.00"', false)
            ->assertSee('Rate Management');
    }

    public function test_invoice_proof_approval_preserves_receipt_and_completes_checkout(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment-receipts/customer-proof.pdf', 'proof');
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'invoice_number' => 'INV-PROOF-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'subtotal_jmd' => 1000,
            'total_jmd' => 1000,
            'payment_status' => 'unpaid',
            'invoice_status' => 'issued',
        ]);
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'payment_number' => 'PAY-PROOF-'.uniqid(),
            'amount' => 1000,
            'reference_number' => $invoice->invoice_number,
            'receipt_path' => 'payment-receipts/customer-proof.pdf',
            'receipt_original_name' => 'customer-proof.pdf',
            'receipt_mime_type' => 'application/pdf',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.finance.payments.approve', $payment))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('payment-receipts/customer-proof.pdf', $payment->fresh()->receipt_path);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        Storage::disk('public')->assertExists('payment-receipts/customer-proof.pdf');
    }

    public function test_packages_only_age_after_their_invoice_reaches_the_minimum_age(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        $status = PackageStatus::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ready for Pickup',
            'slug' => 'ready-for-pickup',
            'is_active' => true,
        ]);
        $invoicedPackage = Package::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'tracking_number' => 'AGING-INVOICED-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'status_id' => $status->id,
            'date_received' => now()->subMonth()->toDateString(),
        ]);
        $uninvoicedPackage = Package::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'tracking_number' => 'AGING-NO-INVOICE-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'status_id' => $status->id,
            'date_received' => now()->subMonth()->toDateString(),
        ]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'invoice_number' => 'INV-AGING-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'subtotal_jmd' => 500,
            'total_jmd' => 500,
            'payment_status' => 'unpaid',
            'invoice_status' => 'issued',
        ]);
        $invoice->timestamps = false;
        $invoice->created_at = now()->subDays(8);
        $invoice->save();
        InvoiceItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'package_id' => $invoicedPackage->id,
            'tracking_number' => $invoicedPackage->tracking_number,
            'total_jmd' => 500,
        ]);

        $agingIds = Package::where('tenant_id', $this->tenant->id)
            ->currentlyAging()
            ->pluck('id');

        $this->assertTrue($agingIds->contains($invoicedPackage->id));
        $this->assertFalse($agingIds->contains($uninvoicedPackage->id));
    }

    public function test_only_manifested_packages_appear_in_packages_and_unmanifested_stay_in_warehouse(): void
    {
        $customer = Customer::where('tenant_id', $this->tenant->id)->firstOrFail();
        $manifest = Manifest::create([
            'tenant_id' => $this->tenant->id,
            'manifest_number' => 'INHOUSE-'.uniqid(),
            'status' => 'draft',
        ]);
        $manifested = Package::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'tracking_number' => 'ON-MANIFEST-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'manifest_id' => $manifest->id,
            'is_in_manifest' => true,
            'date_received' => now()->toDateString(),
        ]);
        $warehouse = Package::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'tracking_number' => 'WAREHOUSE-'.uniqid(),
            'mailbox' => $customer->mailbox,
            'is_in_manifest' => false,
            'date_received' => now()->toDateString(),
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.packages.index'))
            ->assertOk()
            ->assertSee($manifested->tracking_number)
            ->assertDontSee($warehouse->tracking_number);

        $this->actingAs($this->tenantAdmin)
            ->get(route('courier.warehouse.unmanifest'))
            ->assertOk()
            ->assertSee($warehouse->tracking_number)
            ->assertDontSee($manifested->tracking_number);
    }

    public function test_courier_admin_can_generate_personal_destructive_action_code(): void
    {
        $this->tenantAdmin->forceFill(['security_code_hash' => null])->save();

        $this->actingAs($this->tenantAdmin)
            ->post(route('courier.settings.personal-security-code'))
            ->assertRedirect()
            ->assertSessionHas('fresh_security_code');

        $this->assertNotNull($this->tenantAdmin->fresh()->security_code_hash);
    }

    public function test_system_admin_can_generate_personal_destructive_action_code(): void
    {
        $this->superAdmin->forceFill(['security_code_hash' => null])->save();

        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.settings.personal-security-code'))
            ->assertRedirect()
            ->assertSessionHas('fresh_security_code');

        $this->assertNotNull($this->superAdmin->fresh()->security_code_hash);
    }

    public function test_courier_admin_can_set_reusable_destructive_action_code(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->put(route('courier.settings.personal-security-code.update'), [
                'security_code' => 'Delete-2468',
                'security_code_confirmation' => 'Delete-2468',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = $this->tenantAdmin->fresh();
        $this->assertTrue((bool) $user->security_code_reusable);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('DELETE-2468', $user->security_code_hash));
    }

    public function test_system_admin_can_set_reusable_destructive_action_code(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('superadmin.settings.personal-security-code.update'), [
                'security_code' => 'Platform-2468',
                'security_code_confirmation' => 'Platform-2468',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = $this->superAdmin->fresh();
        $this->assertTrue((bool) $user->security_code_reusable);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('PLATFORM-2468', $user->security_code_hash));
    }

    public function test_system_admin_manifest_uses_compact_section_tabs(): void
    {
        $manifest = SuperadminLogisticsManifest::create([
            'tenant_id' => $this->tenant->id,
            'manifest_number' => 'SALM-TABS-'.uniqid(),
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.logistics.show', $manifest))
            ->assertOk()
            ->assertSee('data-logistics-tab="overview"', false)
            ->assertSee('data-logistics-tab="operations"', false)
            ->assertSee('data-logistics-tab="packages"', false)
            ->assertSee('data-logistics-tab="invoices"', false);
    }

    public function test_superadmin_main_pages_render_without_server_errors(): void
    {
        $routes = [
            'superadmin.dashboard',
            'superadmin.tenants',
            'superadmin.tenants.create',
            'superadmin.tenants.customers',
            'superadmin.payments.index',
            'superadmin.staff.employees',
            'superadmin.staff.roles-branches',
            'superadmin.finance.entries',
            'superadmin.finance.categories',
            'superadmin.finance.profit-loss',
            'superadmin.finance.billing-rates',
            'superadmin.finance.bills',
            'superadmin.support.index',
            'superadmin.support.create',
            'superadmin.logistics.index',
            'superadmin.logistics.manifests',
            'superadmin.logistics.bulk-upload',
            'superadmin.logistics.rates',
            'superadmin.logistics.invoices',
            'superadmin.settings.index',
            'superadmin.reports.index',
            'superadmin.audit.index',
            'superadmin.plans.index',
            'superadmin.plans.create',
        ];

        foreach ($routes as $route) {
            $url = route($route);
            $this->assertSmokeOk($this->actingAs($this->superAdmin)->get($url), $url);
        }
    }

    public function test_courier_admin_main_pages_render_without_server_errors(): void
    {
        $routes = [
            'courier.dashboard',
            'courier.customers.index',
            'courier.customers.create',
            'courier.customers.print',
            'courier.authorized-users.index',
            'courier.crm.emails.create',
            'courier.support.index',
            'courier.support.create',
            'courier.packages.index',
            'courier.packages.create',
            'courier.packages.aging',
            'courier.pre-alerts.index',
            'courier.purchase-requests.index',
            'courier.warehouse.unmanifest',
            'courier.manifests.index',
            'courier.manifests.create',
            'courier.delivery.index',
            'courier.delivery.history',
            'courier.delivery-areas.index',
            'courier.delivery-areas.create',
            'courier.invoices.index',
            'courier.invoices.create',
            'courier.invoices.mass',
            'courier.finance.transactions',
            'courier.finance.entries',
            'courier.finance.categories',
            'courier.finance.payments',
            'courier.finance.bills',
            'courier.finance.reports',
            'courier.finance.profit-loss',
            'courier.finance.balance-sheet',
            'courier.staff.index',
            'courier.roles.index',
            'courier.outlets.index',
            'courier.settings.index',
            'courier.weights.index',
            'courier.weights.create',
            'courier.customs-rates.index',
            'courier.customs-rates.create',
            'courier.statuses.index',
            'courier.activity.index',
            'profile.edit',
            'calculator',
        ];

        foreach ($routes as $route) {
            $url = route($route);
            $this->assertSmokeOk($this->actingAs($this->tenantAdmin)->get($url), $url);
        }
    }

    public function test_staff_main_pages_render_without_server_errors(): void
    {
        foreach ([
            'courier.dashboard',
            'courier.customers.index',
            'courier.packages.index',
            'courier.invoices.index',
            'courier.delivery.index',
            'courier.finance.transactions',
            'courier.support.index',
        ] as $route) {
            $url = route($route);
            $this->assertSmokeOk($this->actingAs($this->staff)->get($url), $url);
        }
    }

    public function test_customer_main_pages_render_without_server_errors(): void
    {
        $routes = [
            'customer.dashboard',
            'customer.affiliate-loyalty',
            'customer.messages.index',
            'customer.support.index',
            'customer.support.create',
            'customer.packages',
            'customer.packages.create',
            'customer.pre-alerts.index',
            'customer.pre-alerts.create',
            'customer.purchase-requests.index',
            'customer.purchase-requests.create',
            'customer.invoices',
            'customer.delivery.index',
            'customer.delivery.history',
            'customer.delivery.create',
            'customer.authorized-users.index',
            'customer.authorized-users.create',
            'customer.account.delete',
            'profile.edit',
            'calculator',
        ];

        foreach ($routes as $route) {
            $url = route($route);
            $this->assertSmokeOk($this->actingAs($this->customerUser)->get($url), $url);
        }
    }

    private function assertSmokeOk($response, string $url): void
    {
        $status = $response->getStatusCode();
        $this->assertLessThan(400, $status, "Smoke check failed for {$url} with status {$status}.");
    }
}
