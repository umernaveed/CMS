@extends('layouts.app')

@section('title', 'Delivery Locations')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Delivery Locations</h1><p class="text-muted mb-0">Set delivery rates and geofence coverage for each location.</p></div>
    <a href="{{ route('courier.delivery-areas.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Location</a>
</div>

<form method="GET" action="{{ route('courier.delivery-areas.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Location name"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="">All</option><option value="1" @selected(request('is_active') === '1')>Active</option><option value="0" @selected(request('is_active') === '0')>Inactive</option></select></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('courier.delivery-areas.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Location</th><th>Rate</th><th>Schedule Days</th><th>Geofence</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($areas as $area)
                <tr>
                    <td><strong>{{ $area->name }}</strong></td>
                    <td>{{ ucfirst($area->pricing_mode ?? 'fixed') }}: JMD {{ number_format($area->base_cost, 2) }}<br><small class="text-muted">Per km JMD {{ number_format($area->per_km_cost, 2) }}</small></td>
                    <td>{{ number_format($area->time_slots_count) }} slot(s)</td>
                    <td>
                        {{ ucfirst($area->geofence_type ?? 'none') }}
                        @if($area->geofence_type === 'circle')
                            <br><small class="text-muted">{{ $area->center_lat }}, {{ $area->center_lng }} / {{ $area->radius_km }} km</small>
                        @elseif($area->geofence_type === 'polygon')
                            <br><small class="text-muted">{{ count($area->geofence_coordinates ?? []) }} points</small>
                        @endif
                    </td>
                    <td><span class="badge {{ $area->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $area->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('courier.delivery-areas.edit', $area) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                                <li><a href="{{ route('courier.delivery-areas.edit', $area) }}#schedule-days" class="dropdown-item"><i class="fas fa-calendar-days me-2"></i>Add Schedule Days</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('courier.delivery-areas.destroy', $area) }}" data-confirm="Delete this delivery location?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No delivery locations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $areas->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
