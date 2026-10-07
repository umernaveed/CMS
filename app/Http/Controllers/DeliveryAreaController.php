<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeliveryArea;
use App\Models\DeliveryTimeSlot;
use Illuminate\Http\Request;

class DeliveryAreaController extends Controller
{
    public function index(Request $request)
    {
        $areas = DeliveryArea::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('timeSlots')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($this->perPage(request(), 15))
            ->appends($request->query());

        return view('courier.delivery-areas.index', compact('areas'));
    }

    public function create()
    {
        return view('courier.delivery-areas.create', ['area' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['geofence_coordinates'] = $this->coordinates($data['geofence_coordinates'] ?? null);

        $area = DeliveryArea::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLog::record('created', 'delivery_areas', 'Created delivery location '.$area->name);

        return redirect()->route('courier.delivery-areas.index')->with('success', 'Delivery location created successfully.');
    }

    public function edit(DeliveryArea $deliveryArea)
    {
        $this->authorizeArea($deliveryArea);

        $deliveryArea->load(['timeSlots' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time')]);

        return view('courier.delivery-areas.edit', ['area' => $deliveryArea]);
    }

    public function update(Request $request, DeliveryArea $deliveryArea)
    {
        $this->authorizeArea($deliveryArea);
        $data = $this->validated($request);
        $data['geofence_coordinates'] = $this->coordinates($data['geofence_coordinates'] ?? null);

        $deliveryArea->update($data + [
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLog::record('updated', 'delivery_areas', 'Updated delivery location '.$deliveryArea->name);

        return redirect()->route('courier.delivery-areas.index')->with('success', 'Delivery location updated successfully.');
    }

    public function destroy(DeliveryArea $deliveryArea)
    {
        $this->authorizeArea($deliveryArea);

        if ($deliveryArea->timeSlots()->exists()) {
            return back()->withErrors(['area' => 'This location has time slots. Disable it instead of deleting it.']);
        }

        $name = $deliveryArea->name;
        $deliveryArea->delete();

        ActivityLog::record('deleted', 'delivery_areas', 'Deleted delivery location '.$name);

        return redirect()->route('courier.delivery-areas.index')->with('success', 'Delivery location deleted successfully.');
    }

    public function storeTimeSlot(Request $request, DeliveryArea $deliveryArea)
    {
        $this->authorizeArea($deliveryArea);
        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'max_requests' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        DeliveryTimeSlot::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'area_id' => $deliveryArea->id,
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLog::record('created', 'delivery_time_slots', 'Created delivery schedule day for '.$deliveryArea->name);

        return back()->with('success', 'Schedule day added.');
    }

    public function destroyTimeSlot(DeliveryArea $deliveryArea, DeliveryTimeSlot $slot)
    {
        $this->authorizeArea($deliveryArea);
        abort_if($slot->tenant_id !== auth()->user()->tenant_id || $slot->area_id !== $deliveryArea->id, 404);
        $slot->delete();

        ActivityLog::record('deleted', 'delivery_time_slots', 'Deleted delivery schedule day for '.$deliveryArea->name);

        return back()->with('success', 'Schedule day deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'base_cost' => 'required|numeric|min:0',
            'per_km_cost' => 'nullable|numeric|min:0',
            'pricing_mode' => 'required|in:fixed,distance',
            'geofence_type' => 'required|in:none,circle,polygon',
            'center_lat' => 'nullable|numeric|between:-90,90',
            'center_lng' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0',
            'geofence_coordinates' => 'nullable|string',
        ]);
    }

    private function authorizeArea(DeliveryArea $area): void
    {
        abort_if($area->tenant_id !== auth()->user()->tenant_id, 404);
    }

    private function coordinates(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return collect(explode("\n", $value))
            ->map(function ($line) {
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                    return null;
                }

                return ['lat' => (float) $parts[0], 'lng' => (float) $parts[1]];
            })
            ->filter()
            ->values()
            ->all() ?: null;
    }
}
