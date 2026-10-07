<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_shipping_addresses') || Schema::hasColumn('customer_shipping_addresses', 'display_order')) {
            return;
        }

        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0)->after('shipment_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_shipping_addresses') || ! Schema::hasColumn('customer_shipping_addresses', 'display_order')) {
            return;
        }

        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
