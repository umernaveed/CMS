@extends('layouts.app')

@section('title', 'Client Messages')
@section('page_title', 'Client Messages')
@section('breadcrumb', 'Client Messages')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Client Messages</h1>
        <p class="text-muted mb-0">Preview and filter messages delivered to customer accounts.</p>
    </div>
</div>

<form method="GET" action="{{ route('courier.client-messages.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All customers</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>
                        {{ $customer->mailbox ?: 'No mailbox' }} - {{ $customer->full_name ?: $customer->email }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Any</option>
                <option value="unread" @selected(request('status') === 'unread')>Unread</option>
                <option value="read" @selected(request('status') === 'read')>Read</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
                <option value="">Any</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Title, message, mailbox, email">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Filter</button>
            <a href="{{ route('courier.client-messages.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="row g-3">
    @forelse($messages as $message)
        @php($recipient = $message->user)
        <div class="col-lg-6">
            <div class="app-alert-card alert-info-modern h-100" data-app-alert>
                <div class="app-alert-icon"><i class="fas fa-envelope-open-text"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <div class="app-alert-title mb-0">{{ $message->title }}</div>
                        <span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $message->type)) }}</span>
                        <span class="badge {{ $message->is_read ? 'bg-light text-muted border' : 'bg-primary' }}">{{ $message->is_read ? 'Read' : 'Unread' }}</span>
                    </div>
                    <div class="small text-muted mb-2">
                        {{ optional($recipient)->mailbox ?: 'No mailbox' }} -
                        {{ optional($recipient)->full_name ?: optional($recipient)->email ?: 'Unknown customer' }}
                    </div>
                    <div class="app-alert-message">{!! $message->message !!}</div>
                    @if(! empty($message->data['url']))
                        <div class="mt-3"><a href="{{ $message->data['url'] }}" class="btn btn-sm btn-outline-primary">Open linked record</a></div>
                    @endif
                    <div class="text-muted small mt-3">
                        Sent {{ optional($message->created_at)->format('M d, Y h:i A') }}
                        @if($message->read_at)
                            <span class="ms-2">Read {{ $message->read_at->format('M d, Y h:i A') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="panel p-5 text-center">
                <div class="avatar-circle mx-auto mb-3" style="border-radius: 16px;"><i class="fas fa-envelope"></i></div>
                <h2 class="h5 mb-1">No client messages found</h2>
                <p class="text-muted mb-0">Messages sent to customer accounts will appear here.</p>
            </div>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $messages->links('pagination::bootstrap-5') }}</div>
@endsection
