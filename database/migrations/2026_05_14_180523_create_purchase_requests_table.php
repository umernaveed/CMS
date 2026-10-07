<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('purchase_request_number', 150);
            $table->string('supplier_tracking', 150)->nullable();
            $table->string('courier', 100)->nullable();
            $table->string('merchant')->nullable();
            $table->text('description');
            $table->decimal('price_usd', 12, 2)->default(0);
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup');
            $table->string('status', 30)->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'purchase_request_number']);
            $table->index(['tenant_id', 'supplier_tracking']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('purchase_request_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_receipts');
        Schema::dropIfExists('purchase_requests');
    }
};
