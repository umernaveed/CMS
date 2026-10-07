<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActiveFlagToHsCodesTable extends Migration
{
    public function up()
    {
        Schema::table('hs_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('hs_codes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('duty_rate');
            }
        });
    }

    public function down()
    {
        Schema::table('hs_codes', function (Blueprint $table) {
            if (Schema::hasColumn('hs_codes', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
}
