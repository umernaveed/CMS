<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'product_url')) {
                $table->string('product_url', 500)->nullable()->after('supplier_tracking');
            }
        });

        DB::statement("ALTER TABLE `purchase_requests` MODIFY `delivery_type` ENUM('pickup','delivery','air','sea') NOT NULL DEFAULT 'pickup'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `purchase_requests` MODIFY `delivery_type` ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup'");

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('product_url');
        });
    }
};
