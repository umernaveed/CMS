@extends('layouts.app')

@section('title', 'Pre-Alerts')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Pre-Alerts</h1>
        <p class="text-muted mb-0">Expected incoming online orders submitted for customs and warehouse preparation.</p>
    </div>
    <a href="{{ route('customer.pre-alerts.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Pre-Alert</a>
</div>

<form method="GET" action="{{ route('customer.pre-alerts.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Pre-alert, tracking, courier, merchant">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(['submitted' => 'Submitted', 'assigned' => 'Assigned', 'converted' => 'Assigned (Archived)', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
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
                @foreach(['created_at' => 'Created', 'pre_alert_number' => 'Pre-Alert', 'supplier_tracking' => 'Supplier Tracking', 'merchant' => 'Merchant', 'status' => 'Status', 'price_usd' => 'Price'] as $value => $label)
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
            <a href="{{ route('customer.pre-alerts.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Pre-Alert</th>
                    <th>Supplier Tracking</th>
                    <th>Courier</th>
                    <th>Merchant</th>
                    <th>Status</th>
                    <th>Receipts</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($preAlerts as $preAlert)
                <tr>
                    <td><strong>{{ $preAlert->pre_alert_number }}</strong><br><small class="text-muted">{{ $preAlert->created_at->format('M d, Y') }}</small></td>
                    <td>{{ $preAlert->supplier_tracking }}</td>
                    <td>{{ $preAlert->courier ?: '-' }}</td>
                    <td>{{ $preAlert->merchant ?: '-' }}<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($preAlert->description, 44) }}</small></td>
                    <td>
                        <span class="badge {{ in_array($preAlert->status, ['assigned', 'converted', 'archived'], true) ? 'bg-success' : 'bg-primary' }}">{{ $preAlert->status === 'converted' ? 'Assigned (Archived)' : ucfirst($preAlert->status) }}</span>
                        @if($preAlert->package)
                            <br><a class="small" href="{{ route('customer.packages.show', $preAlert->package) }}">View package</a>
                        @endif
                    </td>
                    <td>
                        @forelse($preAlert->receipts as $receipt)
                            <a class="btn btn-sm btn-outline-secondary mb-1" href="{{ route('customer.pre-alerts.receipt', $receipt) }}" target="_blank"><i class="fas fa-eye"></i></a>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            @if(! in_array($preAlert->status, ['assigned', 'converted', 'archived'], true) && ! $preAlert->package_id)
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a href="{{ route('customer.pre-alerts.edit', $preAlert) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Modify</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('customer.pre-alerts.destroy', $preAlert) }}" data-confirm="Delete this Pre-Alert?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                </ul>
                            @else
                                <span class="text-muted small">Locked</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No Pre-Alerts submitted yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $preAlerts->links('pagination::bootstrap-5') }}</div>
</div>
@endsection

