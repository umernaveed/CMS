<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('to_phone', 50);
            $table->text('message');
            $table->enum('status', ['sent', 'failed', 'queued'])->default('queued');
            $table->string('provider', 100)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
}