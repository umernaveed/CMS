<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\TenantSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInvoiceStorageFees extends Command
{
    protected $signature = 'invoices:backfill-storage-fees
        {--tenant= : Limit to one courier/tenant ID}
        {--invoice= : Limit to one invoice ID}
        {--statuses=unpaid,partial : Comma-separated payment statuses to update}
        {--include-paid : Also update paid invoices}
        {--dry-run : Preview changes without saving. This is also the default when --apply is not used}
        {--apply : Persist changes. Without this option the command is a dry run.}';

    protected $description = 'Recalculate package, invoice item, and invoice storage fees using each courier storage settings.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $statuses = $this->paymentStatuses();

        $query = Invoice::query()
            ->whereNull('voided_at')
            ->where('invoice_status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'cancelled')
            ->with(['items.package']);

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', (int) $tenantId);
        }

        if ($invoiceId = $this->option('invoice')) {
            $query->whereKey((int) $invoiceId);
        } else {
            $query->whereIn('payment_status', $statuses);
        }

        $summary = [
            'checked' => 0,
            'changed' => 0,
            'packages' => 0,
            'items' => 0,
            'invoice_storage_delta' => 0.0,
            'invoice_total_delta' => 0.0,
        ];

        $this->info(($apply ? 'APPLY' : 'DRY RUN').' - recalculating storage fees for statuses: '.implode(', ', $statuses));

        $query->orderBy('id')->chunkById(100, function ($invoices) use ($apply, &$summary) {
            foreach ($invoices as $invoice) {
                $summary['checked']++;
                $changes = $this->changesForInvoice($invoice);

                if (! $changes['changed']) {
                    continue;
                }

                $summary['changed']++;
                $summary['packages'] += count($changes['packages']);
                $summary['items'] += count($changes['items']);
                $summary['invoice_storage_delta'] += $changes['storage_delta'];
                $summary['invoice_total_delta'] += $changes['total_delta'];

                $this->line(sprintf(
                    '%s invoice %s: storage %s -> %s, total %s -> %s',
                    $apply ? 'Updating' : 'Would update',
                    $invoice->invoice_number,
                    number_format((float) $invoice->storage_fee_jmd, 2),
                    number_format($changes['new_storage_fee_jmd'], 2),
                    number_format((float) $invoice->total_jmd, 2),
                    number_format($changes['new_total_jmd'], 2)
                ));

                if ($apply) {
                    DB::transaction(function () use ($changes) {
                        foreach ($changes['packages'] as $packageId => $values) {
                            Package::whereKey($packageId)->update($values + ['updated_at' => now()]);
                        }

                        foreach ($changes['items'] as $itemId => $values) {
                            InvoiceItem::whereKey($itemId)->update($values + ['updated_at' => now()]);
                        }

                        Invoice::whereKey($changes['invoice_id'])->update($changes['invoice'] + ['updated_at' => now()]);
                    });
                }
            }
        });

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Invoices checked', $summary['checked']],
            ['Invoices with changes', $summary['changed']],
            ['Packages to update', $summary['packages']],
            ['Invoice items to update', $summary['items']],
            ['Storage delta', 'JMD '.number_format($summary['invoice_storage_delta'], 2)],
            ['Total delta', 'JMD '.number_format($summary['invoice_total_delta'], 2)],
        ]);

        if (! $apply) {
            $this->warn('Dry run only. Re-run with --apply to save these changes.');
        }

        return self::SUCCESS;
    }

    private function changesForInvoice(Invoice $invoice): array
    {
        $packageChanges = [];
        $itemChanges = [];

        foreach ($invoice->items as $item) {
            $package = $item->package;

            if (! $package) {
                continue;
            }

            $days = $this->storageDays($invoice);
            $storage = $this->storageFee((int) $package->tenant_id, $days);
            $itemTotal = round(
                (float) $item->package_price_jmd
                + $storage
                + (float) $item->processing_fee_jmd
                + (float) $item->unknown_fee_jmd
                + (float) $item->service_fee_jmd
                + (float) $item->customs_fee_jmd,
                2
            );

            if ((int) $package->storage_days !== $days || round((float) $package->storage_fee, 2) !== $storage) {
                $packageChanges[$package->id] = [
                    'storage_days' => $days,
                    'storage_fee' => $storage,
                ];
            }

            if (round((float) $item->storage_fee_jmd, 2) !== $storage || round((float) $item->total_jmd, 2) !== $itemTotal) {
                $itemChanges[$item->id] = [
                    'storage_fee_jmd' => $storage,
                    'total_jmd' => $itemTotal,
                ];
            }
        }

        $newStorage = round(collect($invoice->items)->sum(function ($item) use ($itemChanges) {
            return $itemChanges[$item->id]['storage_fee_jmd'] ?? (float) $item->storage_fee_jmd;
        }), 2);

        $base = round(
            (float) $invoice->subtotal_jmd
            + (float) $invoice->service_fee_jmd
            + (float) $invoice->customs_fee_jmd
            + $newStorage
            + (float) $invoice->gct_amount_jmd,
            2
        );

        $discount = $this->discountAmount($invoice, $base);
        $newTotal = round(max($base - $discount, 0), 2);

        $invoiceChanges = [];
        if (round((float) $invoice->storage_fee_jmd, 2) !== $newStorage) {
            $invoiceChanges['storage_fee_jmd'] = $newStorage;
        }
        if (round((float) $invoice->discount_amount_jmd, 2) !== $discount) {
            $invoiceChanges['discount_amount_jmd'] = $discount;
        }
        if (round((float) $invoice->total_jmd, 2) !== $newTotal) {
            $invoiceChanges['total_jmd'] = $newTotal;
        }

        return [
            'invoice_id' => $invoice->id,
            'changed' => $packageChanges !== [] || $itemChanges !== [] || $invoiceChanges !== [],
            'packages' => $packageChanges,
            'items' => $itemChanges,
            'invoice' => $invoiceChanges,
            'new_storage_fee_jmd' => $newStorage,
            'new_total_jmd' => $newTotal,
            'storage_delta' => $newStorage - (float) $invoice->storage_fee_jmd,
            'total_delta' => $newTotal - (float) $invoice->total_jmd,
        ];
    }

    private function paymentStatuses(): array
    {
        $statuses = collect(explode(',', (string) $this->option('statuses')))
            ->map(fn ($status) => trim($status))
            ->filter()
            ->values();

        if ($this->option('include-paid') && ! $statuses->contains('paid')) {
            $statuses->push('paid');
        }

        return $statuses->all() ?: ['unpaid', 'partial'];
    }

    private function storageDays(Invoice $invoice): int
    {
        if ($invoice->created_at) {
            return max(0, (int) $invoice->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()));
        }

        return 0;
    }

    private function storageFee(int $tenantId, int $storageDays): float
    {
        $freeDays = (int) TenantSetting::getValue($tenantId, 'storage_free_days', 0);
        if ($storageDays <= $freeDays) {
            return 0.0;
        }

        $flat = (float) TenantSetting::getValue($tenantId, 'storage_flat_fee_jmd', 0);
        $daily = (float) TenantSetting::getValue($tenantId, 'storage_daily_increment_jmd', 0);

        return round($flat + max($storageDays - $freeDays, 0) * $daily, 2);
    }

    private function discountAmount(Invoice $invoice, float $base): float
    {
        if ($invoice->discount_type === 'percent') {
            return round(min($base * max((float) $invoice->discount_value, 0) / 100, $base), 2);
        }

        $fixed = (float) ($invoice->discount_amount_jmd ?: $invoice->discount_value);

        return round(min(max($fixed, 0), $base), 2);
    }
}
