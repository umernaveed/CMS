<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Models\DeliveryRequest;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestReceipt;
use App\Models\User;
use App\Services\CourierNotificationService;
use App\Services\SubscriptionLimitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourierController extends Controller
{
    public function dashboard(Request $request, SubscriptionLimitService $limits)
    {
        $tenantId = auth()->user()->tenant_id;
        $chartPeriod = strtolower((string) $request->query('chart_period', 'week'));
        $chartPeriod = $chartPeriod === 'months' ? 'month' : $chartPeriod;
        if (! in_array($chartPeriod, ['today', 'yesterday', 'week', 'month'], true)) {
            $chartPeriod = 'week';
        }
        [$chartStart, $chartEnd, $chartLabel] = $this->dashboardDateRange($chartPeriod);

        $packagesQuery = Package::where('tenant_id', $tenantId);
        $customersQuery = Customer::where('tenant_id', $tenantId);
        $invoicesQuery = Invoice::where('tenant_id', $tenantId);
        $deliveryRequestsQuery = DeliveryRequest::where('tenant_id', $tenantId);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $packagesQuery->where('outlet_id', auth()->user()->outlet_id);
            $customersQuery->where('outlet_id', auth()->user()->outlet_id);
            $invoicesQuery->where('outlet_id', auth()->user()->outlet_id);
            $deliveryRequestsQuery->where('outlet_id', auth()->user()->outlet_id);
        }

        $periodPackagesQuery = (clone $packagesQuery)->whereBetween('created_at', [$chartStart, $chartEnd]);

        $packages = (clone $periodPackagesQuery)->with(['status', 'customer.user', 'invoiceItems.invoice'])->latest()->take(10)->get();
        $totalPackages = (clone $packagesQuery)->count();
        $packagesToday = (clone $packagesQuery)->whereDate('created_at', today())->count();
        $pendingPackages = (clone $packagesQuery)->whereHas('status', function ($q) {
            $q->whereIn('slug', ['pending', 'received', 'ready-for-pickup']);
        })->count();
        $readyPackages = (clone $packagesQuery)->whereHas('status', function ($q) {
            $q->where('slug', 'ready-for-pickup');
        })->count();
        $deliveredPackages = (clone $packagesQuery)
            ->where(function ($query) {
                $query->whereNotNull('date_delivered')
                    ->orWhereHas('status', function ($status) {
                        $status->where('slug', 'delivered')
                            ->orWhere('name', 'like', '%delivered%')
                            ->orWhere('name', 'like', '%picked%');
                    });
            })
            ->count();
        $openPackages = max(0, $totalPackages - $deliveredPackages);
        $warehousePackagesQuery = $this->packagesAtWarehousesQuery(clone $packagesQuery);
        $packagesAtWarehouses = (clone $warehousePackagesQuery)->count();
        $warehouseBreakdown = $this->warehouseBreakdown(clone $warehousePackagesQuery);
        $totalRevenue = (clone $invoicesQuery)->where('payment_status', 'paid')->sum('total_jmd');
        $todayRevenue = (clone $invoicesQuery)
            ->where('payment_status', 'paid')
            ->whereDate('paid_at', today())
            ->sum('total_jmd');
        $deliveryRequestsCount = (clone $deliveryRequestsQuery)->count();
        $pendingDeliveryRequests = (clone $deliveryRequestsQuery)->whereIn('status', ['pending', 'approved', 'assigned', 'picked_up', 'in_transit'])->count();
        $assignedDeliveryRequests = (clone $deliveryRequestsQuery)
            ->where('assigned_to', auth()->id())
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->with(['customer.user', 'area'])
            ->latest()
            ->take(5)
            ->get();
        $customersCount = (clone $customersQuery)->count();
        $invoicesDue = (clone $invoicesQuery)->whereIn('payment_status', ['unpaid', 'partial'])->count();
        $amountDue = (clone $invoicesQuery)->whereIn('payment_status', ['unpaid', 'partial'])->sum('total_jmd');
        $subscriptionUsage = $limits->usage($tenantId);
        $recentActivities = ActivityLog::with('user')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(6)
            ->get();
        $activeStaff = User::where('tenant_id', $tenantId)
            ->whereIn('user_type', ['tenant_admin', 'staff'])
            ->with('outlet')
            ->orderByDesc('is_active')
            ->orderBy('full_name')
            ->limit(5)
            ->get();
        $dashboardStatusIds = PackageStatus::where('tenant_id', $tenantId)
            ->whereIn('slug', ['delivered', 'ready-for-pickup'])
            ->pluck('id', 'slug');
        $dashboardLinks = [
            'warehouse' => route('courier.packages.index', ['at_warehouse' => 1]),
            'delivery' => route('courier.delivery.index'),
            'ready' => route('courier.packages.index', ['status_id' => $dashboardStatusIds['ready-for-pickup'] ?? null]),
            'revenue' => route('courier.invoices.index', ['payment_status' => 'paid']),
            'delivered' => route('courier.packages.index', ['status_id' => $dashboardStatusIds['delivered'] ?? null]),
            'in_transit' => route('courier.packages.index', ['in_transit' => 1]),
            'available' => route('courier.packages.index', ['status_id' => $dashboardStatusIds['ready-for-pickup'] ?? null]),
        ];
        $statusBreakdown = [
            'Delivered' => $deliveredPackages,
            'In Transit' => $this->inTransitPackagesQuery(clone $packagesQuery)->count(),
            'Available' => $this->availablePackagesQuery(clone $packagesQuery)->count(),
        ];
        $weeklyIntake = $this->dashboardChartPoints($packagesQuery, $chartPeriod, $chartStart, $chartEnd);
        $chartFilters = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'week' => 'Week',
            'month' => 'Month',
        ];

        return view('courier.dashboard', compact(
            'packages',
            'totalPackages',
            'packagesToday',
            'openPackages',
            'packagesAtWarehouses',
            'warehouseBreakdown',
            'pendingPackages',
            'readyPackages',
            'deliveredPackages',
            'totalRevenue',
            'todayRevenue',
            'deliveryRequestsCount',
            'pendingDeliveryRequests',
            'assignedDeliveryRequests',
            'customersCount',
            'invoicesDue',
            'amountDue',
            'subscriptionUsage',
            'recentActivities',
            'activeStaff',
            'statusBreakdown',
            'weeklyIntake',
            'chartPeriod',
            'chartFilters',
            'chartLabel',
            'dashboardLinks'
        ));
    }

    public function activity(Request $request)
{
    $tenantId = auth()->user()->tenant_id;
    
    // Get activities with pagination
    $activities = ActivityLog::with('user')
        ->where('tenant_id', $tenantId)
        ->when($request->filled('module'), fn ($query) => $query->where('module', $request->module))
        ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->user_id))
        ->when($request->filled('start_date'), fn ($query) => $query->whereDate('created_at', '>=', $request->start_date))
        ->when($request->filled('end_date'), fn ($query) => $query->whereDate('created_at', '<=', $request->end_date))
        ->latest('created_at')
        ->paginate($this->perPage(request(), 20))
        ->withQueryString(); // Preserve filters in pagination links

    // Get ONLY latest 10 package logs - NO pagination here
    $packageLogs = PackageLog::with(['package', 'status', 'user'])
        ->where('tenant_id', $tenantId)
        ->latest('created_at')
        ->limit(10) // Limit to 10, no pagination
        ->get();
    
    // Get users for filter dropdown
    $users = User::where('tenant_id', $tenantId)
        ->orderBy('full_name')
        ->orderBy('email')
        ->get();
    
    // Get modules for filter dropdown
    $modules = ActivityLog::where('tenant_id', $tenantId)
        ->whereNotNull('module')
        ->distinct()
        ->orderBy('module')
        ->pluck('module');

    return view('courier.activity.index', compact('activities', 'packageLogs', 'users', 'modules'));
}

    public function activityExport(Request $request)
    {
        $activities = $this->filteredActivityLogs($request)->latest('created_at')->get();

        return response()->streamDownload(function () use ($activities) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['action', 'module', 'description', 'user', 'created_at']);
            foreach ($activities as $activity) {
                fputcsv($out, [
                    $activity->action,
                    $activity->module,
                    $activity->description,
                    optional($activity->user)->full_name ?: optional($activity->user)->email ?: 'System',
                    optional($activity->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, 'courier-activity-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function activityPrint(Request $request)
    {
        $activities = $this->filteredActivityLogs($request)->latest('created_at')->get();

        return view('courier.activity.print', compact('activities'));
    }

    private function filteredActivityLogs(Request $request)
    {
        return ActivityLog::with('user')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->module))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->user_id))
            ->when($request->filled('start_date'), fn ($query) => $query->whereDate('created_at', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn ($query) => $query->whereDate('created_at', '<=', $request->end_date));
    }

    private function dashboardDateRange(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'Today'],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'Yesterday'],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), now()->format('F Y')],
            default => [
                now()->startOfWeek(Carbon::SUNDAY),
                now()->startOfWeek(Carbon::SUNDAY)->addDays(6)->endOfDay(),
                'Sunday to Saturday',
            ],
        };
    }

    private function dashboardChartPoints($packagesQuery, string $period, Carbon $start, Carbon $end)
    {
        if (in_array($period, ['today', 'yesterday'], true)) {
            return collect(range(0, 21, 3))->map(function ($hour) use ($packagesQuery, $start) {
                $bucketStart = $start->copy()->addHours($hour);
                $bucketEnd = $bucketStart->copy()->addHours(3)->subSecond();

                return [
                    'label' => $bucketStart->format('g A'),
                    'count' => (clone $packagesQuery)->whereBetween('created_at', [$bucketStart, $bucketEnd])->count(),
                ];
            });
        }

        if ($period === 'month') {
            return collect(range(1, $start->daysInMonth))->map(function ($day) use ($packagesQuery, $start) {
                $date = $start->copy()->day($day);

                return [
                    'label' => $date->format('j'),
                    'count' => (clone $packagesQuery)->whereDate('created_at', $date->toDateString())->count(),
                ];
            });
        }

        return collect(range(0, 6))->map(function ($offset) use ($packagesQuery, $start) {
            $date = $start->copy()->addDays($offset);

            return [
                'label' => $date->format('D'),
                'count' => (clone $packagesQuery)->whereDate('created_at', $date->toDateString())->count(),
            ];
        });
    }

    private function packagesAtWarehousesQuery($packagesQuery)
    {
        return $packagesQuery
            ->whereNull('date_delivered')
            ->whereDoesntHave('status', function ($query) {
                $query->where('slug', 'delivered')
                    ->orWhere('name', 'like', '%transit%')
                    ->orWhere('name', 'like', '%out for delivery%');
            })
            ->whereNotIn('id', $this->latestLogPackageIdsWithStatusLike('%transit%'))
            ->whereNotIn('id', $this->latestLogPackageIdsWithStatusLike('%out for delivery%'));
    }

    private function availablePackagesQuery($packagesQuery)
    {
        return $packagesQuery
            ->whereNull('date_delivered')
            ->whereHas('status', function ($status) {
                $status
                    ->whereIn('slug', [
                        'ready-for-pickup',
                        'available-for-pickup',
                        'ready-for-delivery',
                        'available-for-delivery',
                    ])
                    ->orWhere(function ($nested) {
                        $nested->where(function ($availability) {
                            $availability->where('name', 'like', '%Ready%')
                                ->orWhere('name', 'like', '%Available%');
                        })->where(function ($type) {
                            $type->where('name', 'like', '%Pickup%')
                                ->orWhere('name', 'like', '%Delivery%');
                        });
                    });
            });
    }

    private function inTransitPackagesQuery($packagesQuery)
    {
        return $packagesQuery
            ->whereNull('date_delivered')
            ->where(function ($query) {
                $query->whereHas('status', fn ($status) => $status->where('name', 'like', '%transit%'))
                    ->orWhereIn('id', $this->latestLogPackageIdsWithStatusLike('%transit%'));
            });
    }

    private function latestLogPackageIdsWithStatusLike(string $statusName)
    {
        return PackageLog::query()
            ->select('package_logs.package_id')
            ->join('package_statuses', 'package_logs.status_id', '=', 'package_statuses.id')
            ->where('package_logs.tenant_id', auth()->user()->tenant_id)
            ->where('package_statuses.name', 'like', $statusName)
            ->where('package_logs.id', function ($query) {
                $query->selectRaw('MAX(latest_logs.id)')
                    ->from('package_logs as latest_logs')
                    ->whereColumn('latest_logs.package_id', 'package_logs.package_id');
            });
    }

    private function warehouseBreakdown($packagesQuery)
    {
        return $packagesQuery
            ->with(['latestLog', 'outlet'])
            ->get()
            ->map(function ($package) {
                $location = optional($package->latestLog)->location
                    ?: optional($package->outlet)->name
                    ?: 'Unassigned warehouse';

                return trim($location) ?: 'Unassigned warehouse';
            })
            ->countBy()
            ->sortDesc()
            ->take(4);
    }

    public function authorizedUsers(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = \App\Models\AuthorizedUser::with(['customer.user'])
            ->whereHas('customer', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });

        // Filter by customer if specified
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by name or ID number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $authorizedUsers = $query->latest()->paginate($this->perPage(request(), 20));
        
        // Get customers for filter dropdown
        $customers = Customer::where('customers.tenant_id', $tenantId)
            ->whereHas('authorizedUsers')
            ->with('user')
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->orderBy('users.full_name')
            ->select('customers.*')
            ->get();

        return view('courier.authorized-users.index', compact('authorizedUsers', 'customers'));
    }

    public function showAuthorizedUser(\App\Models\AuthorizedUser $authorizedUser)
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Ensure the authorized user belongs to this tenant
        if ($authorizedUser->customer->tenant_id !== $tenantId) {
            abort(404);
        }

        return view('courier.authorized-users.show', compact('authorizedUser'));
    }

    public function purchaseRequests(Request $request, CourierNotificationService $courierNotifications)
    {
        $tenantId = auth()->user()->tenant_id;
        $courierNotifications->markRead('purchase_request');

        $query = PurchaseRequest::with(['customer.user', 'receipts'])
            ->where('tenant_id', $tenantId)
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('purchase_request_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('product_url', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhere('courier', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });

                if (is_numeric($request->q)) {
                    $q->orWhere('price_usd', $request->q);
                }
            });

        $purchaseRequests = $query->latest()->paginate($this->perPage(request(), 20))->withQueryString();

       $customers = Customer::where('customers.tenant_id', $tenantId)  // Specify table alias
    ->join('users', 'customers.user_id', '=', 'users.id')
    ->orderBy('users.full_name')
    ->select('customers.*')
    ->get();

        return view('courier.purchase-requests.index', compact('purchaseRequests', 'customers'));
    }

    public function showPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_if($purchaseRequest->tenant_id !== $tenantId, 404);

        return view('courier.purchase-requests.show', compact('purchaseRequest'));
    }

    public function updatePurchaseRequestStatus(Request $request, PurchaseRequest $purchaseRequest)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_if($purchaseRequest->tenant_id !== $tenantId, 404);

        $validated = $request->validate([
            'status' => 'required|in:submitted,in_process,ordered,completed,payment_applied,approved',
        ]);

        $purchaseRequest->update(['status' => $validated['status']]);

        return back()->with('success', 'Purchase request status updated to '.PurchaseRequest::statuses()[$validated['status']].'.');
    }

    public function purchaseRequestReceipt(PurchaseRequestReceipt $receipt)
    {
        $purchaseRequest = $receipt->purchaseRequest;

        abort_if($purchaseRequest->tenant_id !== auth()->user()->tenant_id, 404);

        abort_if(! Storage::disk('public')->exists($receipt->path), 404);

        return response()->file(Storage::disk('public')->path($receipt->path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($receipt->original_name ?: basename($receipt->path)).'"',
        ]);
    }
}




