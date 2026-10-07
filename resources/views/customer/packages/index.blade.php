@extends('layouts.app')

@section('title', 'My Packages')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">My Packages</h1><p class="text-muted mb-0">Track actual packages logged by the warehouse.</p></div>
    <a href="{{ route('customer.pre-alerts.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Pre-Alert</a>
</div>
<form method="GET" action="{{ route('customer.packages') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tracking, merchant, courier">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status_id" class="form-select">
                <option value="">All</option>
                @foreach($packageStatuses as $status)
                    <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Freight</label>
            <select name="freight_type" class="form-select">
                <option value="">All</option>
                @foreach(['air' => 'Air', 'sea' => 'Sea'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('freight_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Sort</label>
            <select name="sort" class="form-select">
                @foreach(['date_received' => 'Received', 'tracking_number' => 'Tracking', 'merchant' => 'Merchant', 'freight_type' => 'Freight', 'total_jmd' => 'Total'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'date_received') === $value)>{{ $label }}</option>
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
            <a href="{{ route('customer.packages') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Tracking</th><th>Description</th><th>Status</th><th>Freight</th><th>Received</th><th>Total</th><th>Receipt</th><th></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr>
                    <td><strong>{{ $package->tracking_number }}</strong><br><small class="text-muted">{{ $package->supplier_tracking }}</small></td>
                    <td>{{ \Illuminate\Support\Str::limit($package->description ?: $package->merchant, 60) }}</td>
                    <td><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'Processing' }}</span></td>
                    <td>{{ ucfirst($package->freight_type) }}</td>
                    <td>{{ optional($package->date_received)->format('M d, Y') ?? '-' }}</td>
                    <td>{{ $package->customerVisibleCostDisplay() }}</td>
                    <td>@if($package->invoice_file || $package->purchase_receipts_count)<a href="{{ route('customer.packages.receipt', $package) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>@else<span class="text-muted">-</span>@endif</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('customer.packages.show', $package) }}" class="dropdown-item"><i class="fas fa-location-dot me-2"></i>Track</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No packages found yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection



