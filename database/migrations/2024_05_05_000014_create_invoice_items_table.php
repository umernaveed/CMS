<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('tracking_number', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 10, 2)->default(0);
            $table->decimal('package_price_usd', 10, 2)->default(0);
            $table->decimal('package_price_jmd', 10, 2)->default(0);
            $table->decimal('service_fee_jmd', 10, 2)->default(0);
            $table->decimal('customs_fee_jmd', 10, 2)->default(0);
            $table->decimal('storage_fee_jmd', 10, 2)->default(0);
            $table->decimal('total_jmd', 10, 2)->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('invoice_id');
            $table->index('package_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
}