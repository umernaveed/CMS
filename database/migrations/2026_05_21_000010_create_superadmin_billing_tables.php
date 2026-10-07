<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bill_number', 80)->unique();
            $table->string('title');
            $table->string('category', 120)->default('System Services');
            $table->string('source_type', 40)->default('system_service');
            $table->text('description')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->default('JMD');
            $table->decimal('subtotal_jmd', 12, 2)->default(0);
            $table->decimal('tax_jmd', 12, 2)->default(0);
            $table->decimal('total_jmd', 12, 2)->default(0);
            $table->decimal('amount_paid_jmd', 12, 2)->default(0);
            $table->decimal('balance_jmd', 12, 2)->default(0);
            $table->string('status', 40)->default('issued');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['issue_date', 'due_date']);
        });

        Schema::create('superadmin_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('superadmin_bill_id')->constrained('superadmin_bills')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('amount_jmd', 12, 2)->default(0);
            $table->string('payment_method', 60)->default('bank_transfer');
            $table->string('reference_number', 150)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime_type')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_bill_payments');
        Schema::dropIfExists('superadmin_bills');
    }
};
