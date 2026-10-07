@extends('layouts.app')

@section('title', 'Delivery Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">{{ $historyMode ? 'Delivery History' : 'Delivery Requests' }}</h1><p class="text-muted mb-0">{{ $historyMode ? 'Review completed and cancelled delivery requests.' : 'Approve, assign, and track customer delivery requests.' }}</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ $historyMode ? route('courier.delivery.index') : route('courier.delivery.history') }}" class="btn btn-outline-primary"><i class="fas fa-clock-rotate-left me-1"></i>{{ $historyMode ? 'Active Requests' : 'Delivery History' }}</a>
        <a href="{{ route('courier.delivery.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.delivery.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.delivery.download', request()->query()) }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Download</a>
        @if(auth()->user()->user_type === 'tenant_admin')
            <a href="{{ route('courier.delivery-areas.index') }}" class="btn btn-primary"><i class="fas fa-map-location-dot me-1"></i>Delivery Locations</a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('courier.delivery.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Request, mailbox, recipient"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['pending','approved','assigned','picked_up','in_transit','delivered','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Location</label><select name="area_id" class="form-select"><option value="">All</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(request('area_id') == $area->id)>{{ $area->name }}</option>@endforeach</select></div>
        <div class="col-lg-1 col-md-6"><label class="form-label">Type</label><select name="delivery_type" class="form-select"><option value="">All</option>@foreach(['regular','express','pickup'] as $type)<option value="{{ $type }}" @selected(request('delivery_type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Assigned To</label><select name="assigned_to" class="form-select"><option value="">All</option>@foreach($deliveryStaff as $staff)<option value="{{ $staff->id }}" @selected(request('assigned_to') == $staff->id)>{{ $staff->full_name ?: $staff->email }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('courier.delivery.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Request</th><th>Customer</th><th>Area</th><th>Packages</th><th>Preferred</th><th>Assigned</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($requests as $request)
                <tr>
                    <td><strong>{{ $request->request_number }}</strong><br><small class="text-muted">{{ ucfirst($request->delivery_type) }}</small></td>
                    <td>{{ optional(optional($request->customer)->user)->full_name ?? '-' }}<br><small class="text-muted">{{ $request->mailbox }}</small></td>
                    <td>{{ optional($request->area)->name ?? '-' }}<br><small class="text-muted">JMD {{ number_format($request->delivery_cost, 2) }}</small></td>
                    <td>{{ $request->package_count }} package(s)<br><small class="text-muted">JMD {{ number_format($request->package_total, 2) }}</small></td>
                    <td>{{ optional($request->preferred_date)->format('M d, Y') ?? '-' }}</td>
                    <td>{{ optional($request->assignee)->full_name ?: optional($request->assignee)->email ?: 'Unassigned' }}</td>
                    <td><span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $request->status)) }}</span></td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('courier.delivery.status', $request) }}" class="d-inline-flex flex-wrap justify-content-end gap-2">
                            @csrf @method('PATCH')
                            <select name="status" class="form-select form-select-sm">
                                @foreach(['pending','approved','assigned','picked_up','in_transit','delivered','cancelled'] as $status)
                                    <option value="{{ $status }}" @selected($request->status===$status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="">Unassigned</option>
                                @foreach($deliveryStaff as $staff)
                                    <option value="{{ $staff->id }}" @selected($request->assigned_to == $staff->id)>{{ $staff->full_name ?: $staff->email }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Update</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No delivery requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
