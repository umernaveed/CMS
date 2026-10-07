@extends('layouts.app')

@section('title', 'Pre-Alerts')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Pre-Alerts</h1>
        <p class="text-muted mb-0">Expected online orders submitted by customers before warehouse arrival.</p>
    </div>
    <a href="{{ route('courier.packages.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Package</a>
</div>

<form method="GET" action="{{ route('courier.pre-alerts.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Customer, pre-alert, tracking, carrier"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="submitted" @selected(request('status') === 'submitted')>Submitted</option><option value="converted" @selected(request('status') === 'converted')>Assigned (Archived)</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('courier.pre-alerts.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Pre-Alert</th><th>Customer</th><th>Supplier Tracking</th><th>Carrier</th><th>Merchant</th><th>Status</th><th>Receipts</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($preAlerts as $preAlert)
                <tr>
                    <td><strong>{{ $preAlert->pre_alert_number }}</strong><br><small class="text-muted">{{ $preAlert->created_at->format('M d, Y') }}</small></td>
                    <td>{{ optional(optional($preAlert->customer)->user)->full_name ?? 'Customer' }}<br><small class="text-muted">{{ optional($preAlert->customer)->mailbox }}</small></td>
                    <td>{{ $preAlert->supplier_tracking }}</td>
                    <td>{{ $preAlert->courier ?: '-' }}</td>
                    <td>{{ $preAlert->merchant ?: '-' }}</td>
                    <td><span class="badge {{ $preAlert->status === 'converted' ? 'bg-success' : 'bg-primary' }}">{{ $preAlert->status === 'converted' ? 'Assigned (Archived)' : ucfirst($preAlert->status) }}</span></td>
                    <td>
                        @forelse($preAlert->receipts as $receipt)
                            <a class="btn btn-sm btn-outline-secondary mb-1" href="{{ route('courier.pre-alerts.receipt', $receipt) }}" target="_blank"><i class="fas fa-eye"></i></a>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if($preAlert->status === 'converted' && $preAlert->package)
                                    <li><a class="dropdown-item" href="{{ route('courier.packages.show', $preAlert->package) }}"><i class="fas fa-box me-2"></i>Package</a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ route('courier.packages.create', ['pre_alert_id' => $preAlert->id]) }}"><i class="fas fa-box-circle-check me-2"></i>Create Package</a></li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No Pre-Alerts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $preAlerts->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
