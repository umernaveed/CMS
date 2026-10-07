@extends('layouts.app')

@section('title', 'CRM Messaging')
@section('page_title', 'CRM')
@section('breadcrumb', 'CRM / Email & Messaging')

@section('content')
@php
    $audiences = [
        'platform_users' => 'System-wide: all courier portals and clients',
        'courier' => 'Courier staff only',
        'customer' => 'Courier clients only',
        'all' => 'Selected courier portal: staff and clients',
        'super_admin' => 'SysAdmin only',
    ];
    $mergeTagGroups = [
        'Company' => ['company_name', 'courier_name', 'courier_email', 'courier_phone'],
        'Customer' => ['customer_name', 'first_name', 'last_name', 'customer_email', 'mailbox'],
        'Branch' => ['branch_name', 'branch_address', 'pickup_location'],
        'Package & Billing' => ['tracking_number', 'status', 'invoice_number', 'invoice_total', 'package_count'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">CRM Messaging</h1>
        <p class="text-muted mb-0">Use the existing system notification module to message couriers, clients, or the whole platform.</p>
    </div>
    <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#composeMessage" aria-expanded="false" aria-controls="composeMessage"><i class="fas fa-paper-plane me-1"></i>Compose Message</button><a href="{{ route('superadmin.support.index') }}" class="btn btn-outline-primary"><i class="fas fa-comments me-1"></i>Chat</a></div>
</div>

<div class="accordion" id="crmMessagingAccordion">
    <div class="accordion-item panel overflow-hidden mb-4">
        <h2 class="accordion-header" id="composeHeading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#composeMessage" aria-expanded="false" aria-controls="composeMessage">
                <strong>Email / Messaging Feature</strong>
            </button>
        </h2>
        <div id="composeMessage" class="accordion-collapse collapse" aria-labelledby="composeHeading" data-bs-parent="#crmMessagingAccordion">
            <div class="accordion-body">
                <form method="POST" action="{{ route('superadmin.notifications.store') }}" enctype="multipart/form-data" class="row g-3" data-superadmin-message-form>
                    @csrf
                    <div class="col-lg-4">
                        <label class="form-label">Audience</label>
                        <select name="audience" class="form-select" required>
                            @foreach($audiences as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience', 'platform_users') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Use "Selected courier portal" only after choosing a courier scope.</div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Courier Scope</label>
                        <select name="tenant_id" class="form-select">
                            <option value="">No courier scope / system-wide</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach(['low', 'medium', 'high'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Title</label>
                        <input name="title" class="form-control" value="{{ old('title') }}" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Starts</label>
                        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Ends</label>
                        <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <div class="d-flex flex-wrap align-items-center gap-1 mb-2 p-2 border rounded bg-light" role="group" aria-label="Message editor">
                            <select class="form-select form-select-sm" style="width: 150px;" data-message-font>
                                <option value="">Font</option>
                                <option value="Arial">Arial</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                            <select class="form-select form-select-sm" style="width: 95px;" data-message-size>
                                <option value="">Size</option>
                                <option value="2">12px</option>
                                <option value="3">14px</option>
                                <option value="4">16px</option>
                                <option value="5">18px</option>
                                <option value="6">24px</option>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="bold" title="Bold"><i class="fas fa-bold"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="italic" title="Italic"><i class="fas fa-italic"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="underline" title="Underline"><i class="fas fa-underline"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="strikeThrough" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="justifyLeft" title="Align left"><i class="fas fa-align-left"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="justifyCenter" title="Align center"><i class="fas fa-align-center"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="justifyRight" title="Align right"><i class="fas fa-align-right"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-command="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                            <label class="btn btn-outline-secondary mb-0" title="Text color">
                                <i class="fas fa-font"></i><input type="color" value="#0f172a" class="visually-hidden" data-message-color>
                            </label>
                            <label class="btn btn-outline-secondary mb-0" title="Highlight color">
                                <i class="fas fa-highlighter"></i><input type="color" value="#fff4b8" class="visually-hidden" data-message-highlight>
                            </label>
                            <button type="button" class="btn btn-outline-secondary" data-message-link title="Link"><i class="fas fa-link"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-table title="Table"><i class="fas fa-table"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-columns title="Columns"><i class="fas fa-columns"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-pagebreak title="Page break"><i class="fas fa-file-lines"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-message-smart-text title="Smart Text">Smart Text</button>
                            <select class="form-select form-select-sm" style="width: 210px;" data-message-token>
                                <option value="">Merge tag</option>
                                @foreach($mergeTagGroups as $group => $tokens)
                                    <optgroup label="{{ $group }}">
                                        @foreach($tokens as $token)
                                            <option value="&#123;&#123; {{ $token }} &#125;&#125;">&#123;&#123; {{ $token }} &#125;&#125;</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <textarea name="message" rows="5" class="form-control d-none" required data-message-body>{{ old('message') }}</textarea>
                        <div class="form-control" contenteditable="true" data-message-editor style="min-height: 160px; overflow:auto;">{!! old('message') !!}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Attach Documents</label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp">
                        <div class="form-text">Attach onboarding, customs, authorization, or other forms for recipients to view or download.</div>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <label class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label">Send / publish as active</span>
                        </label>
                        <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="accordion-item panel overflow-hidden">
        <h2 class="accordion-header" id="historyHeading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#messageHistory" aria-expanded="true" aria-controls="messageHistory">
                <strong>Notification Center</strong>
            </button>
        </h2>
        <div id="messageHistory" class="accordion-collapse collapse show" aria-labelledby="historyHeading" data-bs-parent="#crmMessagingAccordion">
            <div class="accordion-body">
                @php($notificationTabs = ['active' => 'Active', 'scheduled' => 'Scheduled', 'inactive' => 'Inactive / Expired'])
                @php($activeNotificationTab = request('notification_tab', 'active'))
                <ul class="nav nav-tabs mb-3">
                    @foreach($notificationTabs as $tabValue => $tabLabel)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeNotificationTab === $tabValue ? 'active' : '' }}" href="{{ route('superadmin.notifications.index', array_merge(request()->except(['notification_tab', 'page']), ['notification_tab' => $tabValue])) }}">
                                {{ $tabLabel }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <form method="GET" class="row g-2 mb-4">
                    <input type="hidden" name="notification_tab" value="{{ $activeNotificationTab }}">
                    <div class="col-md-4">
                        <select name="audience" class="form-select">
                            <option value="">All audiences</option>
                            @foreach($audiences as $value => $label)
                                <option value="{{ $value }}" @selected(request('audience') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="priority" class="form-select">
                            <option value="">All priorities</option>
                            @foreach(['low', 'medium', 'high'] as $priority)
                                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ route('superadmin.notifications.index', ['notification_tab' => $activeNotificationTab]) }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>

                @forelse($notifications as $notification)
                    <div class="border rounded mb-3 overflow-hidden">
                        <div class="p-3 d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="fw-bold">{{ $notification->title }}</div>
                                <div class="text-muted small">
                                    {{ $audiences[$notification->audience] ?? ucfirst($notification->audience) }} &middot;
                                    {{ ucfirst($notification->priority) }} priority &middot;
                                    {{ optional($notification->tenant)->company_name ?: 'System-wide' }}
                                </div>
                                <div class="text-muted mt-2">{{ $notification->messagePreview(150) }}</div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <span class="badge {{ $notification->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $notification->is_active ? 'Live' : 'Inactive' }}</span>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#notificationDetail{{ $notification->id }}"><i class="fas fa-eye me-1"></i>Open</button>
                            </div>
                        </div>
                        <div class="collapse border-top" id="notificationDetail{{ $notification->id }}">
                            <div class="p-3">
                                <form method="POST" action="{{ route('superadmin.notifications.update', $notification) }}" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $notification->title) }}" required></div>
                                        <div class="col-md-3"><label class="form-label">Courier Scope</label><select name="tenant_id" class="form-select"><option value="">No courier scope / system-wide</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected($notification->tenant_id == $tenant->id)>{{ $tenant->company_name }}</option>@endforeach</select></div>
                                        <div class="col-md-3"><label class="form-label">Audience</label><select name="audience" class="form-select">@foreach($audiences as $value => $label)<option value="{{ $value }}" @selected($notification->audience === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div class="col-md-2"><label class="form-label">Priority</label><select name="priority" class="form-select">@foreach(['low', 'medium', 'high'] as $priority)<option value="{{ $priority }}" @selected($notification->priority === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></div>
                                        <div class="col-12"><label class="form-label">Message</label><textarea name="message" rows="4" class="form-control" required>{{ old('message', $notification->message) }}</textarea></div>
                                        <div class="col-12">
                                            <label class="form-label">Add Documents</label>
                                            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp">
                                            @if(! empty($notification->attachments_json))
                                                <div class="small text-muted mt-2">Saved: {{ collect($notification->attachments_json)->pluck('name')->filter()->implode(', ') }}</div>
                                            @endif
                                        </div>
                                        <div class="col-md-4"><label class="form-label">Starts</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ optional($notification->starts_at)->format('Y-m-d\TH:i') }}"></div>
                                        <div class="col-md-4"><label class="form-label">Ends</label><input type="datetime-local" name="ends_at" class="form-control" value="{{ optional($notification->ends_at)->format('Y-m-d\TH:i') }}"></div>
                                        <div class="col-md-4 d-flex align-items-end justify-content-between gap-2">
                                            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($notification->is_active)> <span class="form-check-label small">Live</span></label>
                                            <button class="btn btn-outline-primary">Save</button>
                                        </div>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('superadmin.notifications.destroy', $notification) }}" class="text-end mt-2" onsubmit="return confirm('Delete this notification?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No {{ strtolower($notificationTabs[$activeNotificationTab] ?? 'matching') }} notifications.</div>
                @endforelse

                {{ $notifications->appends(array_merge(request()->query(), ['notification_tab' => $activeNotificationTab]))->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-superadmin-message-form]').forEach(function (form) {
        const editor = form.querySelector('[data-message-editor]');
        const body = form.querySelector('[data-message-body]');
        if (!editor || !body) return;

        function sync() {
            body.value = editor.innerHTML.trim();
        }

        form.querySelectorAll('[data-message-command]').forEach(function (button) {
            button.addEventListener('click', function () {
                editor.focus();
                document.execCommand(button.dataset.messageCommand, false, null);
                sync();
            });
        });

        form.querySelector('[data-message-font]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            editor.focus();
            document.execCommand('fontName', false, event.target.value);
            event.target.value = '';
            sync();
        });

        form.querySelector('[data-message-size]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            editor.focus();
            document.execCommand('fontSize', false, event.target.value);
            event.target.value = '';
            sync();
        });

        form.querySelector('[data-message-color]')?.addEventListener('input', function (event) {
            editor.focus();
            document.execCommand('foreColor', false, event.target.value);
            sync();
        });

        form.querySelector('[data-message-highlight]')?.addEventListener('input', function (event) {
            editor.focus();
            document.execCommand('hiliteColor', false, event.target.value);
            sync();
        });

        form.querySelector('[data-message-link]')?.addEventListener('click', function () {
            const url = prompt('Enter link URL');
            if (!url) return;
            editor.focus();
            document.execCommand('createLink', false, url);
            sync();
        });

        form.querySelector('[data-message-table]')?.addEventListener('click', function () {
            editor.focus();
            document.execCommand('insertHTML', false, '<table style="width:100%; border-collapse:collapse;" border="1"><tbody><tr><td>Label</td><td>Value</td></tr></tbody></table>');
            sync();
        });

        form.querySelector('[data-message-columns]')?.addEventListener('click', function () {
            editor.focus();
            document.execCommand('insertHTML', false, '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"><div>Column one</div><div>Column two</div></div>');
            sync();
        });

        form.querySelector('[data-message-pagebreak]')?.addEventListener('click', function () {
            editor.focus();
            document.execCommand('insertHTML', false, '<hr style="page-break-after:always;">');
            sync();
        });

        form.querySelector('[data-message-smart-text]')?.addEventListener('click', function () {
            editor.focus();
            document.execCommand('insertHTML', false, '<p>Hi &#123;&#123; first_name &#125;&#125;,</p><p>We are sharing this update from &#123;&#123; company_name &#125;&#125;. Please review the details below and contact your courier team if you need assistance.</p>');
            sync();
        });

        form.querySelector('[data-message-token]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            editor.focus();
            document.execCommand('insertText', false, event.target.value);
            event.target.value = '';
            sync();
        });

        editor.addEventListener('input', sync);
        form.addEventListener('submit', sync);
        sync();
    });
});
</script>
@endsection
