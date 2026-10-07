@extends('layouts.app')

@section('title', 'Support Ticket')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $ticket->ticket_number }}</h1>
        <p class="text-muted mb-0">{{ $ticket->subject }}</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('superadmin.support.destroy', $ticket) }}" onsubmit="return confirm('Delete this support ticket?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Message</h2>
            <div style="white-space: pre-line">{{ $ticket->message }}</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Ticket Details</h2>
            <div class="mb-2"><div class="text-muted">Courier</div>{{ optional($ticket->tenant)->company_name ?? 'Platform' }}</div>
            <div class="mb-2"><div class="text-muted">Requester</div>{{ optional($ticket->user)->full_name ?? optional($ticket->user)->email ?? '-' }}</div>
            <div class="mb-2"><div class="text-muted">Priority</div>{{ ucfirst($ticket->priority) }}</div>
            <div class="mb-2"><div class="text-muted">Status</div><span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></div>
            <div class="mb-2"><div class="text-muted">Assigned</div>{{ optional($ticket->assignee)->full_name ?? optional($ticket->assignee)->email ?? '-' }}</div>
            <div class="mb-2"><div class="text-muted">Created</div>{{ optional($ticket->created_at)->format('M d, Y h:i A') }}</div>
            <div><div class="text-muted">Closed</div>{{ optional($ticket->closed_at)->format('M d, Y h:i A') ?? '-' }}</div>
        </div>
    </div>
</div>
@endsection
