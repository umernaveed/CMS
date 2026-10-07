@extends('layouts.app')

@section('title', 'System Admin Employees')
@section('page_title', 'Employees')
@section('breadcrumb', 'Staff / Employees')

@section('content')
<style>
    .staff-workspace {
        display: grid;
        grid-template-columns: minmax(320px, 440px) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .staff-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .staff-form-grid .full { grid-column: 1 / -1; }
    .role-checks {
        display: grid;
        gap: 8px;
        max-height: 220px;
        overflow: auto;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }
    .role-check {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 0;
        padding: 8px;
        border-radius: 6px;
        background: #fff;
        border: 1px solid #eef2f7;
        font-weight: 800;
        color: #334155;
    }
    .role-check span {
        line-height: 1.25;
    }
    .employee-list {
        display: grid;
        gap: 12px;
    }
    .employee-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }
    .employee-summary {
        display: grid;
        grid-template-columns: minmax(180px, 1.15fr) minmax(150px, .75fr) minmax(160px, .8fr) auto;
        gap: 14px;
        align-items: center;
        padding: 14px;
    }
    .employee-name {
        font-weight: 900;
        color: #111827;
    }
    .employee-meta {
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }
    .role-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 7px;
    }
    .role-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 3px 8px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 900;
    }
    .employee-edit {
        border-top: 1px solid #eef2f7;
        padding: 14px;
        background: #f8fafc;
    }
    .employee-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .filter-bar {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 180px auto auto;
        gap: 10px;
        align-items: end;
    }
    @media (max-width: 1180px) {
        .staff-workspace { grid-template-columns: 1fr; }
    }
    @media (max-width: 820px) {
        .employee-summary,
        .filter-bar,
        .staff-form-grid {
            grid-template-columns: 1fr;
        }
        .employee-actions { justify-content: flex-start; }
    }
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">System Admin Employees</h1>
        <p class="text-muted mb-0">Create platform staff accounts and control which superadmin areas they can access.</p>
    </div>
    <a href="{{ route('superadmin.staff.roles-branches') }}" class="btn btn-outline-primary"><i class="fas fa-user-shield me-1"></i>Roles & Permissions</a>
</div>

@if(isset($errors) && $errors->any())
    <div class="alert alert-danger">
        <div class="fw-bold mb-1">Please check the employee form</div>
        {{ $errors->first() }}
    </div>
@endif

<div class="staff-workspace">
    <section class="panel p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">Add Employee</h2>
                <p class="text-muted small mb-0">Assign at least one role so access is limited to the selected superadmin permissions.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Restricted by role</span>
        </div>

        <form method="POST" action="{{ route('superadmin.staff.employees.store') }}">
            @csrf
            <div class="staff-form-grid">
                <div>
                    <label class="form-label">Full Name</label>
                    <input name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="form-label">Temporary Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="full">
                    <label class="form-label">System Admin Roles</label>
                    <div class="role-checks">
                        @forelse($roles as $role)
                            <label class="role-check">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="form-check-input" @checked(in_array($role->id, old('role_ids', [])))>
                                <span>{{ $role->display_name }}<br><small class="text-muted">{{ $role->permissions_count ?? $role->permissions->count() }} permissions</small></span>
                            </label>
                        @empty
                            <div class="text-muted small">Create a role in Roles & Permissions first.</div>
                        @endforelse
                    </div>
                </div>
                <div class="full d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <label class="form-check mb-0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                        Active account
                    </label>
                    <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Employee</button>
                </div>
            </div>
        </form>
    </section>

    <section>
        <form method="GET" class="panel p-3 mb-3">
            <div class="filter-bar">
                <div>
                    <label class="form-label">Search</label>
                    <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, email, or phone">
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="">All employees</option>
                        <option value="1" @selected(request('is_active') === '1')>Active</option>
                        <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                    </select>
                </div>
                <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="{{ route('superadmin.staff.employees') }}" class="btn btn-outline-secondary"><i class="fas fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>

        <div class="employee-list">
            @forelse($employees as $employee)
                @php $employeeRoleIds = $employee->roles->pluck('id')->all(); @endphp
                <article class="employee-card">
                    <div class="employee-summary">
                        <div>
                            <div class="employee-name">{{ $employee->full_name }}</div>
                            <div class="employee-meta">{{ $employee->email }}{{ $employee->phone ? ' · '.$employee->phone : '' }}</div>
                            <div class="role-badges">
                                @forelse($employee->roles as $role)
                                    <span class="role-badge">{{ $role->display_name }}</span>
                                @empty
                                    <span class="badge bg-warning text-dark">Full access: no role assigned</span>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <div class="employee-meta">Last Login</div>
                            <strong>{{ optional($employee->last_login_at)->format('M d, Y h:i A') ?: 'Never' }}</strong>
                        </div>
                        <div>
                            <span class="badge {{ $employee->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="employee-actions">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#employeeEdit{{ $employee->id }}" aria-expanded="false" aria-controls="employeeEdit{{ $employee->id }}">
                                <i class="fas fa-pen me-1"></i>Edit
                            </button>
                            @if($employee->id !== auth()->id())
                                <form method="POST" action="{{ route('superadmin.staff.employees.destroy', $employee) }}" onsubmit="return confirm('Delete this superadmin employee?')" class="m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="collapse" id="employeeEdit{{ $employee->id }}">
                        <form method="POST" action="{{ route('superadmin.staff.employees.update', $employee) }}" class="employee-edit">
                            @csrf
                            @method('PUT')
                            <div class="staff-form-grid">
                                <div>
                                    <label class="form-label">Full Name</label>
                                    <input name="full_name" class="form-control" value="{{ $employee->full_name }}" required>
                                </div>
                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $employee->email }}" required>
                                </div>
                                <div>
                                    <label class="form-label">Phone</label>
                                    <input name="phone" class="form-control" value="{{ $employee->phone }}">
                                </div>
                                <div>
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                </div>
                                <div class="full">
                                    <label class="form-label">Roles</label>
                                    <div class="role-checks">
                                        @foreach($roles as $role)
                                            <label class="role-check">
                                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="form-check-input" @checked(in_array($role->id, $employeeRoleIds, true))>
                                                <span>{{ $role->display_name }}<br><small class="text-muted">{{ $role->permissions_count ?? $role->permissions->count() }} permissions</small></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="full d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <label class="form-check mb-0">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($employee->is_active)>
                                        Active account
                                    </label>
                                    <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </article>
            @empty
                <div class="panel p-4 text-center text-muted">No superadmin employees found.</div>
            @endforelse
        </div>

        <div class="mt-3">{{ $employees->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
