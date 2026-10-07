<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_notifications')
            ->whereNull('tenant_id')
            ->where('audience', 'all')
            ->update(['audience' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('system_notifications')
            ->whereNull('tenant_id')
            ->where('audience', 'super_admin')
            ->update(['audience' => 'all']);
    }
};
