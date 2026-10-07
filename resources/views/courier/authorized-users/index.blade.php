@extends('layouts.app')

@section('title', 'Authorized Users')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Authorized Users</h1>
    <p class="text-muted mb-0">Manage authorized users for all customers.</p>
</div>

<!-- Filters -->
<div class="panel p-3 mb-3">
    <form method="GET" action="{{ route('courier.authorized-users.index') }}" class="row g-3">
        <div class="col-md-4">
            <label for="customer_id" class="form-label">Customer</label>
            <select class="form-select" id="customer_id" name="customer_id">
                <option value="">All Customers</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>
                        {{ $customer->company_name ?: $customer->user->full_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Status</option>
                <option value="active" @selected(request('status') == 'active')>Active</option>
                <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="search" class="form-label">Search</label>
            <input type="text" class="form-control" id="search" name="search" 
                   value="{{ request('search') }}" placeholder="Name, phone, or ID number">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Customer</th>
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
                            <strong>{{ $user->customer->company_name ?: $user->customer->user->full_name }}</strong>
                        </td>
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
                            <a href="{{ route('courier.authorized-users.show', $user) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-users fa-2x mb-2 d-block"></i>
                            No authorized users found.
                            @if(request()->hasAny(['customer_id', 'status', 'search']))
                                <br>
                                <a href="{{ route('courier.authorized-users.index') }}" class="btn btn-primary btn-sm mt-2">
                                    Clear Filters
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $authorizedUsers->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
</div>
@endsection