@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Purchase Requests</h1>
        <p class="text-muted mb-0">Submit and manage purchase requests for expected orders.</p>
    </div>
    <a href="{{ route('customer.purchase-requests.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Purchase Request</a>
</div>

<form method="GET" action="{{ route('customer.purchase-requests.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Request ID, product URL, description">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(\App\Models\PurchaseRequest::statuses() as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Delivery</label>
            <select name="delivery_type" class="form-select">
                <option value="">All</option>
                @foreach(['air' => 'Air', 'sea' => 'Sea'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('delivery_type') === $value)>{{ $label }}</option>
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
                @foreach(['created_at' => 'Created', 'purchase_request_number' => 'Request', 'delivery_type' => 'Delivery', 'status' => 'Status', 'price_usd' => 'Price'] as $value => $label)
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
            <a href="{{ route('customer.purchase-requests.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Product</th>
                    <th>Delivery</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Files</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($purchaseRequests as $request)
                <tr>
                    <td><strong>{{ $request->purchase_request_number }}</strong><br><small class="text-muted">{{ $request->created_at->format('M d, Y') }}</small></td>
                    <td>
                        @if($request->product_url)
                            <a href="{{ $request->product_url }}" target="_blank" rel="noopener noreferrer">Product link</a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($request->description, 44) }}</small>
                    </td>
                    <td>{{ ucfirst($request->delivery_type) }}</td>
                    <td>{{ $request->price_usd !== null ? '$'.number_format((float) $request->price_usd, 2) : '-' }}</td>
                    <td>
                        @php
                            $badgeClasses = \App\Models\PurchaseRequest::statusClasses();
                            $statusLabels = \App\Models\PurchaseRequest::statuses();
                            $badgeClass = $badgeClasses[$request->status] ?? 'bg-primary';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $statusLabels[$request->status] ?? ucfirst(str_replace('_', ' ', $request->status)) }}</span>
                    </td>
                    <td>
                        @forelse($request->receipts as $receipt)
                            <a class="btn btn-sm btn-outline-secondary mb-1" href="{{ route('customer.purchase-requests.receipt', $receipt) }}"><i class="fas fa-download"></i></a>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            @if($request->status === 'submitted')
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a href="{{ route('customer.purchase-requests.edit', $request) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Modify</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('customer.purchase-requests.destroy', $request) }}" onsubmit="return confirm('Delete this purchase request?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                </ul>
                            @else
                                <span class="text-muted small">Locked</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No purchase requests submitted yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $purchaseRequests->links('pagination::bootstrap-5') }}</div>
</div>
@endsection

