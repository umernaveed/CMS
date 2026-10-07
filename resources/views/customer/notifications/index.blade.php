@extends('layouts.app')

@section('title', 'Messages & Notifications')
@section('page_title', 'Messages & Notifications')
@section('breadcrumb', 'Messages & Notifications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Messages & Notifications</h1>
        <p class="text-muted mb-0">Review account alerts, courier updates, and platform notices.</p>
    </div>
</div>

<form method="GET" action="{{ route('customer.messages.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6"><label class="form-label">Search Courier Messages</label><input name="direct_q" class="form-control" value="{{ request('direct_q') }}" placeholder="Title, message, type"></div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="direct_status" class="form-select"><option value="">All</option><option value="unread" @selected(request('direct_status') === 'unread')>Unread</option><option value="read" @selected(request('direct_status') === 'read')>Read</option></select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Type</label>
            <select name="direct_type" class="form-select"><option value="">All</option>@foreach($directTypes as $type)<option value="{{ $type }}" @selected(request('direct_type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>@endforeach</select>
        </div>
        <div class="col-lg-4 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button><a href="{{ route('customer.messages.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
@if($clientNotifications->count())
    <div class="mb-4">
        <h2 class="h5 mb-3">Courier Messages</h2>
        <div class="row g-3">
            @foreach($clientNotifications as $message)
                <div class="col-lg-6">
                    <div class="app-alert-card alert-info-modern h-100" data-app-alert>
                        <div class="app-alert-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <div class="app-alert-title mb-0">{{ $message->title }}</div>
                                <span class="badge badge-soft">Courier message</span>
                            </div>
                            <div class="app-alert-message">{!! $message->message !!}</div>
                            <div class="text-muted small mt-3">{{ optional($message->created_at)->format('M d, Y h:i A') }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $clientNotifications->links('pagination::bootstrap-5', ['perPageName' => 'direct_per_page']) }}</div>
    </div>
@endif

<h2 class="h5 mb-3">Account Notifications</h2>
<form method="GET" action="{{ route('customer.messages.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6"><label class="form-label">Search Account Notifications</label><input name="system_q" class="form-control" value="{{ request('system_q') }}" placeholder="Title or message"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="">All</option>@foreach(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Dismissed</label><select name="dismissed" class="form-select"><option value="">All</option><option value="active" @selected(request('dismissed') === 'active')>Active</option><option value="dismissed" @selected(request('dismissed') === 'dismissed')>Dismissed</option></select></div>
        <div class="col-lg-4 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button><a href="{{ route('customer.messages.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
<div class="row g-3">
    @forelse($notifications as $notification)
        @php
            $tone = $notification->priority === 'high'
                ? ['class' => 'alert-danger-modern', 'icon' => 'fa-triangle-exclamation', 'label' => 'High priority']
                : ($notification->priority === 'medium'
                    ? ['class' => 'alert-warning-modern', 'icon' => 'fa-circle-exclamation', 'label' => 'Medium priority']
                    : ['class' => 'alert-info-modern', 'icon' => 'fa-circle-info', 'label' => 'Notice']);
        @endphp
        <div class="col-lg-6">
            <div class="app-alert-card {{ $tone['class'] }} h-100" data-app-alert>
                <div class="app-alert-icon"><i class="fas {{ $tone['icon'] }}"></i></div>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <div class="app-alert-title mb-0">{{ $notification->title }}</div>
                        <span class="badge badge-soft">{{ $tone['label'] }}</span>
                        @if($notification->dismissed_count)
                            <span class="badge bg-light text-muted border">Dismissed</span>
                        @endif
                    </div>
                    <div class="app-alert-message">{!! $notification->messageHtml() !!}</div>
                    <div class="text-muted small mt-3">
                        {{ optional($notification->starts_at ?? $notification->created_at)->format('M d, Y h:i A') }}
                    </div>
                </div>
                <div class="app-alert-actions">
                    @unless($notification->dismissed_count)
                        <form method="POST" action="{{ route('notifications.dismiss', $notification) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">Dismiss</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    @empty
        @if(! $clientNotifications->count())
        <div class="col-12">
            <div class="panel p-5 text-center">
                <div class="avatar-circle mx-auto mb-3" style="border-radius: 16px;"><i class="fas fa-bell-slash"></i></div>
                <h2 class="h5 mb-1">No messages yet</h2>
                <p class="text-muted mb-0">New customer alerts and courier notices will appear here.</p>
            </div>
        </div>
        @else
            <div class="col-12">
                <div class="panel p-4 text-center text-muted">No account notifications right now.</div>
            </div>
        @endif
    @endforelse
</div>

<div class="mt-4">{{ $notifications->links('pagination::bootstrap-5', ['perPageName' => 'system_per_page']) }}</div>
@endsection


