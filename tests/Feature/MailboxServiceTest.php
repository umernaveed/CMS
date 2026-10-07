<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\MailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailboxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_continues_from_the_highest_existing_prefixed_mailbox_number()
    {
        $tenant = $this->tenantWithMailboxPrefix('OL-LNCF');

        $this->customerWithMailbox($tenant, 'OL-LNCF0189');
        $this->customerWithMailbox($tenant, 'OL-LNCF189');
        $this->customerWithMailbox($tenant, 'OL-LNCF196');

        $this->assertSame('OL-LNCF197', app(MailboxService::class)->nextForTenant($tenant));
    }

    public function test_it_preserves_existing_numeric_width_for_zero_padded_mailboxes()
    {
        $tenant = $this->tenantWithMailboxPrefix('ZX');

        $this->customerWithMailbox($tenant, 'ZX01004');

        $this->assertSame('ZX01005', app(MailboxService::class)->generate($tenant));
    }

    public function test_it_does_not_continue_a_lower_padded_duplicate_range()
    {
        $tenant = $this->tenantWithMailboxPrefix('OL-LNCF');

        $this->customerWithMailbox($tenant, 'OL-LNCF189');
        $this->customerWithMailbox($tenant, 'OL-LNCF0189');

        $this->assertSame('OL-LNCF190', app(MailboxService::class)->nextForTenant($tenant));
    }

    private function tenantWithMailboxPrefix(string $prefix): Tenant
    {
        $tenant = Tenant::create([
            'company_name' => $prefix.' Courier',
            'slug' => strtolower(str_replace('-', '', $prefix)).'-courier',
            'subdomain' => strtolower(str_replace('-', '', $prefix)),
            'email' => strtolower(str_replace('-', '', $prefix)).'@example.test',
            'status' => 'active',
        ]);

        TenantSetting::putValue($tenant->id, 'mailbox_prefix', $prefix);

        return $tenant;
    }

    private function customerWithMailbox(Tenant $tenant, string $mailbox): Customer
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'customer',
            'mailbox' => $mailbox,
        ]);

        return Customer::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'mailbox' => $mailbox,
            'is_active' => true,
        ]);
    }
}
