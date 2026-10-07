<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_packages', 'courier_package_id')) {
                $table->foreignId('courier_package_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('packages')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages') || ! Schema::hasColumn('superadmin_logistics_packages', 'courier_package_id')) {
            return;
        }

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_package_id');
        });
    }
};
