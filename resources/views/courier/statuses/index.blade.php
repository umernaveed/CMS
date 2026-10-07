@extends('layouts.app')

@section('title', 'Package Statuses')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Package Statuses</h1><p class="text-muted mb-0">Create the tracking statuses your courier uses for packages.</p></div>
    <a href="{{ route('courier.statuses.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Status</a>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Status</th><th>Color</th><th>Default</th><th>Active</th><th>Packages</th><th>Sort</th><th></th></tr></thead>
            <tbody>
            @forelse($statuses as $status)
                <tr>
                    <td><span class="badge" style="background: {{ $status->color }}">{{ $status->name }}</span><br><small class="text-muted">{{ $status->slug }}</small></td>
                    <td><span style="display:inline-block;width:32px;height:20px;border-radius:6px;background:{{ $status->color }};border:1px solid #e2e8f0"></span> {{ $status->color }}</td>
                    <td>{{ $status->is_default ? 'Yes' : 'No' }}</td>
                    <td>{{ $status->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>{{ $status->packages_count }}</td>
                    <td>{{ $status->sort_order }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                            <li><a href="{{ route('courier.statuses.show', $status) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                            <li><a href="{{ route('courier.statuses.edit', $status) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                            @if($status->packages_count == 0)
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('courier.statuses.destroy', $status) }}" onsubmit="return confirm('Delete this status?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                            @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No statuses yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $statuses->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
