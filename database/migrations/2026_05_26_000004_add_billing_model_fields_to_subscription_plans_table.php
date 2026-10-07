<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('billing_model', 40)->default('subscription')->after('yearly_price');
            $table->decimal('per_package_base_fee_jmd', 10, 2)->default(0)->after('billing_model');
            $table->decimal('package_service_fee_jmd', 10, 2)->default(0)->after('per_package_base_fee_jmd');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['billing_model', 'per_package_base_fee_jmd', 'package_service_fee_jmd']);
        });
    }
};
