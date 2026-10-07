<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages') || ! Schema::hasTable('packages')) {
            return;
        }

        DB::table('superadmin_logistics_packages')
            ->whereNotNull('courier_package_id')
            ->orderBy('id')
            ->select(['id', 'courier_package_id', 'tracking_number'])
            ->chunkById(200, function ($logisticsPackages) {
                $courierPackages = DB::table('packages')
                    ->whereIn('id', $logisticsPackages->pluck('courier_package_id')->filter()->unique())
                    ->get(['id', 'tracking_number', 'supplier_tracking'])
                    ->keyBy('id');

                foreach ($logisticsPackages as $logisticsPackage) {
                    $courierPackage = $courierPackages->get($logisticsPackage->courier_package_id);
                    if (! $courierPackage) {
                        continue;
                    }

                    $currentTracking = strtoupper(trim((string) $logisticsPackage->tracking_number));
                    $hawb = strtoupper(trim((string) $courierPackage->tracking_number));
                    $supplierTracking = strtoupper(trim((string) $courierPackage->supplier_tracking));

                    if ($supplierTracking === '' || $hawb === '' || $currentTracking !== $hawb) {
                        continue;
                    }

                    $duplicateExists = DB::table('superadmin_logistics_packages')
                        ->where('id', '<>', $logisticsPackage->id)
                        ->whereRaw('UPPER(tracking_number) = ?', [$supplierTracking])
                        ->exists();

                    if ($duplicateExists) {
                        continue;
                    }

                    DB::table('superadmin_logistics_packages')
                        ->where('id', $logisticsPackage->id)
                        ->update([
                            'tracking_number' => $supplierTracking,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Data-only repair; intentionally not reversible without losing user corrections.
    }
};