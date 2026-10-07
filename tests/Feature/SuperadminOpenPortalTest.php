<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminOpenPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_a_courier_portal_as_the_tenant_admin()
    {
        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Full Access',
            'monthly_price' => 0,
            'features' => ['modules' => array_keys(SubscriptionPlan::MODULES)],
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $tenantAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'tenant_admin',
            'email' => 'tenant-admin@demo.test',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($superAdmin)
            ->post(route('superadmin.tenants.open-portal', $tenant));

        $response->assertRedirect(rtrim($tenant->portalUrl(), '/').route('courier.dashboard', [], false));
        $this->assertAuthenticatedAs($tenantAdmin);
        $response->assertSessionHas('impersonated_by', $superAdmin->id);
        $this->assertNotNull($tenantAdmin->fresh()->last_login_at);
    }

    public function test_subscription_plan_create_accepts_per_package_feature_payload()
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Service Charge',
            'description' => 'Under this plan, a service fee is automatically applied to courier transactions.',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_model' => 'per_package',
            'per_package_base_fee_jmd' => 0,
            'package_service_fee_jmd' => 0.10,
            'max_storage_days' => 30,
            'is_active' => true,
            'sort_order' => 0,
            'features' => [
                'features' => [],
                'modules' => ['dashboard', 'customers', 'packages', 'invoices', 'reports', 'manifests', 'delivery', 'live_chat', 'staff', 'branches', 'settings', 'roles', 'system'],
            ],
        ]);

        $this->assertTrue($plan->exists);
        $this->assertSame('per_package', $plan->billing_model);
        $this->assertContains('live_chat', $plan->enabledModules());
    }

    public function test_superadmin_cannot_open_a_portal_without_an_active_tenant_admin()
    {
        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $response = $this
            ->from(route('superadmin.tenants.show', $tenant))
            ->actingAs($superAdmin)
            ->post(route('superadmin.tenants.open-portal', $tenant));

        $response->assertRedirect(route('superadmin.tenants.show', $tenant));
        $response->assertSessionHasErrors('portal');
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_superadmin_can_return_from_an_opened_courier_portal()
    {
        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $tenantAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'tenant_admin',
            'email' => 'tenant-admin@demo.test',
            'is_active' => true,
        ]);

        $this
            ->actingAs($superAdmin)
            ->post(route('superadmin.tenants.open-portal', $tenant));

        $this->assertAuthenticatedAs($tenantAdmin);

        $response = $this->post(route('superadmin.return'));

        $response->assertRedirect(rtrim(config('app.url'), '/').route('superadmin.dashboard', [], false));
        $response->assertSessionMissing('impersonated_by');
        $response->assertSessionMissing('impersonated_by_email');
        $response->assertSessionMissing('impersonated_tenant_id');
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_central_superadmin_request_recovers_from_an_existing_portal_impersonation()
    {
        config(['app.url' => 'https://freitora.test']);

        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $tenantAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'tenant_admin',
            'email' => 'tenant-admin@demo.test',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tenantAdmin)
            ->withSession([
                'impersonated_by' => $superAdmin->id,
                'impersonated_by_email' => $superAdmin->email,
                'impersonated_tenant_id' => $tenant->id,
                'superadmin_open_portal_full_access' => true,
            ])
            ->get('https://freitora.test/superadmin/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($superAdmin);
        $response->assertSessionMissing('impersonated_by');
        $response->assertSessionMissing('impersonated_tenant_id');
    }

    public function test_superadmin_cannot_sign_in_on_a_courier_subdomain()
    {
        config(['app.url' => 'https://freitora.test']);

        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $response = $this
            ->post('https://demo.freitora.test/login', [
                'email' => $superAdmin->email,
                'password' => 'secret-password',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_superadmin_can_sign_in_on_the_central_domain()
    {
        config(['app.url' => 'https://freitora.test']);

        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $response = $this
            ->post('https://freitora.test/login', [
                'email' => $superAdmin->email,
                'password' => 'secret-password',
            ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_superadmin_routes_are_not_available_on_a_courier_subdomain()
    {
        config(['app.url' => 'https://freitora.test']);

        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'is_active' => true,
        ]);

        Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
        ]);

        $this
            ->actingAs($superAdmin)
            ->get('https://demo.freitora.test/superadmin/dashboard')
            ->assertNotFound();
    }

    public function test_courier_account_cannot_access_another_courier_subdomain()
    {
        config(['app.url' => 'https://freitora.test']);

        $firstTenant = Tenant::create([
            'company_name' => 'First Courier',
            'slug' => 'first-courier',
            'subdomain' => 'first',
            'email' => 'admin@first.test',
            'status' => 'active',
        ]);

        Tenant::create([
            'company_name' => 'Second Courier',
            'slug' => 'second-courier',
            'subdomain' => 'second',
            'email' => 'admin@second.test',
            'status' => 'active',
        ]);

        $firstAdmin = User::factory()->create([
            'tenant_id' => $firstTenant->id,
            'user_type' => 'tenant_admin',
            'is_active' => true,
        ]);
        $response = $this
            ->actingAs($firstAdmin)
            ->get('https://second.freitora.test/profile');

        $response->assertRedirect('https://second.freitora.test/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
