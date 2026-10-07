<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE system_notifications MODIFY audience ENUM('all','platform_users','courier','customer','super_admin') NOT NULL DEFAULT 'all'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE system_notifications MODIFY audience ENUM('all','courier','customer','super_admin') NOT NULL DEFAULT 'all'");
    }
};
