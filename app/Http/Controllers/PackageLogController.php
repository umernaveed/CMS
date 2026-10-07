<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Services\CustomerNotificationService;
use App\Services\LoyaltyService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class PackageLogController extends Controller
{
    public function store(Request $request, Package $package, CustomerNotificationService $notifications, LoyaltyService $loyalty)
    {
        $this->authorizeTenantPackage($package);

        $data = $request->validate([
            'status_id' => 'required|exists:package_statuses,id',
            'location' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
            'created_at' => 'nullable|date',
        ]);

        $status = PackageStatus::where('tenant_id', auth()->user()->tenant_id)->findOrFail($data['status_id']);
        $oldStatus = $package->status_id;

        $package->update([
            'status_id' => $status->id,
            'date_delivered' => stripos($status->name, 'delivered') !== false ? now() : $package->date_delivered,
        ]);

        $log = PackageLog::create([
            'tenant_id' => $package->tenant_id,
            'package_id' => $package->id,
            'status_id' => $status->id,
            'note' => $data['note'] ?: 'Status updated to '.$status->name,
            'location' => $data['location'] ?? optional(auth()->user()->outlet)->name,
            'added_by' => auth()->id(),
            'created_at' => ! empty($data['created_at']) ? Carbon::parse($data['created_at']) : now(),
        ]);

        ActivityLog::record('tracking_updated', 'packages', 'Added package log for '.$package->tracking_number, [
            'package_id' => $package->id,
            'status_id' => $status->id,
        ]);

        if (stripos($status->name, 'delivered') !== false && $package->customer) {
            $loyalty->refreshCustomerTier($package->customer);
            $loyalty->awardPackageCollected($package->fresh('customer'));
        }

        return back()->with('success', 'Package tracking log updated successfully.');
    }

    private function authorizeTenantPackage(Package $package): void
    {
        abort_if($package->tenant_id !== auth()->user()->tenant_id, 404);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            abort_if($package->outlet_id !== auth()->user()->outlet_id, 404);
        }
    }
}
