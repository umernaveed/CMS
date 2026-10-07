<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_logistics_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('manifest_number', 100)->unique();
            $table->string('warehouse_reference', 150)->nullable();
            $table->string('cargo_company', 150)->nullable();
            $table->dateTime('received_at')->nullable();
            $table->string('status', 50)->default('received');
            $table->boolean('require_checkout_scan')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('superadmin_logistics_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained('superadmin_logistics_manifests')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('tracking_number', 150);
            $table->string('description')->nullable();
            $table->decimal('weight', 10, 2)->default(0);
            $table->string('weight_unit', 10)->default('lb');
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['manifest_id', 'tracking_number']);
            $table->index(['tenant_id', 'tracking_number']);
        });

        Schema::create('superadmin_logistics_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->nullable()->constrained('superadmin_logistics_manifests')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->string('billing_scope', 30)->default('manifest');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status', 30)->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('superadmin_logistics_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('superadmin_logistics_invoices')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('superadmin_logistics_packages')->cascadeOnDelete();
            $table->string('tracking_number', 150);
            $table->decimal('weight', 10, 2)->default(0);
            $table->string('weight_unit', 10)->default('lb');
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('superadmin_logistics_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_weight', 10, 2)->default(0);
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->string('weight_unit', 10)->default('lb');
            $table->decimal('rate_per_unit', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['weight_unit', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_logistics_rates');
        Schema::dropIfExists('superadmin_logistics_invoice_items');
        Schema::dropIfExists('superadmin_logistics_invoices');
        Schema::dropIfExists('superadmin_logistics_packages');
        Schema::dropIfExists('superadmin_logistics_manifests');
    }
};
