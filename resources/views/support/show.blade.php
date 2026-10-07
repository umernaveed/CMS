@extends('layouts.app')

@section('title', $ticket->subject)
@section('page_title', 'Support Chat')
@section('breadcrumb', $ticket->ticket_number)

@section('content')
<style>
    .chat-shell {
        height: calc(100dvh - 170px);
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        overflow: hidden;
    }
    .chat-main {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
    }
    .chat-thread {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding: 22px;
        background: linear-gradient(180deg, #f8fafc, #fff);
    }
    .chat-bubble-row {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        align-items: flex-end;
    }
    .chat-bubble-row.mine {
        flex-direction: row-reverse;
    }
    .chat-bubble {
        max-width: min(680px, 82%);
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px 14px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
    }
    .chat-bubble-row.mine .chat-bubble {
        background: var(--brand);
        border-color: var(--brand);
        color: #fff;
    }
    .chat-bubble-row.mine .text-muted,
    .chat-bubble-row.mine a {
        color: rgba(255,255,255,.78) !important;
    }
    .chat-composer {
        flex: 0 0 auto;
        border-top: 1px solid var(--line);
        padding: 16px;
        background: #fff;
    }
    .chat-side {
        border-left: 1px solid var(--line);
        background: #fff;
        padding: 20px;
        overflow-y: auto;
    }
    @media (max-width: 991px) {
        .chat-shell {
            height: auto;
            min-height: 0;
            overflow: visible;
            grid-template-columns: 1fr;
        }
    }
</style>

@php($conversationClosed = $ticket->status === 'closed')

<div class="panel chat-shell">
    <section class="chat-main">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-start gap-3">
            <div>
                <h1 class="h4 mb-1">{{ $ticket->subject }}</h1>
                <div class="text-muted small">{{ $ticket->ticket_number }} &middot; {{ ucfirst($ticket->priority) }} priority</div>
            </div>
            <div class="d-flex flex-wrap gap-2"><a href="{{ route($routeBase.'.transcript', $ticket) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Transcript</a><a href="{{ route($routeBase.'.index') }}" class="btn btn-outline-secondary">Back</a></div>
        </div>

        <div class="chat-thread" id="supportChatThread" data-messages-url="{{ route($routeBase.'.messages', $ticket) }}" data-current-user="{{ auth()->id() }}" data-viewer-timezone="{{ $viewerTimezone }}">
            @foreach($ticket->messages as $message)
                @php($mine = $message->user_id === auth()->id())
                <div class="chat-bubble-row {{ $mine ? 'mine' : '' }}" data-message-id="{{ $message->id ?? 0 }}">
                    <span class="avatar-circle" style="width: 36px; height: 36px; font-size: 12px;">{{ strtoupper(substr(optional($message->user)->full_name ?: optional($message->user)->email ?: 'S', 0, 2)) }}</span>
                    <div class="chat-bubble">
                        <div class="d-flex justify-content-between gap-3 mb-1">
                            <strong>{{ optional($message->user)->full_name ?: optional($message->user)->email ?: 'Support' }}</strong>
                            <span class="text-muted small chat-time" data-timestamp="{{ optional($message->created_at)->copy()?->utc()?->toIso8601String() }}">{{ optional($message->created_at)->copy()?->timezone($viewerTimezone)->format('M d, h:i A') }}</span>
                        </div>
                        <div style="white-space: pre-line">{{ $message->message }}</div>
                        @if($message->attachment_path)
                            <div class="mt-2">
                                <a href="{{ asset($message->attachment_path) }}" target="_blank" class="fw-bold"><i class="fas fa-paperclip me-1"></i>Attachment</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route($routeBase.'.reply', $ticket) }}" enctype="multipart/form-data" class="chat-composer" id="supportChatForm" data-closed="{{ $conversationClosed ? 'true' : 'false' }}">
            @csrf
            <div class="alert alert-secondary mb-3" id="supportChatClosedNotice" @unless($conversationClosed) hidden @endunless>
                <i class="fas fa-lock me-1"></i>This chat is closed. Reopen it from the status panel before sending new messages.
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Reply</label>
                    <textarea name="message" class="form-control" rows="2" required placeholder="Type your message..." @disabled($conversationClosed)></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label">File</label>
                    <input type="file" name="attachment" class="form-control" @disabled($conversationClosed)>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" id="supportChatSend" @disabled($conversationClosed)><i class="fas {{ $conversationClosed ? 'fa-lock' : 'fa-paper-plane' }} me-1"></i><span data-send-label>{{ $conversationClosed ? 'Closed' : 'Send' }}</span></button>
                </div>
            </div>
        </form>
    </section>

    <aside class="chat-side">
        <h2 class="h5 mb-3">Details</h2>
        <div class="mb-3"><div class="text-muted small">Status</div><span class="badge badge-soft" id="supportTicketStatusBadge">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></div>
        <div class="mb-3"><div class="text-muted small">Courier</div><strong>{{ optional($ticket->tenant)->company_name ?? 'Platform' }}</strong></div>
        <div class="mb-3"><div class="text-muted small">Requester</div><strong>{{ optional($ticket->user)->full_name ?: optional($ticket->user)->email ?: '-' }}</strong></div>
        <div class="mb-3"><div class="text-muted small">Assigned</div><strong id="supportTicketAssignee">{{ optional($ticket->assignee)->full_name ?: optional($ticket->assignee)->email ?: 'Unassigned' }}</strong></div>
        <div class="mb-3"><div class="text-muted small">Created</div><span class="chat-time" data-timestamp="{{ optional($ticket->created_at)->copy()?->utc()?->toIso8601String() }}">{{ optional($ticket->created_at)->copy()?->timezone($viewerTimezone)->format('M d, Y h:i A') }}</span></div>

        @if($canManageStatus)
            <form method="POST" action="{{ route($routeBase.'.status', $ticket) }}" class="mt-4">
                @csrf
                @method('PATCH')
                <label class="form-label">Update Status</label>
                <select name="status" class="form-select mb-2">
                    @foreach(['open', 'in_progress', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected($ticket->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-primary w-100">Update</button>
            </form>
        @endif

        @if(auth()->user()->user_type === 'super_admin')
            <form method="POST" action="{{ route($routeBase.'.destroy', $ticket) }}" class="mt-3" onsubmit="return confirm('Delete this conversation?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger w-100">Delete Conversation</button>
            </form>
        @endif
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const thread = document.getElementById('supportChatThread');
    const form = document.getElementById('supportChatForm');
    if (!thread || !form) return;

    let lastMessageId = Math.max(0, ...Array.from(thread.querySelectorAll('[data-message-id]')).map(row => parseInt(row.dataset.messageId || '0', 10)));
    let polling = false;
    let unreadMessages = 0;
    const originalTitle = document.title;
    const csrf = form.querySelector('input[name="_token"]').value;
    let conversationClosed = form.dataset.closed === 'true';
    const closedNotice = document.getElementById('supportChatClosedNotice');
    const composerFields = form.querySelectorAll('textarea, input[type="file"], button[type="submit"], #supportChatSend');
    const statusBadge = document.getElementById('supportTicketStatusBadge');
    const viewerTimezone = thread.dataset.viewerTimezone || 'UTC';
    const dateFormatter = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: viewerTimezone
    });
    const detailDateFormatter = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: viewerTimezone
    });

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function formatTimestamp(value, includeYear = false) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';

        return (includeYear ? detailDateFormatter : dateFormatter).format(date).replace(',', '');
    }

    function updateStatus(status, label) {
        if (!status) return;
        if (statusBadge) {
            statusBadge.textContent = label || status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
        }
        setComposerClosed(status === 'closed');
    }

    function setComposerClosed(isClosed) {
        conversationClosed = Boolean(isClosed);
        form.dataset.closed = conversationClosed ? 'true' : 'false';
        if (closedNotice) {
            closedNotice.hidden = !conversationClosed;
        }
        composerFields.forEach(function (field) {
            field.disabled = conversationClosed;
        });
        const sendIcon = form.querySelector('#supportChatSend i');
        const sendLabel = form.querySelector('[data-send-label]');
        if (sendIcon) {
            sendIcon.classList.toggle('fa-lock', conversationClosed);
            sendIcon.classList.toggle('fa-paper-plane', !conversationClosed);
        }
        if (sendLabel) {
            sendLabel.textContent = conversationClosed ? 'Closed' : 'Send';
        }
    }

    function updateAssignee(name) {
        const assignee = document.getElementById('supportTicketAssignee');
        if (assignee && name) assignee.textContent = name;
    }

    function setBadge(element, count) {
        if (!element) return;
        count = parseInt(count || '0', 10);
        if (count > 0) {
            element.textContent = count;
            element.hidden = false;
        } else {
            element.hidden = true;
        }
    }

    function updateNotificationCounts(data) {
        if (!data) return;
        const directCount = parseInt(data.notification_count ?? data.unread_direct_count ?? '0', 10);
        const supportCount = parseInt(data.unread_support_count || '0', 10);
        setBadge(document.querySelector('[data-notification-badge]'), directCount);
        document.querySelectorAll('[data-support-chat-badge]').forEach(function (badge) {
            setBadge(badge, supportCount);
        });
        const bell = document.querySelector('[data-notification-button]');
        if (bell) {
            bell.classList.toggle('has-unread', directCount > 0);
        }
    }

    function announceNewMessage() {
        if (typeof window.playNotificationSound === 'function') {
            window.playNotificationSound();
        }

        if ('speechSynthesis' in window) {
            try {
                const utterance = new SpeechSynthesisUtterance('New Message');
                utterance.rate = 0.95;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            } catch (error) {}
        }
    }

    function markIncoming(messages) {
        const incoming = messages.filter(message => !message.mine);
        if (!incoming.length) return;

        announceNewMessage();

        if (!document.hasFocus()) {
            unreadMessages += incoming.length;
            document.title = `(${unreadMessages}) ${originalTitle}`;
        }
    }

    function resetUnread() {
        unreadMessages = 0;
        document.title = originalTitle;
    }

    function messageHtml(message) {
        const mine = message.mine ? ' mine' : '';
        const attachment = message.attachment_url
            ? `<div class="mt-2"><a href="${escapeHtml(message.attachment_url)}" target="_blank" class="fw-bold"><i class="fas fa-paperclip me-1"></i>Attachment</a></div>`
            : '';
        const createdAt = formatTimestamp(message.created_at_iso) || message.created_at || '';
        return `
            <div class="chat-bubble-row${mine}" data-message-id="${message.id}">
                <span class="avatar-circle" style="width: 36px; height: 36px; font-size: 12px;">${escapeHtml(message.initials || 'S')}</span>
                <div class="chat-bubble">
                    <div class="d-flex justify-content-between gap-3 mb-1">
                        <strong>${escapeHtml(message.name)}</strong>
                        <span class="text-muted small chat-time" data-timestamp="${escapeHtml(message.created_at_iso || '')}">${escapeHtml(createdAt)}</span>
                    </div>
                    <div style="white-space: pre-line">${escapeHtml(message.message)}</div>
                    ${attachment}
                </div>
            </div>`;
    }

    function appendMessages(messages) {
        if (!messages.length) return;
        const nearBottom = thread.scrollTop + thread.clientHeight >= thread.scrollHeight - 120;
        const appended = [];
        messages.forEach(function (message) {
            if (message.id <= lastMessageId || thread.querySelector(`[data-message-id="${message.id}"]`)) return;
            thread.insertAdjacentHTML('beforeend', messageHtml(message));
            appended.push(message);
            lastMessageId = Math.max(lastMessageId, message.id);
        });
        markIncoming(appended);
        if (nearBottom) {
            thread.scrollTop = thread.scrollHeight;
        }
    }

    async function pollMessages() {
        if (polling) return;
        polling = true;
        try {
            const response = await fetch(`${thread.dataset.messagesUrl}?after_id=${lastMessageId}`, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            if (response.ok) {
                const data = await response.json();
                appendMessages(data.messages || []);
                updateStatus(data.ticket_status, data.ticket_status_label);
                updateAssignee(data.assignee_name);
                updateNotificationCounts(data);
            }
        } finally {
            polling = false;
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (conversationClosed) {
            setComposerClosed(true);
            return;
        }

        const button = document.getElementById('supportChatSend');
        const formData = new FormData(form);
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf}
            });
            if (!response.ok) {
                if (response.status === 409) {
                    const data = await response.json();
                    updateStatus(data.ticket_status || 'closed', data.ticket_status_label || 'Closed');
                    updateAssignee(data.assignee_name);
                    updateNotificationCounts(data);
                    return;
                }
                form.submit();
                return;
            }
            const data = await response.json();
            appendMessages(data.message ? [data.message] : []);
            updateStatus(data.ticket_status, data.ticket_status_label);
            updateAssignee(data.assignee_name);
            updateNotificationCounts(data);
            form.reset();
            thread.scrollTop = thread.scrollHeight;
        } catch (error) {
            form.submit();
        } finally {
            button.disabled = conversationClosed;
        }
    });

    thread.scrollTop = thread.scrollHeight;
    document.querySelectorAll('.chat-time[data-timestamp]').forEach(function (element) {
        const includeYear = element.textContent.trim().split(' ').length > 3;
        const formatted = formatTimestamp(element.dataset.timestamp, includeYear);
        if (formatted) element.textContent = formatted;
    });
    setInterval(pollMessages, 1500);
    window.addEventListener('focus', function () {
        resetUnread();
        pollMessages();
    });
});
</script>
@endsection


