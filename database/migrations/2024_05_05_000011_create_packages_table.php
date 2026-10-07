<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('tracking_number', 150);
            $table->string('supplier_tracking', 150)->nullable();
            $table->string('mailbox', 100)->nullable();
            $table->string('courier', 100)->nullable();
            $table->string('merchant', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('package_type_id')->nullable();
            $table->decimal('weight', 10, 2)->default(0);
            $table->decimal('price_usd', 10, 2)->default(0);
            $table->decimal('price_jmd', 10, 2)->default(0);
            $table->enum('freight_type', ['air', 'sea', 'local'])->default('air');
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->string('invoice_file', 500)->nullable();
            $table->unsignedBigInteger('manifest_id')->nullable();
            $table->boolean('is_in_manifest')->default(0);
            $table->integer('storage_days')->default(0);
            $table->decimal('storage_fee', 10, 2)->default(0);
            $table->date('date_received')->nullable();
            $table->datetime('date_delivered')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tracking_number']);
            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index('outlet_id');
            $table->index('package_type_id');
            $table->index('status_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
            $table->foreign('package_type_id')->references('id')->on('package_types')->onDelete('set null');
            $table->foreign('status_id')->references('id')->on('package_statuses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('packages');
    }
}

