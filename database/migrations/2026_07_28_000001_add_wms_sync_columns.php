<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'wms_package_id')) {
                $table->unsignedBigInteger('wms_package_id')->nullable()->after('manifest_id')->index();
            }
            if (! Schema::hasColumn('packages', 'wms_last_synced_at')) {
                $table->timestamp('wms_last_synced_at')->nullable()->after('wms_package_id');
            }
        });

        Schema::table('manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('manifests', 'wms_manifest_id')) {
                $table->unsignedBigInteger('wms_manifest_id')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('manifests', 'wms_last_synced_at')) {
                $table->timestamp('wms_last_synced_at')->nullable()->after('wms_manifest_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            if (Schema::hasColumn('manifests', 'wms_last_synced_at')) {
                $table->dropColumn('wms_last_synced_at');
            }
            if (Schema::hasColumn('manifests', 'wms_manifest_id')) {
                $table->dropColumn('wms_manifest_id');
            }
        });

        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'wms_last_synced_at')) {
                $table->dropColumn('wms_last_synced_at');
            }
            if (Schema::hasColumn('packages', 'wms_package_id')) {
                $table->dropColumn('wms_package_id');
            }
        });
    }
};
