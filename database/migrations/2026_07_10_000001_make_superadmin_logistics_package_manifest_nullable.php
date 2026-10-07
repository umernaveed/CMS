<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages') || ! Schema::hasColumn('superadmin_logistics_packages', 'manifest_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `superadmin_logistics_packages` MODIFY `manifest_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Non-destructive: package intake rows may intentionally have no manifest.
    }
};
