<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\DeliveryRequest;
use App\Models\DeliveryTimeSlot;
use App\Models\Package;
use App\Models\User;
use App\Services\CustomerNotificationService;
use App\Services\CourierNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryRequestController extends Controller
{
    public function courierIndex(Request $request, CourierNotificationService $courierNotifications)
    {
        $courierNotifications->markRead('delivery_request');
        $requests = $this->filteredTenantRequests($request)
            ->with(['customer.user', 'area', 'outlet', 'assignee'])
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());
        $areas = DeliveryArea::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();
        $deliveryStaff = $this->deliveryStaff();

        $historyMode = false;

        return view('courier.delivery.index', compact('requests', 'areas', 'deliveryStaff', 'historyMode'));
    }

    public function history(Request $request)
    {
        $request->merge(['status_history' => true]);
        $requests = $this->filteredTenantRequests($request)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->with(['customer.user', 'area', 'outlet', 'assignee'])
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());
        $areas = DeliveryArea::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();
        $deliveryStaff = $this->deliveryStaff();
        $historyMode = true;

        return view('courier.delivery.index', compact('requests', 'areas', 'deliveryStaff', 'historyMode'));
    }

    public function updateStatus(Request $request, DeliveryRequest $delivery, CustomerNotificationService $notifications)
    {
        $this->authorizeRequest($delivery);
        $data = $request->validate([
            'status' => 'required|in:pending,approved,assigned,picked_up,in_transit,delivered,cancelled',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        if (! empty($data['assigned_to'])) {
            abort_unless($this->deliveryStaff()->contains('id', (int) $data['assigned_to']), 422);
        }

        $previousStatus = $delivery->status;
        $previousAssignee = $delivery->assigned_to;
        $assignedTo = $data['assigned_to'] ?? $delivery->assigned_to;
        $status = $data['status'];
        if ($assignedTo && in_array($status, ['pending', 'approved'], true)) {
            $status = 'assigned';
        }

        $delivery->update([
            'status' => $status,
            'assigned_to' => $assignedTo,
            'approved_by' => in_array($status, ['approved', 'assigned'], true) ? auth()->id() : $delivery->approved_by,
            'approved_at' => in_array($status, ['approved', 'assigned'], true) && ! $delivery->approved_at ? now() : $delivery->approved_at,
            'delivered_at' => $status === 'delivered' ? now() : $delivery->delivered_at,
        ]);

        if ($previousStatus !== $delivery->status) {
            $notifications->deliveryRequestStatusUpdated($delivery->fresh(['tenant', 'customer.user', 'assignee']), $previousStatus);
        }

        $message = $previousAssignee !== $delivery->assigned_to
            ? 'Delivery request reassigned.'
            : 'Delivery request updated.';

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $requests = $this->filteredTenantRequests($request)
            ->with(['customer.user', 'area', 'outlet', 'assignee'])
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($requests) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['request_number', 'customer', 'mailbox', 'area', 'package_count', 'package_total', 'delivery_cost', 'delivery_type', 'assigned_to', 'recipient_name', 'recipient_phone', 'delivery_address', 'preferred_date', 'status', 'created_at']);
            foreach ($requests as $request) {
                fputcsv($out, [
                    $request->request_number,
                    optional(optional($request->customer)->user)->full_name,
                    $request->mailbox,
                    optional($request->area)->name,
                    $request->package_count,
                    $request->package_total,
                    $request->delivery_cost,
                    $request->delivery_type,
                    optional($request->assignee)->full_name ?: optional($request->assignee)->email,
                    $request->recipient_name,
                    $request->recipient_phone,
                    $request->delivery_address,
                    optional($request->preferred_date)->format('Y-m-d'),
                    $request->status,
                    optional($request->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, 'delivery-requests-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request)
    {
        $requests = $this->filteredTenantRequests($request)
            ->with(['customer.user', 'area', 'outlet', 'assignee'])
            ->latest()
            ->get();

        return view('courier.delivery.print', compact('requests'));
    }

    public function download(Request $request): StreamedResponse
    {
        return $this->export($request);
    }

    public function customerIndex(Request $request)
    {
        $customer = auth()->user()->customer;
        $requests = $this->filteredCustomerRequests($request, false, optional($customer)->id)
            ->with(['area', 'timeSlot']);
        $this->sortDeliveryRequests($requests, $request);
        $requests = $requests
            ->paginate($this->perPage($request, 15))
            ->withQueryString();
        $historyMode = false;

        return view('customer.delivery.index', compact('requests', 'historyMode'));
    }

    public function customerHistory(Request $request)
    {
        $customer = auth()->user()->customer;
        $requests = $this->filteredCustomerRequests($request, true, optional($customer)->id)
            ->with(['area', 'timeSlot']);
        $this->sortDeliveryRequests($requests, $request);
        $requests = $requests
            ->paginate($this->perPage($request, 15))
            ->withQueryString();
        $historyMode = true;

        return view('customer.delivery.index', compact('requests', 'historyMode'));
    }

    public function create()
    {
        $customer = auth()->user()->customer;
        $openRequestPackageIds = $customer
            ? $this->openRequestPackageIds($customer->id)
            : collect();
        $packages = Package::where('tenant_id', auth()->user()->tenant_id)
            ->where('customer_id', optional($customer)->id)
            ->whereNotIn('id', $openRequestPackageIds)
            ->where(function ($query) {
                $query->whereNull('date_delivered')
                    ->whereHas('status', function ($status) {
                        $status->where('slug', 'like', '%ready%')
                            ->orWhere('slug', 'like', '%pickup%')
                            ->orWhere('name', 'like', '%ready%')
                            ->orWhere('name', 'like', '%pickup%')
                            ->orWhere('name', 'like', '%available%');
                    });
            })
            ->whereNull('date_delivered')
            ->with('invoiceItems.invoice')
            ->latest()
            ->get();
        $areas = DeliveryArea::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->orderBy('name')->get();
        $timeSlots = DeliveryTimeSlot::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time')->get();

        return view('customer.delivery.create', compact('packages', 'areas', 'timeSlots'));
    }

    public function store(Request $request, CourierNotificationService $courierNotifications)
    {
        $customer = auth()->user()->customer;
        abort_if(! $customer, 404);

        $data = $request->validate([
            'package_ids' => 'required|array|min:1',
            'package_ids.*' => 'integer|distinct|exists:packages,id',
            'area_id' => 'nullable|exists:delivery_areas,id',
            'preferred_time_slot_id' => 'nullable|integer',
            'delivery_type' => 'required|in:regular,express,pickup',
            'pickup_location' => 'nullable|string|max:255',
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'delivery_address' => 'required|string',
            'destination_lat' => 'nullable|numeric|between:-90,90',
            'destination_lng' => 'nullable|numeric|between:-180,180',
            'preferred_date' => 'nullable|date',
            'special_instructions' => 'nullable|string',
        ]);

        $packages = Package::where('tenant_id', auth()->user()->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereIn('id', $data['package_ids'])
            ->with('invoiceItems.invoice')
            ->get();
        abort_if($packages->count() !== count($data['package_ids']), 404);

        $area = ! empty($data['area_id'])
            ? DeliveryArea::where('tenant_id', auth()->user()->tenant_id)->findOrFail($data['area_id'])
            : null;
        if ($data['delivery_type'] !== 'pickup' && ! $area) {
            throw ValidationException::withMessages(['area_id' => 'Choose a pickup location and delivery rate.']);
        }
        if ($area && $area->pricing_mode === 'distance'
            && (! isset($data['destination_lat'], $data['destination_lng']) || ! $area->center_lat || ! $area->center_lng)) {
            throw ValidationException::withMessages([
                'destination_lat' => 'Distance pricing requires pickup and destination map coordinates.',
            ]);
        }
        if ($area && isset($data['destination_lat'], $data['destination_lng'])) {
            abort_unless($this->pointIsWithinArea($area, (float) $data['destination_lat'], (float) $data['destination_lng']), 422, 'Destination address is outside the selected delivery location.');
        }
        $timeSlot = ! empty($data['preferred_time_slot_id'])
            ? DeliveryTimeSlot::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->findOrFail($data['preferred_time_slot_id'])
            : null;
        if ($timeSlot) {
            $data['preferred_date'] = null;
        }
        $deliveryCost = $data['delivery_type'] === 'pickup'
            ? 0
            : $this->deliveryCost($area, (float) ($data['destination_lat'] ?? 0), (float) ($data['destination_lng'] ?? 0));

        $delivery = DB::transaction(function () use ($customer, $data, $packages, $area, $timeSlot, $deliveryCost) {
            $customer->newQuery()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $duplicatePackageIds = $this->openRequestPackageIds($customer->id)
                ->intersect($packages->pluck('id'));

            if ($duplicatePackageIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'package_ids' => 'One or more selected packages already have an open delivery request. Please refresh and choose from the available packages.',
                ]);
            }

            return DeliveryRequest::create([
                'tenant_id' => auth()->user()->tenant_id,
                'customer_id' => $customer->id,
                'outlet_id' => $customer->outlet_id,
                'area_id' => $data['area_id'] ?? null,
                'request_number' => 'DR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'mailbox' => $customer->mailbox,
                'package_ids' => $packages->pluck('id')->values()->all(),
                'package_count' => $packages->count(),
                'package_total' => $packages->sum(fn (Package $package) => $package->customerVisibleCostJmd()),
                'pickup_location' => $data['pickup_location'] ?? optional($area)->name ?? optional($customer->outlet)->name,
                'delivery_cost' => $deliveryCost,
                'delivery_type' => $data['delivery_type'],
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'delivery_address' => $data['delivery_address'],
                'destination_lat' => $data['destination_lat'] ?? null,
                'destination_lng' => $data['destination_lng'] ?? null,
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_time_slot_id' => optional($timeSlot)->id,
                'special_instructions' => $data['special_instructions'] ?? null,
                'status' => 'pending',
            ]);
        });

        $courierNotifications->notify(
            $delivery->tenant_id,
            $delivery->outlet_id,
            'delivery_request',
            'New Delivery Request',
            ($customer->user->full_name ?: $customer->mailbox).' submitted '.$delivery->request_number.'.',
            route('courier.delivery.index'),
            ['delivery_request_id' => $delivery->id]
        );

        return redirect()->route('customer.delivery.index')->with('success', 'Delivery request submitted.');
    }

    private function openRequestPackageIds(int $customerId)
    {
        return DeliveryRequest::where('tenant_id', auth()->user()->tenant_id)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->get(['package_ids'])
            ->pluck('package_ids')
            ->flatten()
            ->filter()
            ->map(fn ($packageId) => (int) $packageId)
            ->unique()
            ->values();
    }

    private function tenantRequests()
    {
        $query = DeliveryRequest::where('tenant_id', auth()->user()->tenant_id);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function filteredTenantRequests(Request $request)
    {
        return $this->tenantRequests()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('request_number', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhere('recipient_name', 'like', $term)
                        ->orWhere('recipient_phone', 'like', $term)
                        ->orWhere('delivery_address', 'like', $term)
                        ->orWhereHas('customer.user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('delivery_type'), fn ($query) => $query->where('delivery_type', $request->delivery_type))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('area_id'), fn ($query) => $query->where('area_id', $request->integer('area_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('preferred_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('preferred_date', '<=', $request->date_to));
    }

    private function filteredCustomerRequests(Request $request, bool $historyMode, ?int $customerId)
    {
        abort_if(! $customerId, 404);

        return DeliveryRequest::where('tenant_id', auth()->user()->tenant_id)
            ->where('customer_id', $customerId)
            ->when($historyMode,
                fn ($query) => $query->whereIn('status', ['delivered', 'cancelled']),
                fn ($query) => $query->whereNotIn('status', ['delivered', 'cancelled'])
            )
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('request_number', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhere('recipient_name', 'like', $term)
                        ->orWhere('recipient_phone', 'like', $term)
                        ->orWhere('delivery_address', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('delivery_type'), fn ($query) => $query->where('delivery_type', $request->delivery_type))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('preferred_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('preferred_date', '<=', $request->date_to));
    }
    private function sortDeliveryRequests($query, Request $request): void
    {
        $sorts = [
            'created_at' => 'created_at',
            'preferred_date' => 'preferred_date',
            'request_number' => 'request_number',
            'recipient_name' => 'recipient_name',
            'status' => 'status',
            'total_jmd' => DB::raw('(COALESCE(package_total, 0) + COALESCE(delivery_cost, 0))'),
        ];

        $sort = $sorts[$request->input('sort')] ?? 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
    private function authorizeRequest(DeliveryRequest $delivery): void
    {
        abort_if($delivery->tenant_id !== auth()->user()->tenant_id, 404);
        abort_if(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id && $delivery->outlet_id !== auth()->user()->outlet_id, 404);
    }

    private function deliveryCost(?DeliveryArea $area, float $lat, float $lng): float
    {
        if (! $area) {
            return 0;
        }

        $distance = ($area->pricing_mode === 'distance' && $lat && $lng && $area->center_lat && $area->center_lng)
            ? $this->distanceKm((float) $area->center_lat, (float) $area->center_lng, $lat, $lng)
            : 0;

        return round((float) $area->base_cost + ($area->pricing_mode === 'distance' ? $distance * (float) $area->per_km_cost : 0), 2);
    }

    private function pointIsWithinArea(DeliveryArea $area, float $lat, float $lng): bool
    {
        if ($area->geofence_type === 'circle') {
            if (! $area->center_lat || ! $area->center_lng || ! $area->radius_km) {
                return true;
            }

            return $this->distanceKm((float) $area->center_lat, (float) $area->center_lng, $lat, $lng) <= (float) $area->radius_km;
        }

        if ($area->geofence_type === 'polygon' && is_array($area->geofence_coordinates) && count($area->geofence_coordinates) >= 3) {
            return $this->pointInPolygon($lat, $lng, $area->geofence_coordinates);
        }

        return true;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function pointInPolygon(float $lat, float $lng, array $points): bool
    {
        $inside = false;
        $count = count($points);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $latI = (float) ($points[$i]['lat'] ?? 0);
            $lngI = (float) ($points[$i]['lng'] ?? 0);
            $latJ = (float) ($points[$j]['lat'] ?? 0);
            $lngJ = (float) ($points[$j]['lng'] ?? 0);

            if ((($lngI > $lng) !== ($lngJ > $lng)) && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 0.0000001) + $latI)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function deliveryStaff()
    {
        return User::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->whereIn('user_type', ['tenant_admin', 'staff'])
            ->when(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id, function ($query) {
                $query->where(function ($nested) {
                    $nested->whereNull('outlet_id')->orWhere('outlet_id', auth()->user()->outlet_id);
                });
            })
            ->with('roles.permissions')
            ->orderBy('full_name')
            ->orderBy('email')
            ->get()
            ->filter(fn ($user) => $user->hasPermission('manage_delivery'))
            ->values();
    }
    protected function perPage(Request $request, int $default, ?int $total = null): int
    {
        if ($request->input('per_page') === 'all') {
            return 10000;
        }

        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $default;
    }
}

