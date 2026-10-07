<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('superadmin_logistics_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_rates', 'minimum_billable_measure')) {
                $table->decimal('minimum_billable_measure', 10, 2)->default(0)->after('max_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_rates', function (Blueprint $table) {
            if (Schema::hasColumn('superadmin_logistics_rates', 'minimum_billable_measure')) {
                $table->dropColumn('minimum_billable_measure');
            }
        });
    }
};
