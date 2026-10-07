@extends('layouts.app')

@section('title', 'Staff Users')
@section('page_title', 'Manage Users')
@section('breadcrumb', 'Staff Users')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Staff Users</h1>
        <p class="text-muted mb-0">Manage courier admins, staff accounts, assigned outlets, and roles.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.customers.index') }}" class="btn btn-outline-secondary">Customers</a>
        <a href="{{ route('courier.roles.index') }}" class="btn btn-outline-primary">Roles</a>
        <a href="{{ route('courier.staff.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Staff</a>
    </div>
</div>

<form method="GET" action="{{ route('courier.staff.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, email, phone"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Branch</label><select name="outlet_id" class="form-select"><option value="">All</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected(request('outlet_id') == $outlet->id)>{{ $outlet->name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Role</label><select name="role_id" class="form-select"><option value="">All</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->display_name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Type</label><select name="user_type" class="form-select"><option value="">All</option><option value="staff" @selected(request('user_type')==='staff')>Staff</option><option value="tenant_admin" @selected(request('user_type')==='tenant_admin')>Courier Admin</option></select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="">All</option><option value="1" @selected(request('is_active')==='1')>Active</option><option value="0" @selected(request('is_active')==='0')>Inactive</option></select></div>
        <div class="col-lg-1 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter"></i></button><a href="{{ route('courier.staff.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>User</th><th>Type</th><th>Outlet</th><th>Roles</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->full_name }}</strong><br><small class="text-muted">{{ $user->email }}</small></td>
                    <td>{{ ucwords(str_replace('_', ' ', $user->user_type)) }}</td>
                    <td>{{ optional($user->outlet)->name ?? 'All outlets' }}</td>
                    <td>
                        @forelse($user->roles as $role)
                            <span class="badge badge-soft">{{ $role->display_name ?? $role->name }}</span>
                        @empty
                            <span class="text-muted">No roles</span>
                        @endforelse
                    </td>
                    <td><span class="badge {{ $user->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                            <li><a href="{{ route('courier.staff.show', $user) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                            <li><a href="{{ route('courier.staff.edit', $user) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                            @if($user->id !== auth()->id())
                                <li><hr class="dropdown-divider"></li>
                                @if($user->is_active)
                                    <li><form method="POST" action="{{ route('courier.staff.deactivate', $user) }}" data-confirm="Deactivate this employee?">@csrf @method('PATCH')<button class="dropdown-item text-warning"><i class="fas fa-user-slash me-2"></i>Deactivate</button></form></li>
                                @endif
                                <li><form method="POST" action="{{ route('courier.staff.destroy', $user) }}" onsubmit="return confirm('Delete this staff user?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                            @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No staff users yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $users->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
