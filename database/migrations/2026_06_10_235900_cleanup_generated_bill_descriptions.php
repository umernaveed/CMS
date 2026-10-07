<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_bills')) {
            return;
        }

        DB::table('superadmin_bills')
            ->whereIn('source_type', ['package_usage', 'shipped_package'])
            ->where(function ($query) {
                $query->where('description', 'like', '%Logged package sample:%')
                    ->orWhere('description', 'like', '%Shipped packages:%');
            })
            ->update(['description' => null]);
    }

    public function down(): void
    {
        // Generated diagnostic descriptions cannot be reconstructed reliably.
    }
};
