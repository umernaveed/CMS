<?php

namespace App\Http\Controllers;

use App\Models\NotificationDismissal;
use App\Models\Package;
use App\Models\ClientNotification;
use App\Models\SystemNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Mail\ClientBroadcastMail;
use App\Services\TenantMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->user_type === 'super_admin', 403);

        $notificationTab = $request->input('notification_tab', 'active');
        $notifications = SystemNotification::with('tenant')
            ->when($notificationTab === 'active', function ($query) {
                $query->activeNow();
            })
            ->when($notificationTab === 'scheduled', function ($query) {
                $query->where('is_active', true)->whereNotNull('starts_at')->where('starts_at', '>', now());
            })
            ->when($notificationTab === 'inactive', function ($query) {
                $query->where(function ($nested) {
                    $nested
                        ->where('is_active', false)
                        ->orWhere(fn ($ended) => $ended->whereNotNull('ends_at')->where('ends_at', '<', now()));
                });
            })
            ->when($request->filled('audience'), fn ($query) => $query->where('audience', $request->audience))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->latest()
            ->paginate($this->perPage(request(), 12))
            ->withQueryString();
        $tenants = Tenant::orderBy('company_name')->get();

        return view('superadmin.notifications.index', compact('notifications', 'tenants'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->user_type === 'super_admin', 403);

        $data = $this->validateNotification($request, true);
        $data['message'] = SystemNotification::sanitizeMessage($data['message']);
        $data['attachments_json'] = $this->storeAttachments($request);
        unset($data['attachments']);
        SystemNotification::create($data);
        [$sent, $failed] = $this->sendNotificationEmails($data);

        $message = 'Notification created successfully and emailed to '.$sent.' recipient'.($sent === 1 ? '' : 's').'.';
        if ($failed > 0) {
            $message .= ' '.$failed.' email'.($failed === 1 ? '' : 's').' failed.';
        }

        return redirect()->route('superadmin.notifications.index')->with('success', $message);
    }

    public function update(Request $request, SystemNotification $notification)
    {
        abort_unless(auth()->user()->user_type === 'super_admin', 403);

        $data = $this->validateNotification($request, false);
        $data['message'] = SystemNotification::sanitizeMessage($data['message']);
        $data['attachments_json'] = $this->mergeAttachments($notification->attachments_json ?? [], $this->storeAttachments($request));
        unset($data['attachments']);
        $notification->update($data);

        return redirect()->route('superadmin.notifications.index')->with('success', 'Notification updated successfully.');
    }

    public function destroy(SystemNotification $notification)
    {
        abort_unless(auth()->user()->user_type === 'super_admin', 403);

        $notification->delete();

        return redirect()->route('superadmin.notifications.index')->with('success', 'Notification deleted successfully.');
    }

    public function dismiss(SystemNotification $notification)
    {
        $user = auth()->user();
        abort_unless($notification->canBeSeenBy($user), 404);

        NotificationDismissal::firstOrCreate([
            'system_notification_id' => $notification->id,
            'user_id' => $user->id,
        ], [
            'dismissed_at' => now(),
        ]);

        return back();
    }

    public function openDirect(ClientNotification $notification)
    {
        $user = auth()->user();
        abort_unless((int) $notification->user_id === (int) $user->id, 404);

        $notification->update([
            'is_read' => true,
            'read_at' => $notification->read_at ?: now(),
        ]);

        $url = $notification->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function counts()
    {
        $user = auth()->user();

        $systemCount = SystemNotification::query()
            ->activeNow()
            ->visibleToUser($user)
            ->whereDoesntHave('dismissals', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        $directQuery = ClientNotification::where('user_id', $user->id)->where('is_read', false);
        $directCount = (clone $directQuery)->count();
        $latestDirect = (clone $directQuery)->latest('created_at')->first();

        $byType = (clone $directQuery)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($count) => (int) $count);

        $agingPackageCount = 0;
        if (in_array($user->user_type, ['tenant_admin', 'staff'], true) && $user->hasPermission('manage_package_exceptions')) {
            $agingPackageCount = Package::where('tenant_id', $user->tenant_id)
                ->when($user->user_type === 'staff' && $user->outlet_id, fn ($query) => $query->where('outlet_id', $user->outlet_id))
                ->currentlyAging()
                ->count();
        }

        return response()->json([
            'system' => $systemCount,
            'direct' => $directCount,
            'total' => $systemCount + $directCount,
            'types' => [
                'delivery_request' => $byType->get('delivery_request', 0),
                'payment_upload' => $byType->get('payment_upload', 0),
                'wallet_top_up' => $byType->get('wallet_top_up', 0),
                'support_message' => $byType->get('support_message', 0),
                'purchase_request' => $byType->get('purchase_request', 0),
                'bill_issued' => $byType->get('bill_issued', 0),
                'aging_package' => max($byType->get('aging_package', 0), $agingPackageCount),
            ],
            'latest' => $latestDirect ? [
                'id' => $latestDirect->id,
                'title' => $latestDirect->title,
                'message' => $latestDirect->messagePreview(140),
                'url' => route('notifications.direct.open', $latestDirect),
            ] : null,
        ]);
    }

    public function customerMessages(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->user_type === 'customer', 403);

        $directPerPage = $this->namedPerPage($request, 'direct_per_page', 12);
        $systemPerPage = $this->namedPerPage($request, 'system_per_page', 12);

        $clientNotifications = ClientNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->when($request->filled('direct_q'), function ($query) use ($request) {
                $term = '%'.$request->direct_q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('title', 'like', $term)
                        ->orWhere('message', 'like', $term)
                        ->orWhere('type', 'like', $term);
                });
            })
            ->when($request->filled('direct_status'), function ($query) use ($request) {
                if ($request->direct_status === 'read') {
                    $query->where('is_read', true);
                } elseif ($request->direct_status === 'unread') {
                    $query->where('is_read', false);
                }
            })
            ->when($request->filled('direct_type'), fn ($query) => $query->where('type', $request->direct_type))
            ->latest('created_at')
            ->paginate($directPerPage, ['*'], 'direct_page')
            ->appends($request->query());

        ClientNotification::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $notifications = SystemNotification::query()
            ->activeNow()
            ->visibleToUser($user)
            ->withCount(['dismissals as dismissed_count' => fn ($query) => $query->where('user_id', $user->id)])
            ->when($request->filled('system_q'), function ($query) use ($request) {
                $term = '%'.$request->system_q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('title', 'like', $term)
                        ->orWhere('message', 'like', $term);
                });
            })
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->when($request->filled('dismissed'), function ($query) use ($request, $user) {
                if ($request->dismissed === 'dismissed') {
                    $query->whereHas('dismissals', fn ($dismissal) => $dismissal->where('user_id', $user->id));
                } elseif ($request->dismissed === 'active') {
                    $query->whereDoesntHave('dismissals', fn ($dismissal) => $dismissal->where('user_id', $user->id));
                }
            })
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->latest()
            ->paginate($systemPerPage, ['*'], 'system_page')
            ->appends($request->query());

        $directTypes = ClientNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('customer.notifications.index', compact('notifications', 'clientNotifications', 'directTypes'));
    }

    public function courierClientMessages(Request $request)
    {
        $user = auth()->user();
        abort_unless(in_array($user->user_type, ['tenant_admin', 'staff'], true), 403);

        $tenantId = (int) $user->tenant_id;
        $customers = User::query()
            ->where('tenant_id', $tenantId)
            ->where('user_type', 'customer')
            ->orderBy('full_name')
            ->orderBy('email')
            ->get(['id', 'full_name', 'email', 'mailbox']);

        $messages = ClientNotification::query()
            ->with(['user.customer'])
            ->where('tenant_id', $tenantId)
            ->whereHas('user', fn ($query) => $query->where('user_type', 'customer'))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('user_id', $request->integer('customer_id')))
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->status === 'read') {
                    $query->where('is_read', true);
                } elseif ($request->status === 'unread') {
                    $query->where('is_read', false);
                }
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('title', 'like', $term)
                        ->orWhere('message', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery
                                ->where('full_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('mailbox', 'like', $term);
                        });
                });
            })
            ->latest('created_at')
            ->paginate($this->perPage(request(), 12))
            ->withQueryString();

        $types = ClientNotification::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('user', fn ($query) => $query->where('user_type', 'customer'))
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('courier.client-messages.index', compact('messages', 'customers', 'types'));
    }

    private function namedPerPage(Request $request, string $name, int $default): int
    {
        if ($request->input($name) === 'all') {
            return 10000;
        }

        $perPage = (int) $request->input($name, $default);

        return in_array($perPage, [10, 12, 15, 25, 50, 100], true) ? $perPage : $default;
    }
    private function validateNotification(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp|max:8192',
            'priority' => 'required|in:low,medium,high',
            'audience' => 'required|in:all,platform_users,courier,customer,super_admin',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $data['tenant_id'] = $data['tenant_id'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['tenant_id']) && $data['audience'] === 'all') {
            throw ValidationException::withMessages([
                'audience' => 'Choose "All platform users" for a system-wide broadcast, or "Super admins only" for SysAdmin notifications.',
            ]);
        }

        if (! empty($data['tenant_id']) && $data['audience'] === 'super_admin') {
            throw ValidationException::withMessages([
                'audience' => 'Super admin notifications cannot be assigned to a courier scope.',
            ]);
        }

        return $data;
    }

    private function sendNotificationEmails(array $data): array
    {
        $sent = 0;
        $failed = 0;
        $body = SystemNotification::sanitizeMessage($data['message']);
        $attachments = $data['attachments_json'] ?? [];

        $this->recipientQuery($data)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($data, $body, $attachments, &$sent, &$failed) {
                foreach ($users as $user) {
                    $subject = $this->renderTokens($data['title'], $user);
                    $message = $this->renderTokens($body, $user);
                    try {
                        app(TenantMailService::class)->send($user->tenant_id, $user->email, new ClientBroadcastMail($subject, $message, $attachments));
                        $sent++;
                    } catch (\Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        return [$sent, $failed];
    }

    private function recipientQuery(array $data)
    {
        $tenantId = $data['tenant_id'] ?? null;

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                match ($data['audience']) {
                    'super_admin' => $query->where('user_type', 'super_admin')->whereNull('tenant_id'),
                    'courier' => $query->whereIn('user_type', ['tenant_admin', 'staff'])->whereNotNull('tenant_id'),
                    'customer' => $query->where('user_type', 'customer')->whereNotNull('tenant_id'),
                    'platform_users', 'all' => $query->whereIn('user_type', ['tenant_admin', 'staff', 'customer'])->whereNotNull('tenant_id'),
                    default => $query->whereRaw('1 = 0'),
                };
            })
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId));
    }

    private function storeAttachments(Request $request): array
    {
        $attachments = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $directory = public_path('uploads/superadmin-message-attachments');
            File::ensureDirectoryExists($directory);
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'url' => asset('uploads/superadmin-message-attachments/'.$filename),
                'path' => 'uploads/superadmin-message-attachments/'.$filename,
            ];
        }

        return $attachments;
    }

    private function mergeAttachments(array $existing, array $new): array
    {
        return collect($existing)->merge($new)->values()->all();
    }

    private function sanitizeBody(string $body): string
    {
        $body = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $body);
        $body = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $body);
        $body = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '$1="#"', $body);

        return strip_tags($body, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><div><span><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><td><th><hr>');
    }

    private function renderTokens(string $content, User $user): string
    {
        $user->loadMissing(['tenant', 'outlet', 'customer']);
        $customer = $user->customer;
        $tenant = $user->tenant;
        $outlet = $user->outlet;
        $address = trim(collect([$user->address_line1, $user->address_line2])->filter()->implode(', '));

        $tokens = [
            'company_name' => optional($tenant)->company_name,
            'courier_name' => optional($tenant)->company_name,
            'courier_email' => optional($tenant)->email,
            'courier_phone' => optional($tenant)->phone,
            'customer_name' => $user->full_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'customer_email' => $user->email,
            'email' => $user->email,
            'phone' => $user->phone,
            'mailbox' => optional($customer)->mailbox ?: $user->mailbox,
            'address' => $address,
            'customer_address' => $address,
            'city' => $user->city,
            'parish' => $user->parish,
            'branch_name' => optional($outlet)->name,
            'branch_address' => optional($outlet)->address,
            'pickup_location' => optional($outlet)->name,
        ];

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn ($matches) => (string) ($tokens[$matches[1]] ?? $matches[0]), $content) ?? $content;
    }
}

