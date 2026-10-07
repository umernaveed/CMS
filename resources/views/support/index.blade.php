@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)
@section('breadcrumb', $title)

@section('content')
@php($supportStatusTabs = ['in_progress' => 'In Progress', 'open' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed'])
@php($activeStatus = request('status', 'in_progress'))
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $title }}</h1>
        <p class="text-muted mb-0">Send messages and track support conversations.</p>
    </div>
    <a href="{{ route($routeBase.'.create') }}" class="btn btn-primary"><i class="fas fa-message me-1"></i>New Chat</a>
</div>

<ul class="nav nav-tabs mb-3">
    @foreach($supportStatusTabs as $statusValue => $statusLabel)
        <li class="nav-item">
            <a class="nav-link {{ $activeStatus === $statusValue ? 'active' : '' }}" href="{{ route($routeBase.'.index', array_merge(request()->except(['status', 'page']), ['status' => $statusValue])) }}">
                {{ $statusLabel }}
            </a>
        </li>
    @endforeach
</ul>

<div class="panel mb-4 p-3">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="status" value="{{ $activeStatus }}">
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
        <div class="col-md-2"><a href="{{ route($routeBase.'.index', ['status' => $activeStatus]) }}" class="btn btn-outline-secondary w-100">Reset</a></div>
    </form>
</div>

<div class="row g-3">
    @forelse($tickets as $ticket)
        @php($last = $ticket->latestMessage)
        <div class="col-lg-6">
            <div class="panel p-3 h-100">
                <a href="{{ route($routeBase.'.show', $ticket) }}" class="d-block text-dark text-decoration-none">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div>
                            <div class="fw-bold">{{ $ticket->subject }}</div>
                            <div class="text-muted small">{{ $ticket->ticket_number }} &middot; {{ optional($ticket->tenant)->company_name ?? 'Platform' }}</div>
                        </div>
                        <span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                    </div>
                    <div class="text-muted small mb-3">
                        {{ ucfirst($ticket->priority) }} priority &middot; {{ optional($ticket->updated_at)->diffForHumans() }}
                    </div>
                    <div class="d-flex gap-2 align-items-start">
                        <span class="avatar-circle" style="width: 34px; height: 34px; font-size: 12px;">{{ strtoupper(substr(optional(optional($last)->user)->full_name ?: optional(optional($last)->user)->email ?: 'S', 0, 2)) }}</span>
                        <div class="flex-grow-1">
                            <div class="small fw-bold">{{ optional(optional($last)->user)->full_name ?: optional(optional($last)->user)->email ?: 'Support' }}</div>
                            <div class="text-muted">{{ \Illuminate\Support\Str::limit(optional($last)->message ?? $ticket->message, 120) }}</div>
                        </div>
                    </div>
                </a>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route($routeBase.'.show', $ticket) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>Open</a>
                    <a href="{{ route($routeBase.'.transcript', $ticket) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Transcript</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="panel p-5 text-center text-muted">No {{ strtolower($supportStatusTabs[$activeStatus] ?? 'matching') }} support conversations.</div>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $tickets->appends(array_merge(request()->query(), ['status' => $activeStatus]))->links('pagination::bootstrap-5') }}</div>
@endsection