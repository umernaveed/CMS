<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->enum('user_type', ['super_admin', 'tenant_admin', 'staff', 'customer'])->default('customer');
            $table->string('mailbox', 100)->nullable();
            $table->string('first_name', 150)->nullable();
            $table->string('last_name', 150)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('trn', 100)->nullable();
            $table->string('tcc', 100)->nullable();
            $table->date('dob')->nullable();
            $table->string('image', 255)->nullable();
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('parish', 100)->nullable();
            $table->string('country', 100)->default('Jamaica');
            $table->string('postal_code', 50)->nullable();
            $table->string('referral_code', 50)->nullable();
            $table->string('referred_by', 50)->nullable();
            $table->boolean('is_active')->default(1);
            $table->datetime('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();

            $table->unique('email');
            $table->unique(['tenant_id', 'mailbox']);
            $table->index('tenant_id');
            $table->index('outlet_id');

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
        Schema::dropIfExists('users');
    }
}