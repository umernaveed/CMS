<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id');
            $table->decimal('points_earned', 10, 2)->default(0);
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('referrer_id');
            $table->index('referred_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('referrer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('referred_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrals');
    }
}