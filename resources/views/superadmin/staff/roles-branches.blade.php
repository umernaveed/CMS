@extends('layouts.app')

@section('title', 'Roles & Branches')
@section('page_title', 'Roles & Branches')
@section('breadcrumb', 'Staff / Roles & Branches')

@section('content')
@php
    $canManageSuperadminRoles = auth()->user()->hasPermission('superadmin_staff_roles');
    $canManageCourierBranches = auth()->user()->hasPermission('superadmin_staff_branches');
    $activeTab = request('tab', $canManageSuperadminRoles ? 'roles' : 'branches');
    if ($activeTab === 'roles' && ! $canManageSuperadminRoles) { $activeTab = 'branches'; }
    if ($activeTab === 'branches' && ! $canManageCourierBranches) { $activeTab = 'roles'; }
@endphp
<style>
    .permission-module-toggle { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; }
    .permission-module-state { color: #64748b; font-size: 12px; font-weight: 800; }
    .staff-tab-pane { display: none; }
    .staff-tab-pane.active { display: block; }
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Roles & Permissions</h1>
        <p class="text-muted mb-0">Create System Admin portal roles, assign permissions, and manage courier branches when allowed.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($canManageSuperadminRoles)
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createRolePanel" aria-expanded="false" aria-controls="createRolePanel">
                <i class="fas fa-user-shield me-1"></i>Create System Admin Role
            </button>
        @endif
        @if($canManageCourierBranches)
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createBranchPanel" aria-expanded="false" aria-controls="createBranchPanel">
                <i class="fas fa-code-branch me-1"></i>Create Branch
            </button>
        @endif
        <a href="{{ route('superadmin.staff.employees') }}" class="btn btn-outline-primary"><i class="fas fa-users me-1"></i>Employees</a>
    </div>
</div>

<div class="collapse mb-4" id="createRolePanel">
    <div class="panel p-4">
        <h2 class="h5 mb-3">Create System Admin Role</h2>
        <form method="POST" action="{{ route('superadmin.staff.roles.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4"><label class="form-label">Role Name</label><input name="display_name" class="form-control" required></div>
            <div class="col-md-8"><label class="form-label">Description</label><input name="description" class="form-control"></div>
            <div class="col-12">
                <label class="form-label">Permissions</label>
                <div class="row g-3">
                    @foreach($permissions as $module => $items)
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100" data-permission-module-card>
                                <label class="permission-module-toggle mb-2">
                                    <input class="form-check-input" type="checkbox" data-module-check>
                                    <span>{{ $module }}</span>
                                </label>
                                <span class="permission-module-state ms-2" data-module-state>Restricted</span>
                                <div class="row g-2 mt-1">
                                    @foreach($items as $permission)
                                        <div class="col-md-6">
                                            <label class="form-check small">
                                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" class="form-check-input">
                                                <span class="form-check-label">{{ $permission->display_name }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-12 text-end"><button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Role</button></div>
        </form>
    </div>
</div>

<div class="collapse mb-4" id="createBranchPanel">
    <div class="panel p-4">
        <h2 class="h5 mb-3">Create Branch</h2>
        <form method="POST" action="{{ route('superadmin.staff.branches.store') }}" class="row g-3">
            @csrf
            <div class="col-lg-3 col-md-6"><label class="form-label">Courier</label><select name="tenant_id" class="form-select" required><option value="">Select courier</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->company_name }}</option>@endforeach</select></div>
            <div class="col-lg-2 col-md-3"><label class="form-label">Branch Code</label><input name="outlet_code" class="form-control" required></div>
            <div class="col-lg-3 col-md-5"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">City</label><input name="city" class="form-control"></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Parish</label><input name="parish" class="form-control"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Manager</label><input name="manager_name" class="form-control"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Rate Group Mode</label><select name="rate_group_mode" class="form-select"><option value="custom">Custom</option><option value="main">Main</option></select></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-check"><input type="checkbox" name="is_main" value="1" class="form-check-input"> Main branch</label></div>
            <div class="col-md-6"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked> Active</label></div>
            <div class="col-12 text-end"><button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Branch</button></div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    @if($canManageSuperadminRoles)
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'roles' ? 'active' : '' }}" href="{{ route('superadmin.staff.roles-branches', array_merge(request()->except(['tab', 'branches_page']), ['tab' => 'roles'])) }}">
                <i class="fas fa-user-shield me-1"></i>System Admin Roles
            </a>
        </li>
    @endif
    @if($canManageCourierBranches)
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'branches' ? 'active' : '' }}" href="{{ route('superadmin.staff.roles-branches', array_merge(request()->except(['tab', 'roles_page']), ['tab' => 'branches'])) }}">
                <i class="fas fa-code-branch me-1"></i>Branches
            </a>
        </li>
    @endif
</ul>

<form method="GET" class="panel p-3 mb-3">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    <div class="row g-2 align-items-end">
        @if($activeTab === 'branches')
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Courier</label>
                <select name="tenant_id" class="form-select">
                    <option value="">All couriers</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Branch Search</label><input name="branch_q" value="{{ request('branch_q') }}" class="form-control" placeholder="Name, code, city"></div>
        @else
            <div class="col-lg-5 col-md-8"><label class="form-label">Role Search</label><input name="role_q" value="{{ request('role_q') }}" class="form-control" placeholder="Role name or key"></div>
        @endif
        <div class="col-lg-2 col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
        <div class="col-lg-2 col-md-2"><a href="{{ route('superadmin.staff.roles-branches', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary w-100">Reset</a></div>
    </div>
</form>

@if($canManageSuperadminRoles)
<div class="staff-tab-pane {{ $activeTab === 'roles' ? 'active' : '' }}">
    <div class="panel">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-3">
            <h2 class="h5 mb-0">System Admin Roles</h2>
            <span class="text-muted small">{{ number_format($roles->total()) }} roles</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Role</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
                <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td><strong>{{ $role->display_name }}</strong><br><small class="text-muted">{{ $role->name }}</small></td>
                        <td>{{ $role->permissions->count() }}</td>
                        <td>{{ $role->users_count }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#roleEdit{{ $role->id }}"><i class="fas fa-pen"></i></button>
                                @if($role->users_count === 0)
                                    <form method="POST" action="{{ route('superadmin.staff.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="collapse" id="roleEdit{{ $role->id }}">
                        <td colspan="4">
                            <form method="POST" action="{{ route('superadmin.staff.roles.update', $role) }}" class="row g-3 p-3 bg-light rounded">
                                @csrf @method('PUT')
                                <div class="col-md-4"><label class="form-label">Role Name</label><input name="display_name" class="form-control" value="{{ $role->display_name }}" required></div>
                                <div class="col-md-8"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $role->description }}"></div>
                                <div class="col-12">
                                    <label class="form-label">Permissions</label>
                                    <div class="row g-3">
                                        @php($rolePermissionIds = $role->permissions->pluck('id')->all())
                                        @foreach($permissions as $module => $items)
                                            <div class="col-lg-6">
                                                <div class="border rounded p-3 h-100" data-permission-module-card>
                                                    <label class="permission-module-toggle mb-2">
                                                        <input class="form-check-input" type="checkbox" data-module-check>
                                                        <span>{{ $module }}</span>
                                                    </label>
                                                    <span class="permission-module-state ms-2" data-module-state>Restricted</span>
                                                    <div class="row g-2 mt-1">
                                                        @foreach($items as $permission)
                                                            <div class="col-md-6">
                                                                <label class="form-check small">
                                                                    <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" class="form-check-input" @checked(in_array($permission->id, $rolePermissionIds, true))>
                                                                    <span class="form-check-label">{{ $permission->display_name }}</span>
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12 text-end"><button class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Save Role</button></div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No roles found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $roles->appends(array_merge(request()->query(), ['tab' => 'roles']))->links('pagination::bootstrap-5') }}</div>
    </div>
</div>
@endif

@if($canManageCourierBranches)
<div class="staff-tab-pane {{ $activeTab === 'branches' ? 'active' : '' }}">
    <div class="panel">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-3">
            <h2 class="h5 mb-0">Branches</h2>
            <span class="text-muted small">{{ number_format($branches->total()) }} branches</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Branch</th><th>Courier</th><th>Usage</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($branches as $branch)
                    <tr>
                        <td><strong>{{ $branch->name }}</strong><br><small class="text-muted">{{ $branch->outlet_code }} &middot; {{ $branch->city ?: '-' }}</small></td>
                        <td>{{ optional($branch->tenant)->company_name }}</td>
                        <td><small>{{ $branch->users_count }} staff &middot; {{ $branch->customers_count }} customers &middot; {{ $branch->packages_count }} packages</small></td>
                        <td><span class="badge {{ $branch->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span>@if($branch->is_main)<span class="badge bg-primary ms-1">Main</span>@endif</td>
                        <td class="text-end">
                            @unless($branch->is_main)
                                <form method="POST" action="{{ route('superadmin.staff.branches.destroy', $branch) }}" onsubmit="return confirm('Delete this branch?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No branches found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $branches->appends(array_merge(request()->query(), ['tab' => 'branches']))->links('pagination::bootstrap-5') }}</div>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = Array.from(document.querySelectorAll('[data-permission-module-card]'));

    function updateModuleState(card) {
        const checkboxes = Array.from(card.querySelectorAll('input[name="permission_ids[]"]'));
        const moduleCheck = card.querySelector('[data-module-check]');
        const state = card.querySelector('[data-module-state]');
        const checked = checkboxes.filter(checkbox => checkbox.checked).length;

        if (moduleCheck) {
            moduleCheck.checked = checkboxes.length > 0 && checked === checkboxes.length;
            moduleCheck.indeterminate = checked > 0 && checked < checkboxes.length;
        }

        if (state) {
            state.textContent = checked === 0 ? 'Restricted' : (checked === checkboxes.length ? 'Checked' : checked + ' of ' + checkboxes.length);
        }
    }

    cards.forEach(function (card) {
        const checkboxes = Array.from(card.querySelectorAll('input[name="permission_ids[]"]'));
        const moduleCheck = card.querySelector('[data-module-check]');

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () { updateModuleState(card); });
        });

        if (moduleCheck) {
            moduleCheck.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) { checkbox.checked = moduleCheck.checked; });
                updateModuleState(card);
            });
        }

        updateModuleState(card);
    });
});
</script>
@endsection