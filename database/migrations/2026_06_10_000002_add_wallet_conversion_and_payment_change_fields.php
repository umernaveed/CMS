<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'converted_to_points')) {
                $table->boolean('converted_to_points')->default(false)->after('amount');
            }
            if (! Schema::hasColumn('wallet_transactions', 'points_awarded')) {
                $table->decimal('points_awarded', 12, 2)->default(0)->after('converted_to_points');
            }
            if (! Schema::hasColumn('wallet_transactions', 'point_value_jmd')) {
                $table->decimal('point_value_jmd', 12, 4)->default(0)->after('points_awarded');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'amount_received')) {
                $table->decimal('amount_received', 12, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('payments', 'change_due')) {
                $table->decimal('change_due', 12, 2)->default(0)->after('amount_received');
            }
            if (! Schema::hasColumn('payments', 'change_to_wallet')) {
                $table->boolean('change_to_wallet')->default(false)->after('change_due');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            foreach (['point_value_jmd', 'points_awarded', 'converted_to_points'] as $column) {
                if (Schema::hasColumn('wallet_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            foreach (['change_to_wallet', 'change_due', 'amount_received'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
