<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_shipping_addresses', 'flag_icon')) {
                $table->string('flag_icon', 20)->nullable()->after('country_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('customer_shipping_addresses', 'flag_icon')) {
                $table->dropColumn('flag_icon');
            }
        });
    }
};
