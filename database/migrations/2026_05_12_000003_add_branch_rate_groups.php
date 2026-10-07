<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('rate_group_mode', 20)->default('main')->after('manager_name');
        });

        Schema::table('weight_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('tenant_id');
            $table->index('outlet_id');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('weight_rates', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropIndex(['outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('rate_group_mode');
        });
    }
};
