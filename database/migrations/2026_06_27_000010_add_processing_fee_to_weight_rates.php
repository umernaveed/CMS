<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProcessingFeeToWeightRates extends Migration
{
    public function up()
    {
        Schema::table('weight_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('weight_rates', 'processing_fee_jmd')) {
                $table->decimal('processing_fee_jmd', 10, 2)->default(0)->after('minimum_billable_measure');
            }
        });
    }

    public function down()
    {
        Schema::table('weight_rates', function (Blueprint $table) {
            if (Schema::hasColumn('weight_rates', 'processing_fee_jmd')) {
                $table->dropColumn('processing_fee_jmd');
            }
        });
    }
}