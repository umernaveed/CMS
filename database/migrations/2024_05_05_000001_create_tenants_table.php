<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('slug', 100);
            $table->string('subdomain', 100)->nullable();
            $table->string('custom_domain', 255)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('currency', 10)->default('JMD');
            $table->string('timezone', 100)->default('America/Jamaica');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('parish', 100)->nullable();
            $table->enum('status', ['trial', 'active', 'suspended', 'expired', 'cancelled'])->default('trial');
            $table->datetime('trial_ends_at')->nullable();
            $table->datetime('subscription_ends_at')->nullable();
            $table->timestamps();

            $table->unique('slug');
            $table->unique('subdomain');
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenants');
    }
}