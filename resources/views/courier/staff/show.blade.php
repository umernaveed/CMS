@extends('layouts.app')

@section('title', 'Staff Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $staff->full_name }}</h1>
        <p class="text-muted mb-0">{{ $staff->email }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.staff.edit', $staff) }}" class="btn btn-primary">Edit</a>
        @if($staff->id !== auth()->id())
            <form method="POST" action="{{ route('courier.staff.destroy', $staff) }}" onsubmit="return confirm('Delete this staff user?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel p-4">
            <h2 class="h5 mb-3">User Profile</h2>
            <div class="row g-3">
                <div class="col-md-6"><div class="text-muted">Name</div>{{ $staff->full_name }}</div>
                <div class="col-md-6"><div class="text-muted">Email</div>{{ $staff->email }}</div>
                <div class="col-md-6"><div class="text-muted">Phone</div>{{ $staff->phone ?: '-' }}</div>
                <div class="col-md-6"><div class="text-muted">Type</div>{{ ucwords(str_replace('_', ' ', $staff->user_type)) }}</div>
                <div class="col-md-6"><div class="text-muted">Outlet</div>{{ optional($staff->outlet)->name ?: 'All outlets' }}</div>
                <div class="col-md-6"><div class="text-muted">Status</div><span class="badge {{ $staff->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $staff->is_active ? 'Active' : 'Inactive' }}</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Roles</h2>
            @forelse($staff->roles as $role)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-bold">{{ $role->display_name ?? $role->name }}</div>
                    <div class="text-muted small">{{ $role->permissions->count() }} permissions</div>
                </div>
            @empty
                <p class="text-muted mb-0">No roles assigned.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
