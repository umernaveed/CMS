@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Purchase Requests</h1>
    <p class="text-muted mb-0">View all customer purchase requests for your courier.</p>
</div>

<div class="panel p-3 mb-3">
    <form method="GET" action="{{ route('courier.purchase-requests.index') }}" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All Customers</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>
                        {{ $customer->company_name ?: $customer->user->full_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All Status</option>
                @foreach(\App\Models\PurchaseRequest::statuses() as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Request number, product URL, description">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Customer</th>
                    <th>Delivery</th>
                    <th>Product</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Files</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseRequests as $request)
                    <tr>
                        <td>
                            <strong>{{ $request->purchase_request_number }}</strong>
                            <br><small class="text-muted">{{ $request->created_at->format('M d, Y') }}</small>
                        </td>
                        <td>
                            <strong>{{ $request->customer->company_name ?: $request->customer->user->full_name }}</strong>
                        </td>
                        <td>{{ ucfirst($request->delivery_type) }}</td>
                        <td>
                            @if($request->product_url)
                                <a href="{{ $request->product_url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Product link</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($request->description, 48) }}</td>
                        <td>{{ $request->price_usd !== null ? '$'.number_format($request->price_usd, 2) : '-' }}</td>
                        <td>
                            @php
                                $badgeClasses = \App\Models\PurchaseRequest::statusClasses();
                                $statusLabels = \App\Models\PurchaseRequest::statuses();
                                $badgeClass = $badgeClasses[$request->status] ?? 'bg-primary';
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $statusLabels[$request->status] ?? ucfirst(str_replace('_', ' ', $request->status)) }}
                            </span>
                        </td>
                        <td>
                            @if($request->receipts->count())
                                <span class="badge bg-secondary">{{ $request->receipts->count() }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a href="{{ route('courier.purchase-requests.show', $request) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach(\App\Models\PurchaseRequest::statuses() as $value => $label)
                                        @if($request->status !== $value)
                                            <li>
                                                <form method="POST" action="{{ route('courier.purchase-requests.status.update', $request) }}" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $value }}">
                                                    <button type="submit" class="dropdown-item">{{ $label }}</button>
                                                </form>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No purchase requests found.
                            @if(request()->hasAny(['customer_id', 'status', 'q']))
                                <div class="mt-2"><a href="{{ route('courier.purchase-requests.index') }}" class="btn btn-outline-primary btn-sm">Clear Filters</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $purchaseRequests->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
