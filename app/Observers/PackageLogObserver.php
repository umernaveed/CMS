<?php

namespace App\Observers;

use App\Models\PackageLog;
use App\Services\CustomerNotificationService;

class PackageLogObserver
{
    public function created(PackageLog $log): void
    {
        if (! $log->status_id || in_array($log->note, ['Package created', 'Bulk uploaded package', 'Imported package'], true)) {
            return;
        }

        $package = $log->package()->with(['tenant', 'customer.user', 'status'])->first();
        if (! $package) {
            return;
        }

        if ((int) $package->status_id !== (int) $log->status_id) {
            $statusName = optional($log->status)->name;
            $package->forceFill([
                'status_id' => $log->status_id,
                'date_delivered' => $statusName && stripos($statusName, 'delivered') !== false ? now() : $package->date_delivered,
            ])->saveQuietly();
            $package->load('status');
        }

        app(CustomerNotificationService::class)->packageStatusUpdated($package, $log->load('status'));
    }
}
