<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('superadmin_logistics_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_rates', 'shipment_type')) {
                $table->string('shipment_type', 20)->default('air')->after('id');
            }
            if (! Schema::hasColumn('superadmin_logistics_rates', 'region')) {
                $table->string('region', 40)->nullable()->after('shipment_type');
            }
        });

        Schema::table('superadmin_logistics_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_shipments', 'region')) {
                $table->string('region', 40)->default('USA')->after('shipment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('superadmin_logistics_shipments', 'region')) {
                $table->dropColumn('region');
            }
        });

        Schema::table('superadmin_logistics_rates', function (Blueprint $table) {
            foreach (['region', 'shipment_type'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_rates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
