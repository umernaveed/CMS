@extends('layouts.app')

@section('title', 'Authorized Users')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Authorized Users</h1>
    <p class="text-muted mb-0">Manage people who can collect packages on your behalf.</p>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="{{ route('customer.authorized-users.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>Add Authorized User
    </a>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Identification</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($authorizedUsers as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->full_name }}</strong>
                            @if($user->notes)
                                <br><small class="text-muted">{{ Str::limit($user->notes, 50) }}</small>
                            @endif
                        </td>
                        <td>{{ $user->phone ?: '-' }}</td>
                        <td>
                            @if($user->identification_type && $user->identification_number)
                                {{ ucfirst($user->identification_type) }}: {{ $user->identification_number }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('customer.authorized-users.edit', $user) }}" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('customer.authorized-users.destroy', $user) }}"
                                      onsubmit="return confirm('Remove this authorized user?')" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="fas fa-users fa-2x mb-2 d-block"></i>
                            No authorized users yet.
                            <br>
                            <a href="{{ route('customer.authorized-users.create') }}" class="btn btn-primary btn-sm mt-2">
                                Add Your First Authorized User
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $authorizedUsers->links('pagination::bootstrap-5') }}</div>
</div>
@endsection