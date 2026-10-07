<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\WmsSyncService;
use Illuminate\Console\Command;

class SyncExistingCouriersToWms extends Command
{
    protected $signature = 'wms:sync-existing
        {--tenant= : Sync only one SaaS tenant ID}
        {--customers-only : Skip courier workspace sync and only sync customers}
        {--dry-run : Show what would sync without calling WMS}
        {--chunk=100 : Number of customers to sync per batch}
        {--pause-ms=0 : Pause this many milliseconds after each WMS request}';

    protected $description = 'Sync existing SaaS courier workspaces and customers to WMS.';

    public function handle(WmsSyncService $sync): int
    {
        if (! $sync->enabled() && ! $this->option('dry-run')) {
            $this->error('WMS sync is not configured. Set WMS_BASE_URL and WMS_SYNC_TOKEN.');

            return self::FAILURE;
        }

        $tenantQuery = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenantId) => $query->whereKey($tenantId))
            ->orderBy('id');

        $tenantCount = (clone $tenantQuery)->count();
        if ($tenantCount === 0) {
            $this->warn('No courier workspaces found for the selected filters.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $customersOnly = (bool) $this->option('customers-only');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $pauseMs = max(0, (int) $this->option('pause-ms'));
        $syncedTenants = 0;
        $failedTenants = 0;
        $syncedCustomers = 0;
        $skippedCustomers = 0;
        $failedCustomers = 0;

        $this->info(($dryRun ? '[dry-run] ' : '').'Syncing '.$tenantCount.' courier workspace(s) to WMS.');

        $tenantQuery->chunkById(25, function ($tenants) use ($sync, $dryRun, $customersOnly, $chunkSize, $pauseMs, &$syncedTenants, &$failedTenants, &$syncedCustomers, &$skippedCustomers, &$failedCustomers) {
            foreach ($tenants as $tenant) {
                $this->line('Courier #'.$tenant->id.': '.$tenant->company_name);

                if (! $customersOnly) {
                    if ($dryRun) {
                        $syncedTenants++;
                    } else {
                        $courierId = $sync->syncTenant($tenant);
                        $this->pause($pauseMs);
                        if (! $courierId) {
                            $failedTenants++;
                            $this->warn('  Courier sync failed: '.$this->syncError($sync, 'No WMS courier ID returned.'));
                        } else {
                            $syncedTenants++;
                            $this->line('  WMS courier ID: '.$courierId);
                        }
                    }
                }

                $wmsCourierId = TenantSetting::where('tenant_id', $tenant->id)
                    ->where('key', 'wms_courier_id')
                    ->value('value');
                if (! $dryRun && ! $wmsCourierId) {
                    $customerCount = Customer::where('tenant_id', $tenant->id)->count();
                    $skippedCustomers += $customerCount;
                    $this->warn('  Skipping '.$customerCount.' customer(s): courier is not linked to WMS.');
                    continue;
                }

                $customerQuery = Customer::with(['tenant', 'user', 'outlet'])
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('id');

                $customerTotal = (clone $customerQuery)->count();
                if ($customerTotal === 0) {
                    $this->line('  Customers: 0');
                    continue;
                }

                $bar = $this->output->createProgressBar($customerTotal);
                $bar->start();

                $customerQuery->chunkById($chunkSize, function ($customers) use ($sync, $dryRun, $pauseMs, &$syncedCustomers, &$failedCustomers, $bar) {
                    foreach ($customers as $customer) {
                        if ($dryRun) {
                            $syncedCustomers++;
                        } elseif ($sync->syncCustomer($customer)) {
                            $syncedCustomers++;
                        } else {
                            $failedCustomers++;
                            $this->newLine();
                            $this->warn('  Customer #'.$customer->id.' / '.$customer->mailbox.' failed: '.$this->syncError($sync, 'Unknown error'));
                        }
                        $this->pause($pauseMs);
                        $bar->advance();
                    }
                });

                $bar->finish();
                $this->newLine();
            }
        });

        $this->info(($dryRun ? 'Would sync' : 'Synced').' '.$syncedTenants.' courier workspace(s) and '.$syncedCustomers.' customer(s).');
        if ($failedTenants > 0) {
            $this->warn('Failed '.$failedTenants.' courier workspace(s). Customers for those couriers were skipped.');
        }
        if ($skippedCustomers > 0) {
            $this->warn('Skipped '.$skippedCustomers.' customer(s).');
        }
        if ($failedCustomers > 0) {
            $this->warn('Failed '.$failedCustomers.' customer(s). Check the messages above and storage/logs/laravel.log.');
        }

        return ($failedTenants > 0 || $failedCustomers > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function syncError(WmsSyncService $sync, string $fallback): string
    {
        if (method_exists($sync, 'lastError')) {
            return $sync->lastError() ?: $fallback;
        }

        return $fallback;
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
