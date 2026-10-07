@extends('layouts.app')

@section('title', 'Courier Activity')
@section('page_title', 'Courier Activity')
@section('breadcrumb', 'Activity')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Courier Activity</h1>
        <p class="text-muted mb-0">Operational history for your courier only.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.activity.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract CSV</a>
        <a href="{{ route('courier.activity.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
    </div>
</div>

<div class="panel p-3 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Module</label>
            <select name="module" class="form-select">
                <option value="">All modules</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst($module) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">User</label>
            <select name="user_id" class="form-select">
                <option value="">All users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->full_name ?: $user->email }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
        <div class="col-md-1">
            <a href="{{ route('courier.activity.index') }}" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel">
            <div class="p-3 border-bottom">
                <h2 class="h5 mb-0">Activity Log</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Action</th>
                            <th>Module</th>
                            <th>User</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($activities as $activity)
                        <tr>
                            <td>
                                <strong>{{ $activity->action }}</strong>
                                <br>
                                <small class="text-muted">{{ $activity->description }}</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ ucfirst($activity->module) }}</span>
                            </td>
                            <td>{{ optional($activity->user)->full_name ?? optional($activity->user)->email ?? 'System' }}</td>
                            <td>{{ optional($activity->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                No activity records found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            
            {{-- SINGLE PAGINATION - Only show if there are multiple pages --}}
            @if($activities->hasPages())
                <div class="d-flex justify-content-center p-3 border-top">
                    {{ $activities->links('pagination::bootstrap-5') }}
                </div>
            @elseif($activities->isNotEmpty())
                <div class="d-flex justify-content-center p-3 border-top">
                    <div class="text-muted small">Showing all {{ $activities->total() }} entries</div>
                </div>
            @endif
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="panel">
            <div class="p-3 border-bottom">
                <h2 class="h5 mb-0">Package Timeline</h2>
            </div>
            <div class="p-3" style="max-height: 500px; overflow-y: auto;">
                @forelse($packageLogs as $log)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="fw-bold">
                            {{ optional($log->package)->tracking_number ?? 'Package #' . ($log->package_id ?? 'N/A') }}
                        </div>
                        <div class="text-muted small mt-1">
                            {{ $log->note ?? 'Status update' }}
                            @if($log->status)
                                <br><span class="badge bg-info">{{ $log->status->name ?? '' }}</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-clock"></i> {{ optional($log->created_at)->format('M d, Y h:i A') }}
                            <br>
                            <i class="bi bi-person"></i> by {{ optional($log->user)->full_name ?? 'System' }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-box fs-1 d-block mb-2"></i>
                        No package timeline available.
                    </div>
                @endforelse
            </div>
            @if($packageLogs->count() >= 10)
                <div class="p-3 border-top text-center">
                    <small class="text-muted">Showing latest 10 entries</small>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
