<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_packages', 'warehouse_fee')) {
                $table->decimal('warehouse_fee', 10, 2)->default(0)->after('rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'service_fee_type')) {
                $table->string('service_fee_type', 30)->default('flat')->after('warehouse_fee');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'service_fee_value')) {
                $table->decimal('service_fee_value', 10, 2)->default(0)->after('service_fee_type');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'service_fee')) {
                $table->decimal('service_fee', 10, 2)->default(0)->after('service_fee_value');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_rate')) {
                $table->decimal('freight_rate', 10, 2)->default(0)->after('service_fee');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_charge')) {
                $table->decimal('freight_charge', 10, 2)->default(0)->after('freight_rate');
            }
        });

        Schema::table('superadmin_logistics_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'warehouse_fee')) {
                $table->decimal('warehouse_fee', 10, 2)->default(0)->after('rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'service_fee')) {
                $table->decimal('service_fee', 10, 2)->default(0)->after('warehouse_fee');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'freight_rate')) {
                $table->decimal('freight_rate', 10, 2)->default(0)->after('service_fee');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'freight_charge')) {
                $table->decimal('freight_charge', 10, 2)->default(0)->after('freight_rate');
            }
        });

        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_service_fee_type'], ['value' => 'flat', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_service_fee_flat_amount'], ['value' => '0', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_service_fee_percentage'], ['value' => '0', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_freight_rate_lb'], ['value' => '0', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_freight_rate_kg'], ['value' => '0', 'group' => 'logistics']);

        DB::table('superadmin_logistics_packages')->update([
            'warehouse_fee' => DB::raw('ROUND(rate, 2)'),
            'service_fee_type' => 'flat',
            'service_fee_value' => 0,
            'service_fee' => 0,
            'freight_rate' => 0,
            'freight_charge' => 0,
            'amount' => DB::raw('ROUND(rate, 2)'),
        ]);

        DB::table('superadmin_logistics_invoice_items')->update([
            'warehouse_fee' => DB::raw('ROUND(rate, 2)'),
            'service_fee' => 0,
            'freight_rate' => 0,
            'freight_charge' => 0,
            'amount' => DB::raw('ROUND(rate, 2)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_invoice_items', function (Blueprint $table) {
            $columns = ['warehouse_fee', 'service_fee', 'freight_rate', 'freight_charge'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('superadmin_logistics_invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            $columns = ['warehouse_fee', 'service_fee_type', 'service_fee_value', 'service_fee', 'freight_rate', 'freight_charge'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('superadmin_logistics_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('system_settings')
            ->whereIn('key', [
                'logistics_service_fee_type',
                'logistics_service_fee_flat_amount',
                'logistics_service_fee_percentage',
                'logistics_freight_rate_lb',
                'logistics_freight_rate_kg',
            ])
            ->delete();
    }
};
