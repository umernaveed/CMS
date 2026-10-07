<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\ClientNotification;
use App\Models\SystemNotification;
use App\Models\Tenant;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportChatController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $status = $request->input('status', 'in_progress');
        $tickets = $this->visibleTickets()
            ->with(['tenant', 'user', 'assignee', 'latestMessage.user'])
            ->where('status', $status)
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->latest('updated_at')
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();

        return view('support.index', [
            'tickets' => $tickets,
            'routeBase' => $this->routeBase($user),
            'canCreateForOthers' => $user->user_type === 'super_admin',
            'title' => $this->supportTitle($user),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $tenants = $user->user_type === 'super_admin' ? Tenant::orderBy('company_name')->get() : collect();
        $users = $user->user_type === 'super_admin'
            ? User::whereIn('user_type', ['tenant_admin', 'staff'])->orderBy('tenant_id')->orderBy('full_name')->orderBy('email')->get()
            : collect();

        return view('support.create', [
            'tenants' => $tenants,
            'users' => $users,
            'routeBase' => $this->routeBase($user),
            'canCreateForOthers' => $user->user_type === 'super_admin',
            'title' => $this->supportTitle($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $rules = [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'attachment' => 'nullable|file|max:5120',
        ];

        if ($user->user_type === 'super_admin') {
            $rules['tenant_id'] = 'required|exists:tenants,id';
            $rules['user_id'] = 'nullable|exists:users,id';
        }

        $data = $request->validate($rules);
        $requester = $user;

        if ($user->user_type === 'super_admin' && ! empty($data['user_id'])) {
            $requester = User::findOrFail($data['user_id']);
            abort_unless(
                in_array($requester->user_type, ['tenant_admin', 'staff'], true)
                    && (int) $requester->tenant_id === (int) $data['tenant_id'],
                403
            );
        }

        $tenantId = $user->user_type === 'super_admin'
            ? ($data['tenant_id'] ?? $requester->tenant_id)
            : $user->tenant_id;

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'user_id' => $requester->id,
            'ticket_number' => 'SUP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'],
            'status' => 'open',
            'assigned_to' => $user->user_type === 'super_admin' ? $user->id : null,
        ]);

        $message = $this->createMessage($ticket, $user, $data['message'], $request);
        $this->notifyNewMessage($ticket, $message->load('user'));

        return redirect()->route($this->routeBase($user).'.show', $ticket)->with('success', 'Support conversation started.');
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);
        ClientNotification::where('user_id', auth()->id())
            ->where('type', 'support_message')
            ->where('data->ticket_id', $ticket->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $ticket->load(['tenant', 'user', 'assignee', 'messages.user']);
        if ($ticket->messages->isEmpty() && filled($ticket->message)) {
            $ticket->setRelation('messages', collect([
                new SupportMessage([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'message' => $ticket->message,
                    'created_at' => $ticket->created_at,
                ]),
            ]));
        }

        return view('support.show', [
            'ticket' => $ticket,
            'routeBase' => $this->routeBase(auth()->user()),
            'canManageStatus' => in_array(auth()->user()->user_type, ['super_admin', 'tenant_admin', 'staff']),
            'viewerTimezone' => $this->viewerTimezone($ticket),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);
        $user = auth()->user();

        if ($ticket->status === 'closed') {
            return $this->closedTicketReplyResponse($request, $ticket, $user);
        }

        $data = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $message = $this->createMessage($ticket, $user, $data['message'], $request);
        $this->notifyNewMessage($ticket, $message->load('user'));
        $updates = [
            'status' => 'in_progress',
            'closed_at' => null,
        ];

        if (! $ticket->assigned_to && in_array($user->user_type, ['super_admin', 'tenant_admin', 'staff'], true)) {
            $updates['assigned_to'] = $user->id;
        }

        $ticket->update($updates);

        if ($request->expectsJson()) {
            $freshTicket = $ticket->fresh(['assignee']);

            return response()->json([
                'message' => $this->messagePayload($message->load('user'), $ticket),
                'ticket_status' => $freshTicket->status,
                'ticket_status_label' => $this->statusLabel($freshTicket->status),
                'assignee_name' => $this->assigneeName($freshTicket),
                'notification_count' => $this->notificationCount($user),
                'unread_direct_count' => $this->unreadDirectCount($user),
                'unread_support_count' => $this->unreadSupportCount($user),
            ]);
        }

        return redirect()->route($this->routeBase($user).'.show', $ticket)->with('success', 'Message sent.');
    }

    public function messages(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);

        $afterId = (int) $request->query('after_id', 0);
        $messages = $ticket->messages()
            ->with('user')
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn ($message) => $this->messagePayload($message, $ticket))
            ->values();
        $this->markTicketNotificationsRead($ticket);
        $freshTicket = $ticket->fresh(['assignee']);

        return response()->json([
            'messages' => $messages,
            'ticket_status' => $freshTicket->status,
            'ticket_status_label' => $this->statusLabel($freshTicket->status),
            'assignee_name' => $this->assigneeName($freshTicket),
            'notification_count' => $this->notificationCount(auth()->user()),
            'unread_direct_count' => $this->unreadDirectCount(auth()->user()),
            'unread_support_count' => $this->unreadSupportCount(auth()->user()),
        ]);
    }

    public function downloadTranscript(SupportTicket $ticket): StreamedResponse
    {
        $this->authorizeTicket($ticket);
        $ticket->load(['tenant', 'user', 'assignee', 'messages.user']);
        $messages = $ticket->messages;

        if ($messages->isEmpty() && filled($ticket->message)) {
            $messages = collect([
                new SupportMessage([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'message' => $ticket->message,
                    'created_at' => $ticket->created_at,
                ]),
            ]);
        }

        $filename = Str::slug($ticket->ticket_number ?: 'support-ticket').'-transcript-'.now()->format('Ymd-His').'.csv';
        $timezone = $this->viewerTimezone($ticket);

        return response()->streamDownload(function () use ($ticket, $messages, $timezone) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ticket_number', $ticket->ticket_number]);
            fputcsv($out, ['subject', $ticket->subject]);
            fputcsv($out, ['courier', optional($ticket->tenant)->company_name ?: 'Platform']);
            fputcsv($out, ['requester', optional($ticket->user)->full_name ?: optional($ticket->user)->email ?: '-']);
            fputcsv($out, ['status', $this->statusLabel($ticket->status)]);
            fputcsv($out, ['priority', ucfirst($ticket->priority)]);
            fputcsv($out, []);
            fputcsv($out, ['sent_at', 'sender', 'email', 'message', 'attachment']);

            foreach ($messages as $message) {
                $sender = $message->user;
                fputcsv($out, [
                    optional($message->created_at)->copy()?->timezone($timezone)->format('Y-m-d H:i:s'),
                    optional($sender)->full_name ?: optional($sender)->email ?: 'Support',
                    optional($sender)->email,
                    trim(preg_replace('/\s+/', ' ', strip_tags((string) $message->message))),
                    $message->attachment_path ? asset($message->attachment_path) : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);
        abort_unless(in_array(auth()->user()->user_type, ['super_admin', 'tenant_admin', 'staff']), 403);

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update([
            'status' => $data['status'],
            'closed_at' => in_array($data['status'], ['resolved', 'closed']) ? now() : null,
        ]);

        return redirect()->route($this->routeBase(auth()->user()).'.show', $ticket)->with('success', 'Conversation status updated.');
    }

    public function destroy(SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);
        abort_unless(auth()->user()->user_type === 'super_admin', 403);
        $ticket->delete();

        return redirect()->route('superadmin.support.index')->with('success', 'Support conversation deleted.');
    }

    private function closedTicketReplyResponse(Request $request, SupportTicket $ticket, User $user)
    {
        $message = 'This support chat is closed. Reopen it before sending new messages.';

        if ($request->expectsJson()) {
            return response()->json([
                'error' => $message,
                'ticket_status' => $ticket->status,
                'ticket_status_label' => $this->statusLabel($ticket->status),
                'assignee_name' => $this->assigneeName($ticket->fresh(['assignee'])),
                'notification_count' => $this->notificationCount($user),
                'unread_direct_count' => $this->unreadDirectCount($user),
                'unread_support_count' => $this->unreadSupportCount($user),
            ], 409);
        }

        throw ValidationException::withMessages([
            'message' => $message,
        ]);
    }
    private function createMessage(SupportTicket $ticket, User $user, string $message, Request $request): SupportMessage
    {
        $path = null;
        if ($request->hasFile('attachment')) {
            $directory = public_path('uploads/support');
            File::ensureDirectoryExists($directory);
            $file = $request->file('attachment');
            $filename = 'support-'.$ticket->id.'-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $path = 'uploads/support/'.$filename;
        }

        return SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $message,
            'attachment_path' => $path,
        ]);
    }

    private function messagePayload(SupportMessage $message, ?SupportTicket $ticket = null): array
    {
        $user = $message->user;
        $name = optional($user)->full_name ?: optional($user)->email ?: 'Support';
        $timezone = $this->viewerTimezone($ticket ?: $message->ticket);
        $createdAt = optional($message->created_at);

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'mine' => $message->user_id === auth()->id(),
            'name' => $name,
            'initials' => strtoupper(substr($name, 0, 2)),
            'message' => $message->message,
            'attachment_url' => $message->attachment_path ? asset($message->attachment_path) : null,
            'created_at' => $createdAt ? $createdAt->copy()->timezone($timezone)->format('M d, h:i A') : '',
            'created_at_iso' => $createdAt ? $createdAt->copy()->utc()->toIso8601String() : null,
            'created_at_timezone' => $timezone,
        ];
    }

    private function notifyNewMessage(SupportTicket $ticket, SupportMessage $message): void
    {
        $ticket->loadMissing('user');
        $sender = $message->user;
        $senderName = optional($sender)->full_name ?: optional($sender)->email ?: 'Support';
        $routeBase = fn (User $user) => $this->routeBase($user);

        $recipients = User::query()
            ->where('is_active', true)
            ->whereKeyNot($message->user_id)
            ->where(function ($query) use ($ticket) {
                $query
                    ->whereKey($ticket->user_id)
                    ->orWhere(function ($nested) use ($ticket) {
                        $nested->where('tenant_id', $ticket->tenant_id)
                            ->whereIn('user_type', ['tenant_admin', 'staff']);
                    })
                    ->when(optional($ticket->user)->user_type !== 'customer', fn ($nested) => $nested->orWhere('user_type', 'super_admin'));
            })
            ->get();

        foreach ($recipients as $recipient) {
            ClientNotification::create([
                'tenant_id' => $ticket->tenant_id,
                'user_id' => $recipient->id,
                'type' => 'support_message',
                'title' => 'New Message',
                'message' => $senderName.' replied to '.$ticket->subject,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'url' => route($routeBase($recipient).'.show', $ticket),
                ],
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
    }

    private function markTicketNotificationsRead(SupportTicket $ticket): void
    {
        ClientNotification::where('user_id', auth()->id())
            ->where('type', 'support_message')
            ->where('data->ticket_id', $ticket->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    private function unreadDirectCount(User $user): int
    {
        return ClientNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    private function notificationCount(User $user): int
    {
        $systemCount = SystemNotification::query()
            ->activeNow()
            ->visibleToUser($user)
            ->whereDoesntHave('dismissals', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->limit(8)
            ->get()
            ->count();

        return $systemCount + $this->unreadDirectCount($user);
    }

    private function unreadSupportCount(User $user): int
    {
        return ClientNotification::where('user_id', $user->id)
            ->where('type', 'support_message')
            ->where('is_read', false)
            ->count();
    }

    private function assigneeName(SupportTicket $ticket): string
    {
        return optional($ticket->assignee)->full_name ?: optional($ticket->assignee)->email ?: 'Unassigned';
    }

    private function viewerTimezone(?SupportTicket $ticket = null): string
    {
        $user = auth()->user();
        $timezone = optional(optional($user)->tenant)->timezone
            ?: optional(optional($ticket)->tenant)->timezone
            ?: config('app.timezone', 'UTC');

        return $this->validTimezone($timezone);
    }

    private function validTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return $timezone;
        }

        return 'UTC';
    }

    private function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    private function visibleTickets()
    {
        $user = auth()->user();
        $query = SupportTicket::query();

        if ($user->user_type === 'super_admin') {
            return $query
                ->whereNotNull('tenant_id')
                ->where(function ($nested) {
                    $nested
                        ->whereNull('user_id')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->whereIn('user_type', ['super_admin', 'tenant_admin', 'staff']));
                });
        }

        if (in_array($user->user_type, ['tenant_admin', 'staff'])) {
            return $query->where('tenant_id', $user->tenant_id);
        }

        return $query->where('user_id', $user->id);
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        abort_unless($this->visibleTickets()->whereKey($ticket->id)->exists(), 403);
    }

    private function routeBase(User $user): string
    {
        return match ($user->user_type) {
            'super_admin' => 'superadmin.support',
            'tenant_admin', 'staff' => 'courier.support',
            default => 'customer.support',
        };
    }

    private function supportTitle(User $user): string
    {
        return match ($user->user_type) {
            'super_admin' => 'Courier Support Chat',
            'tenant_admin', 'staff' => 'Support Chat',
            default => 'Help Chat',
        };
    }
}
