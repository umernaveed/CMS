<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_shipments')) {
            Schema::create('superadmin_logistics_shipments', function (Blueprint $table) {
                $table->id();
                $table->string('shipment_number', 100)->unique();
                $table->string('shipment_type', 20);
                $table->string('master_number', 150)->unique();
                $table->string('cargo_company', 150)->nullable();
                $table->date('departure_date')->nullable();
                $table->string('customs_office', 150)->nullable();
                $table->string('loading_port', 150)->nullable();
                $table->string('place_of_unloading', 150)->nullable();
                $table->string('shipping_agent_code', 100)->nullable();
                $table->string('asycuda_reference_number', 150)->nullable();
                $table->text('voyage_container_numbers')->nullable();
                $table->string('exporter_name', 150)->nullable();
                $table->text('exporter_address')->nullable();
                $table->json('documents')->nullable();
                $table->decimal('awb_cost', 12, 2)->default(0);
                $table->decimal('delivery_cost', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shipment_type', 'departure_date'], 'sals_type_departure_idx');
            });
        }

        Schema::table('superadmin_logistics_manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_manifests', 'shipment_id')) {
                $table->foreignId('shipment_id')->nullable()->after('id')->constrained('superadmin_logistics_shipments')->nullOnDelete();
            }
        });

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_packages', 'shipment_id')) {
                $table->foreignId('shipment_id')->nullable()->after('id')->constrained('superadmin_logistics_shipments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (Schema::hasColumn('superadmin_logistics_packages', 'shipment_id')) {
                $table->dropConstrainedForeignId('shipment_id');
            }
        });

        Schema::table('superadmin_logistics_manifests', function (Blueprint $table) {
            if (Schema::hasColumn('superadmin_logistics_manifests', 'shipment_id')) {
                $table->dropConstrainedForeignId('shipment_id');
            }
        });

        Schema::dropIfExists('superadmin_logistics_shipments');
    }
};
