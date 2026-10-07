<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weight_rates') || ! Schema::hasColumn('weight_rates', 'max_weight')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `weight_rates` MODIFY `max_weight` DECIMAL(10,2) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('weight_rates') || ! Schema::hasColumn('weight_rates', 'max_weight')) {
            return;
        }

        DB::table('weight_rates')->whereNull('max_weight')->update(['max_weight' => 999999]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `weight_rates` MODIFY `max_weight` DECIMAL(10,2) NOT NULL DEFAULT 0');
        }
    }
};
