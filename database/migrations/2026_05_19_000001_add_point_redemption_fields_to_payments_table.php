<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'points_redeemed')) {
                $table->decimal('points_redeemed', 10, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('payments', 'points_value_jmd')) {
                $table->decimal('points_value_jmd', 10, 2)->default(0)->after('points_redeemed');
            }
            if (! Schema::hasColumn('payments', 'point_value_jmd')) {
                $table->decimal('point_value_jmd', 10, 4)->default(0)->after('points_value_jmd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['point_value_jmd', 'points_value_jmd', 'points_redeemed'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
