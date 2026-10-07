<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('mailbox', 100);
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('account_manager_id')->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->decimal('total_points', 10, 2)->default(0);
            $table->decimal('total_referral_points', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'mailbox']);
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('outlet_id');
            $table->index('account_manager_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
            $table->foreign('account_manager_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
}