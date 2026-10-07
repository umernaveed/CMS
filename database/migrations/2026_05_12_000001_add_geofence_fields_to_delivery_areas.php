<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->string('geofence_type', 20)->default('none')->after('per_km_cost');
            $table->decimal('center_lat', 10, 7)->nullable()->after('geofence_type');
            $table->decimal('center_lng', 10, 7)->nullable()->after('center_lat');
            $table->decimal('radius_km', 10, 2)->nullable()->after('center_lng');
            $table->json('geofence_coordinates')->nullable()->after('radius_km');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropColumn([
                'geofence_type',
                'center_lat',
                'center_lng',
                'radius_km',
                'geofence_coordinates',
            ]);
        });
    }
};
