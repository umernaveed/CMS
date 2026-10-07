@extends('layouts.app')

@section('title', 'Request Delivery')

@section('content')
@php($dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
<div class="mb-4"><h1 class="h3 mb-1">Request Delivery</h1><p class="text-muted mb-0">Choose packages and your preferred delivery window.</p></div>
<form method="POST" action="{{ route('customer.delivery.store') }}" class="panel p-4">
    @csrf
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Packages</label>
            <div class="row g-2">
                @forelse($packages as $package)
                    <div class="col-md-6">
                        <label class="border rounded p-3 d-block">
                            <input type="checkbox" name="package_ids[]" value="{{ $package->id }}" class="form-check-input me-2" data-package-weight="{{ (float) $package->weight }}" data-package-cuft="{{ (float) ($package->volume_cuft ?: $package->weight) }}" data-package-cbm="{{ (float) ($package->volume_cbm ?: (($package->volume_cuft ?: $package->weight) / \App\Models\WeightRate::CBM_TO_CUFT)) }}" data-package-is-sea="{{ $package->isSeaFreight() ? '1' : '0' }}" data-package-total="{{ $package->customerVisibleCostJmd() }}">
                            <strong>{{ $package->tracking_number }}</strong>
                            <span class="text-muted d-block small">{{ $package->description ?: $package->merchant }} - {{ $package->measureDisplay() }} - {{ $package->customerVisibleCostDisplay() }}</span>
                        </label>
                    </div>
                @empty
                    <div class="col-12 text-muted">No delivery-ready packages are available.</div>
                @endforelse
            </div>
        </div>
        <div class="col-md-4"><label class="form-label">Total Weight / Volume</label><input class="form-control" id="delivery-total-weight" value="0.00 lb" readonly></div>
        <div class="col-md-4"><label class="form-label">Number of Packages</label><input class="form-control" id="delivery-package-count" value="0" readonly></div>
        <div class="col-md-4"><label class="form-label">Pickup Location</label><input name="pickup_location" id="delivery-pickup-location" class="form-control" value="{{ optional(auth()->user()->customer?->outlet)->name }}" placeholder="Warehouse or branch"></div>
        <div class="col-md-4"><label class="form-label">Delivery Rate / Zone</label><select name="area_id" id="delivery-area" class="form-select"><option value="">Select rate</option>@foreach($areas as $area)<option value="{{ $area->id }}" data-name="{{ $area->name }}" data-mode="{{ $area->pricing_mode ?? 'fixed' }}" data-base="{{ (float) $area->base_cost }}" data-per-km="{{ (float) $area->per_km_cost }}" data-lat="{{ $area->center_lat }}" data-lng="{{ $area->center_lng }}">{{ $area->name }} - {{ ($area->pricing_mode ?? 'fixed') === 'distance' ? 'Base + distance' : 'Fixed' }} JMD {{ number_format($area->base_cost, 2) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Schedule Day</label><select name="preferred_time_slot_id" id="delivery-time-slot" class="form-select"><option value="">Any time</option>@foreach($timeSlots as $slot)<option value="{{ $slot->id }}" @selected((string) old('preferred_time_slot_id') === (string) $slot->id)>{{ $dayNames[(int) $slot->day_of_week] ?? 'Day '.$slot->day_of_week }}: {{ substr($slot->start_time,0,5) }}-{{ substr($slot->end_time,0,5) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Preferred Date</label><input type="date" name="preferred_date" id="delivery-preferred-date" class="form-control" value="{{ old('preferred_date') }}"></div>
        <div class="col-md-4"><label class="form-label">Delivery Type</label><select name="delivery_type" class="form-select"><option value="regular">Regular</option><option value="express">Express</option><option value="pickup">Pickup</option></select></div>
        <div class="col-md-4"><label class="form-label">Recipient Name</label><input name="recipient_name" class="form-control" value="{{ auth()->user()->full_name }}" required></div>
        <div class="col-md-4"><label class="form-label">Recipient Phone</label><input name="recipient_phone" class="form-control" value="{{ auth()->user()->phone }}" required></div>
        <div class="col-12"><label class="form-label">Delivery Address</label><input name="delivery_address" class="form-control" value="{{ auth()->user()->address_line1 }}" required></div>
        <div class="col-md-4"><label class="form-label">Destination Latitude</label><input type="number" step="0.0000001" name="destination_lat" id="delivery-lat" class="form-control" placeholder="Map latitude"></div>
        <div class="col-md-4"><label class="form-label">Destination Longitude</label><input type="number" step="0.0000001" name="destination_lng" id="delivery-lng" class="form-control" placeholder="Map longitude"></div>
        <div class="col-md-4"><label class="form-label">Estimated Delivery Charge</label><input id="delivery-estimate" class="form-control" value="Select a delivery rate" readonly></div>
        <div class="col-12"><label class="form-label">Special Instructions</label><textarea name="special_instructions" class="form-control" rows="3"></textarea></div>
        <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Submit Request</button><a href="{{ route('customer.delivery.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = Array.from(document.querySelectorAll('input[name="package_ids[]"]'));
    const totalWeight = document.getElementById('delivery-total-weight');
    const packageCount = document.getElementById('delivery-package-count');
    const area = document.getElementById('delivery-area');
    const pickup = document.getElementById('delivery-pickup-location');
    const lat = document.getElementById('delivery-lat');
    const lng = document.getElementById('delivery-lng');
    const estimate = document.getElementById('delivery-estimate');
    const timeSlot = document.getElementById('delivery-time-slot');
    const preferredDate = document.getElementById('delivery-preferred-date');

    function syncPreferredDateAvailability() {
        if (!timeSlot || !preferredDate) return;

        const hasScheduledDay = timeSlot.value !== '';
        preferredDate.disabled = hasScheduledDay;
        preferredDate.required = !hasScheduledDay;
        if (hasScheduledDay) {
            preferredDate.value = '';
        }
    }

    function syncSummary() {
        const selected = checks.filter(check => check.checked);
        const airWeight = selected.filter(check => check.dataset.packageIsSea !== '1').reduce((sum, check) => sum + Number(check.dataset.packageWeight || 0), 0);
        const seaCuft = selected.filter(check => check.dataset.packageIsSea === '1').reduce((sum, check) => sum + Number(check.dataset.packageCuft || 0), 0);
        const seaCbm = selected.filter(check => check.dataset.packageIsSea === '1').reduce((sum, check) => sum + Number(check.dataset.packageCbm || 0), 0);
        const measureParts = [];
        if (airWeight > 0 || seaCuft === 0) measureParts.push(airWeight.toFixed(2) + ' lb');
        if (seaCuft > 0) measureParts.push(seaCuft.toFixed(2) + ' cu.ft / ' + seaCbm.toFixed(3) + ' CBM');
        if (totalWeight) totalWeight.value = measureParts.join(' + ');
        if (packageCount) packageCount.value = selected.length;
    }

    checks.forEach(check => check.addEventListener('change', syncSummary));
    function distanceKm(lat1, lng1, lat2, lng2) {
        const toRad = value => value * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
    function syncDeliveryEstimate() {
        const option = area.options[area.selectedIndex];
        if (!option || !option.value) {
            estimate.value = 'Select a delivery rate';
            return;
        }
        if (pickup && option.dataset.name) pickup.value = option.dataset.name;
        let total = Number(option.dataset.base || 0);
        if (option.dataset.mode === 'distance') {
            const values = [option.dataset.lat, option.dataset.lng, lat.value, lng.value].map(Number);
            if (values.some(value => !Number.isFinite(value) || value === 0)) {
                estimate.value = 'Enter destination coordinates';
                return;
            }
            total += distanceKm(...values) * Number(option.dataset.perKm || 0);
        }
        estimate.value = 'JMD ' + total.toFixed(2);
    }
    [area, lat, lng].forEach(field => field && field.addEventListener('change', syncDeliveryEstimate));
    [lat, lng].forEach(field => field && field.addEventListener('input', syncDeliveryEstimate));
    if (timeSlot) timeSlot.addEventListener('change', syncPreferredDateAvailability);
    syncSummary();
    syncDeliveryEstimate();
    syncPreferredDateAvailability();
});
</script>
@endsection


