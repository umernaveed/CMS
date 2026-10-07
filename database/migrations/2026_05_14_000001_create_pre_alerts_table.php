<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('pre_alert_number', 150);
            $table->string('supplier_tracking', 150)->nullable();
            $table->string('courier', 100)->nullable();
            $table->string('merchant')->nullable();
            $table->text('description');
            $table->decimal('price_usd', 12, 2)->default(0);
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup');
            $table->string('status', 30)->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pre_alert_number']);
            $table->index(['tenant_id', 'supplier_tracking']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('pre_alert_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_alert_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_alert_receipts');
        Schema::dropIfExists('pre_alerts');
    }
};
