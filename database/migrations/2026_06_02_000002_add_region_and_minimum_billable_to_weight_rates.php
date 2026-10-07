<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weight_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('weight_rates', 'region')) {
                $table->string('region', 120)->nullable()->after('pricing_tier_id');
            }
            if (! Schema::hasColumn('weight_rates', 'volume_unit')) {
                $table->string('volume_unit', 20)->default('cuft')->after('region');
            }
            if (! Schema::hasColumn('weight_rates', 'minimum_billable_measure')) {
                $table->decimal('minimum_billable_measure', 10, 2)->default(0)->after('max_weight');
            }
        });

        Schema::table('delivery_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_requests', 'pickup_location')) {
                $table->string('pickup_location')->nullable()->after('package_total');
            }
            if (! Schema::hasColumn('delivery_requests', 'destination_lat')) {
                $table->decimal('destination_lat', 10, 7)->nullable()->after('delivery_address');
            }
            if (! Schema::hasColumn('delivery_requests', 'destination_lng')) {
                $table->decimal('destination_lng', 10, 7)->nullable()->after('destination_lat');
            }
        });

        Schema::table('superadmin_logistics_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_invoices', 'supplier_invoice_path')) {
                $table->string('supplier_invoice_path')->nullable()->after('receipt_original_name');
            }
            if (! Schema::hasColumn('superadmin_logistics_invoices', 'supplier_invoice_original_name')) {
                $table->string('supplier_invoice_original_name')->nullable()->after('supplier_invoice_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weight_rates', function (Blueprint $table) {
            foreach (['minimum_billable_measure', 'volume_unit', 'region'] as $column) {
                if (Schema::hasColumn('weight_rates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('delivery_requests', function (Blueprint $table) {
            foreach (['destination_lng', 'destination_lat', 'pickup_location'] as $column) {
                if (Schema::hasColumn('delivery_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('superadmin_logistics_invoices', function (Blueprint $table) {
            foreach (['supplier_invoice_original_name', 'supplier_invoice_path'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
