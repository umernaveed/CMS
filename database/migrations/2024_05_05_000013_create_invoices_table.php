<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('invoice_number', 100);
            $table->string('mailbox', 100)->nullable();
            $table->decimal('subtotal_usd', 10, 2)->default(0);
            $table->decimal('subtotal_jmd', 10, 2)->default(0);
            $table->decimal('service_fee_usd', 10, 2)->default(0);
            $table->decimal('service_fee_jmd', 10, 2)->default(0);
            $table->decimal('customs_fee_usd', 10, 2)->default(0);
            $table->decimal('customs_fee_jmd', 10, 2)->default(0);
            $table->decimal('storage_fee_jmd', 10, 2)->default(0);
            $table->decimal('gct_amount_jmd', 10, 2)->default(0);
            $table->decimal('discount_amount_jmd', 10, 2)->default(0);
            $table->decimal('total_jmd', 10, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'cancelled'])->default('unpaid');
            $table->enum('invoice_status', ['draft', 'issued', 'cancelled'])->default('draft');
            $table->string('pdf_path', 500)->nullable();
            $table->boolean('email_sent')->default(0);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index('outlet_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}