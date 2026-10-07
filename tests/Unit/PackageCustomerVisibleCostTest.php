<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PackageCustomerVisibleCostTest extends TestCase
{
    public function test_it_hides_cost_without_a_manifest_invoice(): void
    {
        $package = new Package(['manifest_id' => null, 'price_jmd' => 750, 'storage_fee' => 100]);

        $this->assertSame(0.0, $package->customerVisibleCostJmd());
        $this->assertSame('JMD 0.00', $package->customerVisibleCostDisplay());
    }

    public function test_it_uses_invoice_item_total_for_manifested_packages(): void
    {
        $invoice = (new Invoice())->forceFill([
            'invoice_status' => 'issued',
            'payment_status' => 'unpaid',
            'voided_at' => null,
            'created_at' => now(),
        ]);
        $item = (new InvoiceItem())->forceFill(['total_jmd' => 875.50]);
        $item->setRelation('invoice', $invoice);
        $package = new Package(['manifest_id' => 12, 'price_jmd' => 750, 'storage_fee' => 100]);
        $package->setRelation('invoiceItems', new Collection([$item]));

        $this->assertSame(875.50, $package->customerVisibleCostJmd());
        $this->assertSame('JMD 875.50', $package->customerVisibleCostDisplay());
    }
}
