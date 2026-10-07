@extends('layouts.app')

@section('title', 'Support Tickets')
@section('page_title', 'Support Tickets')
@section('breadcrumb', 'Support Tickets')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Support Tickets</h1><p class="text-muted mb-0">Track courier and customer support requests.</p></div>
    <a href="{{ route('superadmin.support.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Ticket</a>
</div>

@php($supportStatusTabs = ['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'])
<ul class="nav nav-tabs mb-3">
    @foreach($supportStatusTabs as $statusValue => $statusLabel)
        <li class="nav-item">
            <a class="nav-link {{ request('status', '') === $statusValue ? 'active' : '' }}" href="{{ route('superadmin.support.index', array_merge(request()->except(['status', 'page']), ['status' => $statusValue])) }}">
                {{ $statusLabel }}
            </a>
        </li>
    @endforeach
</ul>

<div class="panel mb-4 p-3">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="status" value="{{ request('status') }}">

        <div class="col-md-3">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <option value="">All priorities</option>
                @foreach(['low', 'medium', 'high', 'urgent'] as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('superadmin.support.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Ticket</th><th>Courier</th><th>Requester</th><th>Priority</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td><strong>{{ $ticket->ticket_number }}</strong><br><small class="text-muted">{{ $ticket->subject }}</small></td>
                    <td>{{ optional($ticket->tenant)->company_name ?? 'Platform' }}</td>
                    <td>{{ optional($ticket->user)->full_name ?? optional($ticket->user)->email ?? '-' }}</td>
                    <td>{{ ucfirst($ticket->priority) }}</td>
                    <td><span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td>
                    <td>{{ optional($ticket->assignee)->full_name ?? optional($ticket->assignee)->email ?? '-' }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('superadmin.support.show', $ticket) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('superadmin.support.destroy', $ticket) }}" onsubmit="return confirm('Delete this support ticket?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No support tickets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $tickets->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
