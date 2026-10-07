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
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_weight')) {
                $table->decimal('freight_weight', 10, 4)->default(0)->after('freight_rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_unit')) {
                $table->string('freight_unit', 10)->nullable()->after('freight_weight');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'amount_usd')) {
                $table->decimal('amount_usd', 10, 2)->default(0)->after('freight_charge');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'exchange_rate_jmd')) {
                $table->decimal('exchange_rate_jmd', 12, 4)->default(0)->after('amount_usd');
            }
        });

        Schema::table('superadmin_logistics_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'freight_weight')) {
                $table->decimal('freight_weight', 10, 4)->default(0)->after('freight_rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'freight_unit')) {
                $table->string('freight_unit', 10)->nullable()->after('freight_weight');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'amount_usd')) {
                $table->decimal('amount_usd', 10, 2)->default(0)->after('freight_charge');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoice_items', 'exchange_rate_jmd')) {
                $table->decimal('exchange_rate_jmd', 12, 4)->default(0)->after('amount_usd');
            }
        });

        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_exchange_rate_jmd'], ['value' => '0', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_cached_exchange_rate_jmd'], ['value' => '0', 'group' => 'logistics']);
        DB::table('system_settings')->updateOrInsert(['key' => 'logistics_cached_exchange_rate_date'], ['value' => '', 'group' => 'logistics']);
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_invoice_items', function (Blueprint $table) {
            foreach (['freight_weight', 'freight_unit', 'amount_usd', 'exchange_rate_jmd'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            foreach (['freight_weight', 'freight_unit', 'amount_usd', 'exchange_rate_jmd'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('system_settings')
            ->whereIn('key', [
                'logistics_exchange_rate_jmd',
                'logistics_cached_exchange_rate_jmd',
                'logistics_cached_exchange_rate_date',
            ])
            ->delete();
    }
};
