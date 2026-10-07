<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'invoice_billable_weight')) {
                $table->decimal('invoice_billable_weight', 10, 2)->nullable()->after('processing_fee_jmd');
            }

            if (! Schema::hasColumn('packages', 'invoice_rate_jmd')) {
                $table->decimal('invoice_rate_jmd', 12, 2)->nullable()->after('invoice_billable_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'invoice_rate_jmd')) {
                $table->dropColumn('invoice_rate_jmd');
            }

            if (Schema::hasColumn('packages', 'invoice_billable_weight')) {
                $table->dropColumn('invoice_billable_weight');
            }
        });
    }
};
