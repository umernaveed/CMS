<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        $duplicates = DB::table('superadmin_logistics_packages')
            ->select('tracking_number', DB::raw('count(*) as total'))
            ->groupBy('tracking_number')
            ->having('total', '>', 1)
            ->pluck('tracking_number');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add unique tracking constraint. Remove duplicate logistics tracking number(s) first: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            $table->unique('tracking_number', 'superadmin_logistics_tracking_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            $table->dropUnique('superadmin_logistics_tracking_unique');
        });
    }
};
