<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasTable('shipping_modes')) {
            DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) {
                DB::table('shipping_modes')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => 'air'],
                    [
                        'name' => 'Air',
                        'category' => 'air',
                        'description' => 'By weight',
                        'icon' => 'fas fa-plane',
                        'sort_order' => 10,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            });
        }



        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'freight_type')) {
            DB::statement("ALTER TABLE `packages` MODIFY `freight_type` VARCHAR(80) NOT NULL DEFAULT 'air'");
        }

        if (Schema::hasTable('weight_rates') && Schema::hasColumn('weight_rates', 'freight_type')) {
            DB::statement("ALTER TABLE `weight_rates` MODIFY `freight_type` VARCHAR(80) NOT NULL DEFAULT 'air'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('shipping_modes')) {
            return;
        }

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) {
            DB::table('shipping_modes')->updateOrInsert(
                ['tenant_id' => $tenantId, 'code' => 'sendx'],
                [
                    'name' => 'US to JA',
                    'category' => 'air',
                    'description' => 'Standard route',
                    'icon' => 'fas fa-route',
                    'sort_order' => 10,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }
};



