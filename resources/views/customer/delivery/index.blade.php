@extends('layouts.app')

@section('title', 'Delivery Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">{{ $historyMode ? 'Delivery History' : 'Delivery Requests' }}</h1><p class="text-muted mb-0">{{ $historyMode ? 'Completed and cancelled delivery requests.' : 'Request delivery for packages ready for your mailbox.' }}</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ $historyMode ? route('customer.delivery.index') : route('customer.delivery.history') }}" class="btn btn-outline-primary"><i class="fas fa-clock-rotate-left me-1"></i>{{ $historyMode ? 'Active Requests' : 'History' }}</a>
        @unless($historyMode)
            <a href="{{ route('customer.delivery.create') }}" class="btn btn-primary"><i class="fas fa-truck me-1"></i>Request Delivery</a>
        @endunless
    </div>
</div>
<form method="GET" action="{{ $historyMode ? route('customer.delivery.history') : route('customer.delivery.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Request, recipient, phone, address">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(($historyMode ? ['delivered' => 'Delivered', 'cancelled' => 'Cancelled'] : ['pending' => 'Pending', 'approved' => 'Approved', 'assigned' => 'Assigned', 'picked_up' => 'Picked Up', 'in_transit' => 'In Transit']) as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Type</label>
            <select name="delivery_type" class="form-select">
                <option value="">All</option>
                @foreach(['regular' => 'Regular', 'express' => 'Express', 'pickup' => 'Pickup'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('delivery_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Sort</label>
            <select name="sort" class="form-select">
                @foreach(['created_at' => 'Created', 'preferred_date' => 'Preferred', 'request_number' => 'Request', 'recipient_name' => 'Recipient', 'status' => 'Status', 'total_jmd' => 'Total'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'created_at') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Direction</label>
            <select name="direction" class="form-select">
                <option value="desc" @selected(request('direction', 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6 d-flex gap-2">
            <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
            <a href="{{ $historyMode ? route('customer.delivery.history') : route('customer.delivery.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Request</th><th>Packages</th><th>Recipient</th><th>Preferred</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($requests as $request)
                <tr>
                    <td><strong>{{ $request->request_number }}</strong><br><small class="text-muted">{{ ucfirst($request->delivery_type) }}</small></td>
                    <td>{{ $request->package_count }}</td>
                    <td>{{ $request->recipient_name }}<br><small class="text-muted">{{ $request->recipient_phone }}</small></td>
                    <td>{{ optional($request->preferred_date)->format('M d, Y') ?? '-' }}</td>
                    <td><span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $request->status)) }}</span></td>
                    <td>JMD {{ number_format($request->package_total + $request->delivery_cost, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No delivery requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
</div>
@endsection


