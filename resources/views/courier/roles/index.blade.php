@extends('layouts.app')

@section('title', 'Roles')
@section('page_title', 'Roles')
@section('breadcrumb', 'Roles')

@section('content')
<style>
    .roles-ui-wrap { max-width: 1180px; }
    .roles-ui-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .roles-ui-title { font-size: 20px; font-weight: 800; color: #111827; margin: 0; }
    .roles-ui-subtitle { color: #6b7280; font-size: 13px; margin: 3px 0 0; }
    .roles-ui-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .roles-ui-toolbar { display: flex; gap: 10px; align-items: center; padding: 12px; border-bottom: 1px solid #eef2f7; }
    .roles-ui-search { max-width: 360px; height: 34px; font-size: 13px; }
    .roles-ui-filter { height: 34px; min-width: 86px; font-size: 13px; }
    .roles-ui-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .roles-ui-table th { background: #f9fafb; color: #6b7280; font-size: 11px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; padding: 11px 14px; border-bottom: 1px solid #e5e7eb; }
    .roles-ui-table td { padding: 12px 14px; border-bottom: 1px solid #eef2f7; color: #374151; vertical-align: middle; }
    .roles-ui-table tr:last-child td { border-bottom: 0; }
    .roles-ui-role { font-weight: 800; color: #111827; }
    .roles-ui-muted { color: #6b7280; font-size: 12px; }
    .roles-ui-status { display: inline-flex; align-items: center; min-width: 58px; justify-content: center; border-radius: 999px; padding: 3px 9px; font-size: 11px; font-weight: 800; background: #dcfce7; color: #15803d; }
    .roles-ui-actions { display: flex; justify-content: flex-end; gap: 6px; }
    .roles-ui-actions .btn { border-radius: 4px; font-size: 12px; padding: 4px 9px; }
    .roles-ui-pagination { padding: 12px; border-top: 1px solid #eef2f7; }
    @media (max-width: 767px) {
        .roles-ui-head, .roles-ui-toolbar { flex-direction: column; align-items: stretch; }
        .roles-ui-search { max-width: none; }
        .roles-ui-actions { justify-content: flex-start; }
    }
</style>

<div class="roles-ui-wrap">
    <div class="roles-ui-head">
        <div>
            <h1 class="roles-ui-title">Roles</h1>
            <p class="roles-ui-subtitle">List all roles and assign modules by role, branch, and staff user.</p>
        </div>
        <a href="{{ route('courier.roles.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Role</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="roles-ui-card">
        <form method="GET" action="{{ route('courier.roles.index') }}" class="roles-ui-toolbar">
            <input type="search" name="q" class="form-control roles-ui-search" value="{{ request('q') }}" placeholder="Search">
            <button class="btn btn-outline-secondary btn-sm roles-ui-filter" type="submit">Filter</button>
            @if(request()->filled('q'))
                <a class="btn btn-outline-secondary btn-sm roles-ui-filter" href="{{ route('courier.roles.index') }}">Reset</a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="roles-ui-table">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th class="text-center">#Assigned</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td>
                            <div class="roles-ui-role">{{ $role->display_name ?? $role->name }}</div>
                            <div class="roles-ui-muted">{{ $role->is_system ? 'System role' : 'Custom role' }}</div>
                        </td>
                        <td>{{ $role->description ?: '-' }}</td>
                        <td class="text-center">{{ number_format($role->users_count) }}</td>
                        <td>{{ optional($role->created_at)->format('M d, Y') ?: '-' }}</td>
                        <td><span class="roles-ui-status" style="{{ ($role->is_active ?? true) ? '' : 'background:#fee2e2;color:#991b1b;' }}">{{ ($role->is_active ?? true) ? 'Active' : 'Disabled' }}</span></td>
                        <td>
                            <div class="roles-ui-actions">
                                <a href="{{ route('courier.roles.show', $role) }}" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i>View</a>
                                @if(! $role->is_system)
                                    <a href="{{ route('courier.roles.edit', $role) }}" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1"></i>Edit</a>
                                    <form method="POST" action="{{ route('courier.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')" class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No roles found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="roles-ui-pagination">{{ $roles->links('pagination::bootstrap-5') }}</div>
    </div>
</div>
@endsection

