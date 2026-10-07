@extends('layouts.app')

@section('title', 'Status Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $status->name }}</h1>
        <p class="text-muted mb-0">{{ $status->slug }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.statuses.edit', $status) }}" class="btn btn-primary">Edit</a>
        @if($status->packages_count == 0)
            <form method="POST" action="{{ route('courier.statuses.destroy', $status) }}" onsubmit="return confirm('Delete this status?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        @endif
    </div>
</div>

<div class="panel p-4">
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted">Preview</div><span class="badge" style="background: {{ $status->color }}">{{ $status->name }}</span></div>
        <div class="col-md-3"><div class="text-muted">Color</div>{{ $status->color }}</div>
        <div class="col-md-3"><div class="text-muted">Icon</div>{{ $status->icon ?: '-' }}</div>
        <div class="col-md-3"><div class="text-muted">Sort Order</div>{{ $status->sort_order }}</div>
        <div class="col-md-3"><div class="text-muted">Default</div>{{ $status->is_default ? 'Yes' : 'No' }}</div>
        <div class="col-md-3"><div class="text-muted">Active</div>{{ $status->is_active ? 'Active' : 'Inactive' }}</div>
        <div class="col-md-3"><div class="text-muted">Packages Using It</div>{{ $status->packages_count }}</div>
    </div>
</div>
@endsection
