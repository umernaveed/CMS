<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_model', 40)->default('subscription')->after('package_service_fee_jmd');
            $table->unsignedInteger('expected_monthly_packages')->nullable()->after('billing_model');
            $table->decimal('per_package_base_fee_jmd', 10, 2)->default(0)->after('expected_monthly_packages');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_model', 'expected_monthly_packages', 'per_package_base_fee_jmd']);
        });
    }
};
