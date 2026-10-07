@extends('layouts.app')

@section('title', 'Edit Purchase Request')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Edit Purchase Request</h1>
        <p class="text-muted mb-0">Update request details or add receipts before approval.</p>
    </div>
    <a href="{{ route('customer.purchase-requests.index') }}" class="btn btn-outline-secondary">Back to requests</a>
</div>

@include('customer.purchase-requests._form', ['action' => route('customer.purchase-requests.update', $purchaseRequest), 'method' => 'PUT', 'purchaseRequest' => $purchaseRequest])
@endsection
