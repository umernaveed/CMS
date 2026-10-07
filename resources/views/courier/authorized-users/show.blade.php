@extends('layouts.app')

@section('title', 'Authorized User Details')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Authorized User Details</h1>
    <p class="text-muted mb-0">View authorized user information.</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="panel p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Full Name</label>
                    <p class="mb-0">{{ $authorizedUser->full_name }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Phone Number</label>
                    <p class="mb-0">{{ $authorizedUser->phone ?: '-' }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">ID Type</label>
                    <p class="mb-0">{{ $authorizedUser->identification_type ? ucfirst($authorizedUser->identification_type) : '-' }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">ID Number</label>
                    <p class="mb-0">{{ $authorizedUser->identification_number ?: '-' }}</p>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Notes</label>
                    <p class="mb-0">{{ $authorizedUser->notes ?: '-' }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Status</label>
                    <p class="mb-0">
                        <span class="badge {{ $authorizedUser->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $authorizedUser->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Created</label>
                    <p class="mb-0">{{ $authorizedUser->created_at->format('M j, Y g:i A') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panel p-4">
            <h5 class="mb-3">Customer Information</h5>
            <div class="mb-3">
                <label class="form-label fw-bold">Customer Name</label>
                <p class="mb-0">{{ $authorizedUser->customer->company_name ?: $authorizedUser->customer->user->full_name }}</p>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Customer Email</label>
                <p class="mb-0">{{ $authorizedUser->customer->user->email }}</p>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Customer Phone</label>
                <p class="mb-0">{{ $authorizedUser->customer->user->phone ?: '-' }}</p>
            </div>
            <div class="mb-0">
                <label class="form-label fw-bold">Mailbox</label>
                <p class="mb-0">{{ $authorizedUser->customer->mailbox ?: '-' }}</p>
            </div>
        </div>

        <div class="panel p-4">
            <div class="d-grid gap-2">
                <a href="{{ route('courier.authorized-users.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to List
                </a>
                <a href="{{ route('courier.customers.show', $authorizedUser->customer) }}" class="btn btn-outline-primary">
                    <i class="fas fa-user me-1"></i>View Customer
                </a>
            </div>
        </div>
    </div>
</div>
@endsection