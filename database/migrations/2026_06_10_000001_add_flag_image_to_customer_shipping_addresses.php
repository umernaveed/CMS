<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_shipping_addresses', 'flag_image')) {
                $table->string('flag_image')->nullable()->after('flag_icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('customer_shipping_addresses', 'flag_image')) {
                $table->dropColumn('flag_image');
            }
        });
    }
};
