@php
    $coordinates = old('geofence_coordinates');
    if ($coordinates === null && optional($area)->geofence_coordinates) {
        $coordinates = collect($area->geofence_coordinates)->map(fn ($point) => ($point['lat'] ?? '').', '.($point['lng'] ?? ''))->implode("\n");
    }
@endphp
<div class="panel p-4">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Location Name</label><input name="name" class="form-control" value="{{ old('name', optional($area)->name) }}" required></div>
        <div class="col-md-4"><label class="form-label">Base Rate JMD</label><input type="number" step="0.01" name="base_cost" class="form-control" value="{{ old('base_cost', optional($area)->base_cost ?? 0) }}" required></div>
        <div class="col-md-4"><label class="form-label">Per KM Rate JMD</label><input type="number" step="0.01" name="per_km_cost" class="form-control" value="{{ old('per_km_cost', optional($area)->per_km_cost ?? 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Pricing Method</label><select name="pricing_mode" class="form-select"><option value="fixed" @selected(old('pricing_mode', optional($area)->pricing_mode ?? 'fixed') === 'fixed')>Fixed base rate</option><option value="distance" @selected(old('pricing_mode', optional($area)->pricing_mode) === 'distance')>Base + distance</option></select></div>
        <div class="col-md-4"><label class="form-label">Geofence Type</label><select name="geofence_type" class="form-select"><option value="none" @selected(old('geofence_type', optional($area)->geofence_type ?? 'none') === 'none')>None</option><option value="circle" @selected(old('geofence_type', optional($area)->geofence_type) === 'circle')>Circle</option><option value="polygon" @selected(old('geofence_type', optional($area)->geofence_type) === 'polygon')>Polygon</option></select></div>
        <div class="col-md-4"><label class="form-label">Pickup Latitude</label><input type="number" step="0.0000001" name="center_lat" class="form-control" value="{{ old('center_lat', optional($area)->center_lat) }}"></div>
        <div class="col-md-4"><label class="form-label">Pickup Longitude</label><input type="number" step="0.0000001" name="center_lng" class="form-control" value="{{ old('center_lng', optional($area)->center_lng) }}"></div>
        <div class="col-md-4"><label class="form-label">Radius KM</label><input type="number" step="0.01" name="radius_km" class="form-control" value="{{ old('radius_km', optional($area)->radius_km) }}"></div>
        <div class="col-md-8"><label class="form-label">Polygon Coordinates</label><textarea name="geofence_coordinates" class="form-control" rows="4" placeholder="18.0179, -76.8099">{{ $coordinates }}</textarea><small class="text-muted">Use one latitude, longitude pair per line, or paste JSON points.</small></div>
        <div class="col-12">
            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($area)->is_active ?? true))>
                <span class="form-check-label">Active location</span>
            </label>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2"><a href="{{ route('courier.delivery-areas.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary">{{ $button ?? 'Save Location' }}</button></div>
    </div>
</div>
