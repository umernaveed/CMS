<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->mailboxTables() as $table => $tenantColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'mailbox')) {
                continue;
            }

            if ($this->requiresUniqueNormalizedMailboxGuard($table) && $tenantColumn && Schema::hasColumn($table, $tenantColumn)) {
                $this->guardAgainstDuplicateNormalizedMailboxes($table, $tenantColumn);
            }

            DB::table($table)
                ->whereNotNull('mailbox')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $normalized = Customer::normalizeMailbox($row->mailbox);
                        if ($normalized !== null && $normalized !== $row->mailbox) {
                            DB::table($table)->where('id', $row->id)->update(['mailbox' => $normalized]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mailbox whitespace cleanup is intentionally not reversible.
    }

    private function mailboxTables(): array
    {
        return [
            'customers' => 'tenant_id',
            'users' => 'tenant_id',
            'packages' => 'tenant_id',
            'invoices' => 'tenant_id',
            'delivery_requests' => 'tenant_id',
        ];
    }

    private function requiresUniqueNormalizedMailboxGuard(string $table): bool
    {
        return in_array($table, ['customers', 'users'], true);
    }

    private function guardAgainstDuplicateNormalizedMailboxes(string $table, string $tenantColumn): void
    {
        $normalSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(mailbox), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''))";
        $normalizedMailboxes = DB::table($table)
            ->select($tenantColumn)
            ->selectRaw($normalSql.' as normalized_mailbox')
            ->whereNotNull('mailbox')
            ->where('mailbox', '<>', '');

        $duplicates = DB::query()
            ->fromSub($normalizedMailboxes, 'normalized_mailboxes')
            ->select($tenantColumn, 'normalized_mailbox')
            ->selectRaw('COUNT(*) as total')
            ->groupBy($tenantColumn, 'normalized_mailbox')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates
                ->map(fn ($row) => $tenantColumn.'='.$row->{$tenantColumn}.', mailbox='.$row->normalized_mailbox)
                ->implode('; ');

            throw new RuntimeException("Cannot normalize {$table}. Duplicate mailbox records would be created: {$sample}");
        }
    }
};
