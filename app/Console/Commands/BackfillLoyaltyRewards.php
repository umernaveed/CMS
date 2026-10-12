<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\TenantSetting;
use App\Models\WalletTransaction;
use App\Services\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillLoyaltyRewards extends Command
{
    protected $signature = 'loyalty:backfill-rewards
        {--since=2026-07-01 : Only inspect invoices paid or packages delivered on/after this date}
        {--tenant= : Limit to a tenant id}
        {--apply : Actually create missing reward transactions}';

    protected $description = 'Backfill missing loyalty rewards for paid invoices and delivered packages.';

    public function handle(LoyaltyService $loyalty): int
    {
        $since = Carbon::parse((string) $this->option('since'))->startOfDay();
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $apply = (bool) $this->option('apply');

        $invoiceCount = 0;
        $packageCount = 0;

        Invoice::query()
            ->with('customer')
            ->where('payment_status', 'paid')
            ->whereNotNull('customer_id')
            ->whereDate('paid_at', '>=', $since)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($loyalty, $apply, &$invoiceCount) {
                foreach ($invoices as $invoice) {
                    if (! $invoice->customer || ! $this->invoiceRewardWouldApply($invoice)) {
                        continue;
                    }

                    $invoiceCount++;

                    if ($apply) {
                        $loyalty->awardInvoicePaid($invoice);
                    }
                }
            });

        Package::query()
            ->with('customer')
            ->whereNotNull('customer_id')
            ->whereNotNull('date_delivered')
            ->whereDate('date_delivered', '>=', $since)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(200, function ($packages) use ($loyalty, $apply, &$packageCount) {
                foreach ($packages as $package) {
                    if (! $package->customer || ! $this->packageRewardWouldApply($package)) {
                        continue;
                    }

                    $packageCount++;

                    if ($apply) {
                        $loyalty->refreshCustomerTier($package->customer);
                        $loyalty->awardPackageCollected($package);
                    }
                }
            });

        $mode = $apply ? 'Applied' : 'Dry run found';
        $this->info($mode.' '.$invoiceCount.' missing invoice reward(s) and '.$packageCount.' missing package reward(s).');

        if (! $apply) {
            $this->line('Run again with --apply to write the missing wallet transactions.');
        }

        return self::SUCCESS;
    }

    private function invoiceRewardWouldApply(Invoice $invoice): bool
    {
        if (! $this->loyaltyEnabled((int) $invoice->tenant_id)) {
            return false;
        }

        $perAmount = (float) TenantSetting::getValue((int) $invoice->tenant_id, 'loyalty_invoice_points_per_amount', 0);
        $amountStep = (float) TenantSetting::getValue((int) $invoice->tenant_id, 'loyalty_invoice_amount_step_jmd', 1000);
        $points = $amountStep > 0 ? floor(((float) $invoice->total_jmd / $amountStep) * $perAmount) : 0;

        return $points > 0 && ! $this->rewardExists((int) $invoice->customer_id, 'invoice_payment', (int) $invoice->id);
    }

    private function packageRewardWouldApply(Package $package): bool
    {
        if (! $this->loyaltyEnabled((int) $package->tenant_id)) {
            return false;
        }

        $points = (float) TenantSetting::getValue((int) $package->tenant_id, 'loyalty_package_points', 0);

        return $points > 0 && ! $this->rewardExists((int) $package->customer_id, 'reward', (int) $package->id);
    }

    private function loyaltyEnabled(int $tenantId): bool
    {
        return (bool) (int) TenantSetting::getValue($tenantId, 'loyalty_enabled', 0);
    }

    private function rewardExists(int $customerId, string $sourceType, int $referenceId): bool
    {
        return WalletTransaction::where('customer_id', $customerId)
            ->where('source_type', $sourceType)
            ->where('reference_id', $referenceId)
            ->where('transaction_type', 'credit')
            ->exists();
    }
}
