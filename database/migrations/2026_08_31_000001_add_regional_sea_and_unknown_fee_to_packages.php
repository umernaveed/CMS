<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'region')) {
                $table->string('region', 40)->default('USA')->after('freight_type');
            }
            if (! Schema::hasColumn('packages', 'volume_cuft')) {
                $table->decimal('volume_cuft', 12, 4)->nullable()->after('region');
            }
            if (! Schema::hasColumn('packages', 'volume_cbm')) {
                $table->decimal('volume_cbm', 12, 4)->nullable()->after('volume_cuft');
            }
            if (! Schema::hasColumn('packages', 'label_type')) {
                $table->string('label_type', 40)->nullable()->after('volume_cbm');
            }
            if (! Schema::hasColumn('packages', 'unknown_fee_jmd')) {
                $table->decimal('unknown_fee_jmd', 10, 2)->default(0)->after('processing_fee_jmd');
            }
            if (! Schema::hasColumn('packages', 'unknown_fee_reason')) {
                $table->string('unknown_fee_reason')->nullable()->after('unknown_fee_jmd');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'unknown_fee_jmd')) {
                $table->decimal('unknown_fee_jmd', 10, 2)->default(0)->after('processing_fee_jmd');
            }
            if (! Schema::hasColumn('invoice_items', 'unknown_fee_reason')) {
                $table->string('unknown_fee_reason')->nullable()->after('unknown_fee_jmd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['unknown_fee_reason', 'unknown_fee_jmd'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('packages', function (Blueprint $table) {
            foreach (['unknown_fee_reason', 'unknown_fee_jmd', 'label_type', 'volume_cbm', 'volume_cuft', 'region'] as $column) {
                if (Schema::hasColumn('packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
