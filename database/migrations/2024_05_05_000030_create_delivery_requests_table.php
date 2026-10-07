<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('request_number', 100);
            $table->string('mailbox', 100)->nullable();
            $table->json('package_ids')->nullable();
            $table->integer('package_count')->default(0);
            $table->decimal('package_total', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->enum('delivery_type', ['regular', 'express', 'pickup'])->default('regular');
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_phone', 50)->nullable();
            $table->text('delivery_address')->nullable();
            $table->date('preferred_date')->nullable();
            $table->unsignedBigInteger('preferred_time_slot_id')->nullable();
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'approved', 'assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->boolean('is_paid')->default(0);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'request_number']);
            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index('outlet_id');
            $table->index('area_id');
            $table->index('preferred_time_slot_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
            $table->foreign('area_id')->references('id')->on('delivery_areas')->onDelete('set null');
            $table->foreign('preferred_time_slot_id')->references('id')->on('delivery_time_slots')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_requests');
    }
}
