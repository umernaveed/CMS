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
                $table->unsignedInteger('pieces')->default(0);
                $table->date('departure_date')->nullable();
                $table->date('arrival_date')->nullable();
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
                $table->decimal('additional_cost', 12, 2)->default(0);
                $table->string('status', 40)->default('in_progress');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shipment_type', 'departure_date'], 'sals_type_departure_idx');
            });
        } else {
            Schema::table('superadmin_logistics_shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('superadmin_logistics_shipments', 'arrival_date')) {
                    $table->date('arrival_date')->nullable()->after('departure_date');
                }
                if (! Schema::hasColumn('superadmin_logistics_shipments', 'pieces')) {
                    $table->unsignedInteger('pieces')->default(0)->after('cargo_company');
                }
                if (! Schema::hasColumn('superadmin_logistics_shipments', 'additional_cost')) {
                    $table->decimal('additional_cost', 12, 2)->default(0)->after('delivery_cost');
                }
                if (! Schema::hasColumn('superadmin_logistics_shipments', 'status')) {
                    $table->string('status', 40)->default('in_progress')->after('additional_cost');
                }
            });
        }

        if (Schema::hasTable('superadmin_logistics_manifests') && ! Schema::hasColumn('superadmin_logistics_manifests', 'shipment_id')) {
            Schema::table('superadmin_logistics_manifests', function (Blueprint $table) {
                $table->foreignId('shipment_id')->nullable()->after('id')->constrained('superadmin_logistics_shipments')->nullOnDelete();
            });
        }

        if (Schema::hasTable('superadmin_logistics_packages') && ! Schema::hasColumn('superadmin_logistics_packages', 'shipment_id')) {
            Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
                $table->foreignId('shipment_id')->nullable()->after('id')->constrained('superadmin_logistics_shipments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('superadmin_logistics_packages') && Schema::hasColumn('superadmin_logistics_packages', 'shipment_id')) {
            Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shipment_id');
            });
        }

        if (Schema::hasTable('superadmin_logistics_manifests') && Schema::hasColumn('superadmin_logistics_manifests', 'shipment_id')) {
            Schema::table('superadmin_logistics_manifests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shipment_id');
            });
        }
    }
};
