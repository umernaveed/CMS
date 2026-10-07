<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tax_identifier', 100)->nullable()->after('phone');
            $table->decimal('package_service_fee_jmd', 10, 2)->default(0)->after('tax_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_identifier', 'package_service_fee_jmd']);
        });
    }
};
