<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages') || ! Schema::hasColumn('superadmin_logistics_packages', 'tenant_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `superadmin_logistics_packages` MODIFY `tenant_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Non-destructive: unknown intake rows may intentionally have no courier yet.
    }
};
