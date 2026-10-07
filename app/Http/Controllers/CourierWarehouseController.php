<?php

namespace App\Http\Controllers;

use App\Models\SuperadminLogisticsPackage;
use App\Models\Package;
use Illuminate\Http\Request;

class CourierWarehouseController extends Controller
{
    public function unmanifest(Request $request)
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $packages = SuperadminLogisticsPackage::with(['manifest', 'invoiceItems.invoice', 'courierPackage.manifest'])
            ->where('tenant_id', $tenantId)
            ->whereNull('courier_package_id')
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->status) {
                    'awaiting_check_in' => $query->whereNull('checked_in_at'),
                    'checked_in' => $query->whereNotNull('checked_in_at')->whereNull('checked_out_at'),
                    'checked_out' => $query->whereNotNull('checked_out_at'),
                    default => null,
                };
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('tracking_number', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('manifest', fn ($manifest) => $manifest->where('manifest_number', 'like', $term));
                });
            })
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->withQueryString();

        $summary = [
            'total' => SuperadminLogisticsPackage::where('tenant_id', $tenantId)->whereNull('courier_package_id')->count(),
            'awaitingCheckIn' => SuperadminLogisticsPackage::where('tenant_id', $tenantId)->whereNull('courier_package_id')->whereNull('checked_in_at')->count(),
            'checkedIn' => SuperadminLogisticsPackage::where('tenant_id', $tenantId)->whereNull('courier_package_id')->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
            'checkedOut' => SuperadminLogisticsPackage::where('tenant_id', $tenantId)->whereNull('courier_package_id')->whereNotNull('checked_out_at')->count(),
        ];

        $inHousePackages = Package::with(['customer.user', 'status'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('manifest_id')
                    ->orWhereNull('is_in_manifest')
                    ->orWhere('is_in_manifest', false);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('tracking_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhereHas('customer.user', fn ($user) => $user->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest()
            ->limit(20)
            ->get();

        return view('courier.warehouse.unmanifest', compact('packages', 'summary', 'inHousePackages'));
    }
}
