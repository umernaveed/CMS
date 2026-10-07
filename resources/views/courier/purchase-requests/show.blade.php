@extends('layouts.app')

@section('title', 'Purchase Request Details')

@section('content')
<div class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1">{{ $purchaseRequest->purchase_request_number }}</h1>
            <p class="text-muted mb-0">Details for this purchase request.</p>
        </div>
        <a href="{{ route('courier.purchase-requests.index') }}" class="btn btn-outline-secondary">Back to Purchase Requests</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="panel p-3">
            <h2 class="h6 mb-3">Request Summary</h2>
            <div class="mb-2"><strong>Customer</strong><br>{{ $purchaseRequest->customer->company_name ?: $purchaseRequest->customer->user->full_name }}</div>
            <div class="mb-2"><strong>Status</strong><br>
                @php
                    $statusLabels = \App\Models\PurchaseRequest::statuses();
                    $badgeClasses = \App\Models\PurchaseRequest::statusClasses();
                    $badgeClass = $badgeClasses[$purchaseRequest->status] ?? 'bg-primary';
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $statusLabels[$purchaseRequest->status] ?? ucfirst(str_replace('_', ' ', $purchaseRequest->status)) }}</span>
            </div>
            <div class="mb-2"><strong>Product URL</strong><br>
                @if($purchaseRequest->product_url)
                    <a href="{{ $purchaseRequest->product_url }}" target="_blank" rel="noopener noreferrer">{{ $purchaseRequest->product_url }}</a>
                @else
                    -
                @endif
            </div>
            <div class="mb-2"><strong>Delivery Type</strong><br>{{ ucfirst($purchaseRequest->delivery_type) }}</div>
            <div class="mb-2"><strong>Price</strong><br>{{ $purchaseRequest->price_usd !== null ? '$'.number_format($purchaseRequest->price_usd, 2) : '-' }}</div>
            <div class="mb-2"><strong>Created</strong><br>{{ $purchaseRequest->created_at->format('M d, Y h:i A') }}</div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="panel p-3 mb-3">
            <h2 class="h6 mb-3">Description</h2>
            <p>{{ $purchaseRequest->description }}</p>
            @if($purchaseRequest->notes)
                <h2 class="h6 mb-2">Notes</h2>
                <p>{{ $purchaseRequest->notes }}</p>
            @endif
        </div>

        <div class="panel p-3 mb-3">
            <h2 class="h6 mb-3">Manage Status</h2>
            <form method="POST" action="{{ route('courier.purchase-requests.status.update', $purchaseRequest) }}">
                @csrf
                @method('PATCH')
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(\App\Models\PurchaseRequest::statuses() as $value => $label)
                                <option value="{{ $value }}" @selected($purchaseRequest->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Update Status</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="panel p-3">
            <h2 class="h6 mb-3">Receipts</h2>
            @if($purchaseRequest->receipts->isNotEmpty())
                <div class="d-flex flex-wrap gap-2">
                    @foreach($purchaseRequest->receipts as $receipt)
                        <a href="{{ route('courier.purchase-requests.receipt', $receipt) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i>{{ $receipt->original_name }}</a>
                    @endforeach
                </div>
            @else
                <div class="text-muted">No receipts uploaded.</div>
            @endif
        </div>
    </div>
</div>
@endsection
