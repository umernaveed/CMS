<?php

namespace Tests\Feature;

use App\Models\SuperadminBill;
use App\Models\SuperadminLogisticsManifest;
use App\Models\SuperadminLogisticsPackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminCourierBillingCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_generate_a_cycle_bill_with_service_fee_and_freight_charges()
    {
        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'company_name' => 'Demo Courier',
            'slug' => 'demo-courier',
            'subdomain' => 'demo',
            'email' => 'admin@demo.test',
            'status' => 'active',
            'tax_identifier' => 'TRN-123456789',
            'package_service_fee_jmd' => 25,
        ]);

        $manifest = SuperadminLogisticsManifest::create([
            'tenant_id' => $tenant->id,
            'manifest_number' => 'SALM-TEST-001',
            'received_at' => now(),
            'status' => 'checked_out',
        ]);

        SuperadminLogisticsPackage::create([
            'manifest_id' => $manifest->id,
            'tenant_id' => $tenant->id,
            'tracking_number' => 'PKG-001',
            'weight' => 2,
            'weight_unit' => 'lb',
            'rate' => 100,
            'amount' => 200,
            'checked_out_at' => '2026-05-10 10:00:00',
        ]);

        SuperadminLogisticsPackage::create([
            'manifest_id' => $manifest->id,
            'tenant_id' => $tenant->id,
            'tracking_number' => 'PKG-002',
            'weight' => 3,
            'weight_unit' => 'lb',
            'rate' => 150,
            'amount' => 450,
            'checked_out_at' => '2026-05-12 10:00:00',
        ]);

        $response = $this
            ->actingAs($superAdmin)
            ->post(route('superadmin.finance.bills.generate-cycle'), [
                'tenant_id' => $tenant->id,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'tax_jmd' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $bill = SuperadminBill::firstOrFail();
        $this->assertSame('shipped_package', $bill->source_type);
        $this->assertSame('Package Service Fees', $bill->category);
        $this->assertEquals(700.00, (float) $bill->subtotal_jmd);
        $this->assertSame('2 shipped packages billed for 2026-05-01 to 2026-05-31.', $bill->description);
    }
}
