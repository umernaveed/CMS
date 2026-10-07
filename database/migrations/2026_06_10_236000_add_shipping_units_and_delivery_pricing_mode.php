<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'freight_type')) {
                $table->string('freight_type', 20)->nullable()->after('description');
            }
            if (! Schema::hasColumn('invoice_items', 'measure_unit')) {
                $table->string('measure_unit', 20)->default('lb')->after('billable_weight');
            }
            if (! Schema::hasColumn('invoice_items', 'rate_region')) {
                $table->string('rate_region', 120)->nullable()->after('measure_unit');
            }
        });

        Schema::table('delivery_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_areas', 'pricing_mode')) {
                $table->string('pricing_mode', 20)->default('fixed')->after('per_km_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $columns = array_filter(['freight_type', 'measure_unit', 'rate_region'], fn ($column) => Schema::hasColumn('invoice_items', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
        Schema::table('delivery_areas', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_areas', 'pricing_mode')) {
                $table->dropColumn('pricing_mode');
            }
        });
    }
};
