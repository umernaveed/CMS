@extends('layouts.app')

@section('title', 'Branch')
@section('page_title', 'Branch')
@section('breadcrumb', 'Branch')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Branch</h1><p class="text-muted mb-0">Branches and branch staff for this courier.</p></div>
    <a href="{{ route('courier.outlets.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Branch</a>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Branch</th><th>Contact</th><th>Location</th><th>Rate Group</th><th>Customers</th><th>Packages</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($outlets as $outlet)
                <tr>
                    <td><strong>{{ $outlet->name }}</strong> @if($outlet->is_main)<span class="badge badge-soft ms-1">Main</span>@endif<br><small class="text-muted">{{ $outlet->outlet_code }}</small></td>
                    <td>{{ $outlet->email ?: '-' }}<br><small class="text-muted">{{ $outlet->phone }}</small></td>
                    <td>{{ $outlet->city ?: '-' }}{{ $outlet->parish ? ', '.$outlet->parish : '' }}</td>
                    <td>{{ ($outlet->rate_group_mode ?? 'main') === 'custom' ? 'Own branch rates' : 'Main branch rates' }}</td>
                    <td>{{ $outlet->customers_count }}</td>
                    <td>{{ $outlet->packages_count }}</td>
                    <td><span class="badge {{ $outlet->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $outlet->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                            <li><a href="{{ route('courier.outlets.show', $outlet) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                            <li><a href="{{ route('courier.outlets.edit', $outlet) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                            @unless($outlet->is_main)
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('courier.outlets.destroy', $outlet) }}" onsubmit="return confirm('Delete this branch?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                            @endunless
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No branches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $outlets->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
