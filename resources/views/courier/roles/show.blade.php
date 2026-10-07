@extends('layouts.app')

@section('title', 'Role Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $role->display_name ?? $role->name }}</h1>
        <p class="text-muted mb-0">{{ $role->description ?: 'Role permissions and usage.' }}</p>
    </div>
    @unless($role->is_system)
        <div class="d-flex gap-2">
            <a href="{{ route('courier.roles.edit', $role) }}" class="btn btn-primary">Edit</a>
            <form method="POST" action="{{ route('courier.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        </div>
    @endunless
</div>

<div class="panel p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted">Type</div>{{ $role->is_system ? 'System' : 'Custom' }}</div>
        <div class="col-md-3"><div class="text-muted">Users</div>{{ $role->users_count }}</div>
        <div class="col-md-3"><div class="text-muted">Permissions</div>{{ $role->permissions->count() }}</div>
        <div class="col-md-3"><div class="text-muted">Key</div>{{ $role->name }}</div>
    </div>
</div>

<div class="panel p-4">
    <h2 class="h5 mb-3">Permissions</h2>
    @forelse($permissions as $module => $items)
        <div class="mb-3">
            <div class="fw-bold text-capitalize mb-2">{{ str_replace('_', ' ', $module) }}</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($items as $permission)
                    <span class="badge badge-soft">{{ $permission->display_name }}</span>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-muted mb-0">No permissions assigned.</p>
    @endforelse
</div>
@endsection
