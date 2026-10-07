<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM users'));
        $singleColumnEmailUniqueIndexes = $indexes
            ->where('Non_unique', 0)
            ->groupBy('Key_name')
            ->filter(function ($rows, $name) {
                if ($name === 'PRIMARY') {
                    return false;
                }

                $columns = collect($rows)
                    ->sortBy('Seq_in_index')
                    ->pluck('Column_name')
                    ->values()
                    ->all();

                return $columns === ['email'];
            })
            ->keys();

        foreach ($singleColumnEmailUniqueIndexes as $indexName) {
            DB::statement('ALTER TABLE users DROP INDEX `'.str_replace('`', '``', $indexName).'`');
        }

        $indexes = collect(DB::select('SHOW INDEX FROM users'));
        if (! $indexes->contains('Key_name', 'users_email_index')) {
            DB::statement('ALTER TABLE users ADD INDEX `users_email_index` (`email`)');
        }
    }

    public function down(): void
    {
        // Customer emails must remain reusable across different couriers.
    }
};
