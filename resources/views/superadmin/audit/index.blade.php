@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Audit Trail</h1>
        <p class="text-muted mb-0">Master view of system activity across System Admin, courier, and customer portals.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('superadmin.audit.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract</a>
        <a href="{{ route('superadmin.audit.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('superadmin.audit.download', request()->query()) }}" class="btn btn-primary"><i class="fas fa-download me-1"></i>Download</a>
    </div>
</div>

<form method="GET" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select">
                <option value="">All couriers</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Module</label>
            <select name="module" class="form-select">
                <option value="">All modules</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Action</label>
            <input name="action" class="form-control" value="{{ request('action') }}" placeholder="Action">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
        </div>
        <div class="col-lg-1 col-md-6">
            <button class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
        </div>
        <div class="col-md-10">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Description, IP, user, courier">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <a href="{{ route('superadmin.audit.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="150">When</th>
                    <th>Courier</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                    <th>Route</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                    <td>{{ optional($log->tenant)->company_name ?: '-' }}</td>
                    <td>{{ optional($log->user)->full_name ?: optional($log->user)->email ?: 'System' }}</td>
                    <td>{{ $log->module }}</td>
                    <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td><code>{{ $log->ip_address }}</code></td>
                    <td><small>{{ $log->new_data['route'] ?? '-' }}</small></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No audit records found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    
    @if($logs->isNotEmpty())
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 p-3 border-top">
        <div class="text-muted small">
            Showing 
            <strong>{{ $logs->firstItem() }}</strong> 
            to 
            <strong>{{ $logs->lastItem() }}</strong> 
            of 
            <strong>{{ $logs->total() }}</strong> 
            entries
        </div>
        <div>
            {{ $logs->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
    @endif
</div>
@endsection
