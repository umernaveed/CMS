@extends('layouts.app')

@section('title', 'Branch Details')
@section('page_title', 'Branch Details')
@section('breadcrumb', 'Branch / Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $outlet->name }}</h1>
        <p class="text-muted mb-0">{{ $outlet->outlet_code }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.outlets.edit', $outlet) }}" class="btn btn-primary">Edit</a>
        @unless($outlet->is_main)
            <form method="POST" action="{{ route('courier.outlets.destroy', $outlet) }}" onsubmit="return confirm('Delete this branch?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        @endunless
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Branch Profile</h2>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted">Short Name</div>{{ $outlet->short_name ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Email</div>{{ $outlet->email ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Phone</div>{{ $outlet->phone ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Manager</div>{{ $outlet->manager_name ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">City</div>{{ $outlet->city ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Parish</div>{{ $outlet->parish ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Main Branch</div>{{ $outlet->is_main ? 'Yes' : 'No' }}</div>
                <div class="col-md-4"><div class="text-muted">Status</div><span class="badge {{ $outlet->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $outlet->is_active ? 'Active' : 'Inactive' }}</span></div>
                <div class="col-md-4"><div class="text-muted">Customers</div>{{ $outlet->customers_count }}</div>
                <div class="col-md-4"><div class="text-muted">Packages</div>{{ $outlet->packages_count }}</div>
                <div class="col-12"><div class="text-muted">Address</div>{{ $outlet->address ?: '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Assigned Staff</h2>
            @forelse($staff as $user)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-bold">{{ $user->full_name }}</div>
                    <div class="text-muted small">{{ $user->email }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">No staff assigned to this branch.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
