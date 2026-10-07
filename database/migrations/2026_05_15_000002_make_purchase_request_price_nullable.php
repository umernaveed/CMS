<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `purchase_requests` MODIFY `price_usd` DECIMAL(12,2) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `purchase_requests` MODIFY `price_usd` DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
};
