<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('superadmin_logistics_manifests') && ! Schema::hasColumn('superadmin_logistics_manifests', 'shipment_id')) {
            Schema::table('superadmin_logistics_manifests', function (Blueprint $table) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('id')->index();
            });
        }

        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_packages', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'courier_package_id')) {
                $table->unsignedBigInteger('courier_package_id')->nullable()->after('tenant_id')->index();
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'warehouse_fee')) {
                $table->decimal('warehouse_fee', 10, 2)->default(0)->after('rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'service_fee_type')) {
                $table->string('service_fee_type', 20)->default('flat')->after('warehouse_fee');
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
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_weight')) {
                $table->decimal('freight_weight', 10, 4)->default(0)->after('freight_rate');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_unit')) {
                $table->string('freight_unit', 10)->default('lb')->after('freight_weight');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'freight_charge')) {
                $table->decimal('freight_charge', 10, 2)->default(0)->after('freight_unit');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'amount_usd')) {
                $table->decimal('amount_usd', 10, 2)->default(0)->after('freight_charge');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'exchange_rate_jmd')) {
                $table->decimal('exchange_rate_jmd', 10, 4)->default(0)->after('amount_usd');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'location_status')) {
                $table->string('location_status', 40)->default('in_house')->after('checked_out_by');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'location_notes')) {
                $table->text('location_notes')->nullable()->after('location_status');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left non-destructive. These columns are also used by existing logistics features.
    }
};
