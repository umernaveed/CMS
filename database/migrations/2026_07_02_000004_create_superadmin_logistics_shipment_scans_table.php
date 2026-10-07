<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('superadmin_logistics_shipment_scans')) {
            return;
        }

        Schema::create('superadmin_logistics_shipment_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('superadmin_logistics_shipments')->cascadeOnDelete();
            $table->foreignId('logistics_package_id')->nullable()->constrained('superadmin_logistics_packages')->nullOnDelete();
            $table->foreignId('courier_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('scan_value', 150);
            $table->string('result', 40);
            $table->string('message')->nullable();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'result']);
            $table->index(['scan_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_logistics_shipment_scans');
    }
};
