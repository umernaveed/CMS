<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrmEmailDraft;
use App\Models\CrmEmailQueueItem;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\SystemSetting;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourierEmailController extends Controller
{
    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        [$outlets, $groups, $drafts, $addressTemplates, $customers] = $this->formData($tenantId);
        $selectedCustomerIds = $this->selectedCustomerIds($request->input('selected_customer_ids', []), $tenantId);
        $bulkEmailStatus = $this->bulkEmailStatus($tenantId);
        [$emailQueueItems, $emailQueueStatus] = $this->bulkEmailQueueItems($tenantId, $request);

        return view('courier.crm.emails.create', compact('outlets', 'groups', 'drafts', 'addressTemplates', 'customers', 'selectedCustomerIds', 'bulkEmailStatus', 'emailQueueItems', 'emailQueueStatus'));
    }

    public function edit(Request $request, CrmEmailDraft $draft)
    {
        $this->authorizeDraft($draft);

        [$outlets, $groups, $drafts, $addressTemplates, $customers] = $this->formData(auth()->user()->tenant_id);
        $selectedCustomerIds = collect($draft->target_customer_ids_json ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
        $bulkEmailStatus = $this->bulkEmailStatus(auth()->user()->tenant_id);
        [$emailQueueItems, $emailQueueStatus] = $this->bulkEmailQueueItems(auth()->user()->tenant_id, $request);

        return view('courier.crm.emails.create', compact('outlets', 'groups', 'drafts', 'addressTemplates', 'customers', 'selectedCustomerIds', 'draft', 'bulkEmailStatus', 'emailQueueItems', 'emailQueueStatus'));
    }

    public function store(Request $request)
    {
        $data = $this->validateEmail($request);
        $data['attachments_json'] = $this->storeAttachments($request);

        $draft = CrmEmailDraft::create($this->draftPayload($data));

        if ($request->input('action') === 'save') {
            return redirect()
                ->route('courier.crm.emails.drafts.edit', $draft)
                ->with('success', 'Email draft saved for future use.');
        }

        return $this->sendDraft($draft);
    }

    public function update(Request $request, CrmEmailDraft $draft)
    {
        $this->authorizeDraft($draft);

        $data = $this->validateEmail($request);
        $data['attachments_json'] = $this->mergeAttachments($draft->attachments_json ?? [], $this->storeAttachments($request));
        if ($request->input('action') === 'save_new') {
            $newDraft = CrmEmailDraft::create($this->draftPayload($data));

            return redirect()
                ->route('courier.crm.emails.drafts.edit', $newDraft)
                ->with('success', 'Email saved as a new draft.');
        }

        $draft->update($this->draftPayload($data));

        if ($request->input('action') === 'save') {
            return redirect()
                ->route('courier.crm.emails.drafts.edit', $draft)
                ->with('success', 'Email draft updated.');
        }

        return $this->sendDraft($draft);
    }

    public function sendSaved(CrmEmailDraft $draft)
    {
        $this->authorizeDraft($draft);

        return $this->sendDraft($draft);
    }

    public function previewRecipients(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $request->validate([
            'target' => 'required|in:all,never_shipped,new_clients,branch,group,selected',
            'branch_id' => [
                'nullable',
                Rule::exists('outlets', 'id')->where('tenant_id', $tenantId),
            ],
            'group' => 'nullable|string|max:100',
            'selected_customer_ids' => 'nullable|array',
            'selected_customer_ids.*' => [
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
        ]);

        if ($data['target'] === 'branch' && empty($data['branch_id'])) {
            return response()->json([
                'count' => 0,
                'customers' => [],
                'message' => 'Select a branch to preview recipients.',
            ]);
        }

        if ($data['target'] === 'group' && empty($data['group'])) {
            return response()->json([
                'count' => 0,
                'customers' => [],
                'message' => 'Select a marketing group to preview recipients.',
            ]);
        }

        $query = $this->targetCustomers($tenantId, [
            'target' => $data['target'],
            'branch_id' => $data['branch_id'] ?? null,
            'group' => $data['group'] ?? null,
            'selected_customer_ids' => $data['selected_customer_ids'] ?? [],
        ])->with(['user', 'outlet'])->orderBy('mailbox');

        $count = (clone $query)->count();
        $customers = $query->limit(300)->get()->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'mailbox' => $customer->mailbox,
            'name' => optional($customer->user)->full_name ?: 'Unnamed client',
            'email' => optional($customer->user)->email,
            'branch' => optional($customer->outlet)->name,
        ])->values();

        return response()->json([
            'count' => $count,
            'shown' => $customers->count(),
            'customers' => $customers,
            'message' => $count > $customers->count() ? 'Showing first '.$customers->count().' of '.$count.' recipients.' : null,
        ]);
    }

    private function validateEmail(Request $request): array
    {
        return $request->validate([
            'target' => 'required|in:all,never_shipped,new_clients,branch,group,selected',
            'branch_id' => [
                'required_if:target,branch',
                'nullable',
                Rule::exists('outlets', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'group' => 'required_if:target,group|nullable|string|max:100',
            'selected_customer_ids' => 'required_if:target,selected|array',
            'selected_customer_ids.*' => [
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)),
            ],
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp|max:8192',
        ]);
    }

    private function draftPayload(array $data): array
    {
        return [
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
            'target' => $data['target'],
            'branch_id' => $data['target'] === 'branch' ? ($data['branch_id'] ?? null) : null,
            'email_group' => $data['target'] === 'group' ? ($data['group'] ?? null) : null,
            'target_customer_ids_json' => $data['target'] === 'selected' ? collect($data['selected_customer_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all() : null,
            'subject' => $data['subject'],
            'body' => $this->sanitizeBody($data['body']),
            'attachments_json' => $data['attachments_json'] ?? [],
            'status' => 'draft',
        ];
    }

    private function sendDraft(CrmEmailDraft $draft)
    {
        $this->authorizeDraft($draft);

        $tenantId = $draft->tenant_id;
        $data = [
            'target' => $draft->target,
            'branch_id' => $draft->branch_id,
            'group' => $draft->email_group,
            'selected_customer_ids' => $draft->target_customer_ids_json ?? [],
            'subject' => $draft->subject,
            'body' => $draft->body,
            'attachments_json' => $draft->attachments_json ?? [],
        ];

        if ($this->draftHasOpenQueue($draft)) {
            return redirect()
                ->route('courier.crm.emails.drafts.edit', $draft)
                ->with('success', 'This marketing email is already queued. Cron will continue sending it in batches.');
        }

        if ($this->draftHasSentQueue($draft)) {
            return redirect()
                ->route('courier.crm.emails.drafts.edit', $draft)
                ->with('success', 'This marketing email was already sent. Save it as a new draft before sending it again.');
        }

        CrmEmailQueueItem::where('crm_email_draft_id', $draft->id)->delete();

        $queuedCount = 0;
        $this->targetCustomers($tenantId, $data)->with('user')->orderBy('id')->chunkById(200, function ($batch) use (&$queuedCount, $tenantId, $draft) {
            $rows = [];
            foreach ($batch as $customer) {
                $user = $customer->user;
                $recipientEmail = optional($user)->email;
                if (! $recipientEmail) {
                    continue;
                }

                $rows[] = [
                    'tenant_id' => $tenantId,
                    'crm_email_draft_id' => $draft->id,
                    'customer_id' => $customer->id,
                    'user_id' => optional($user)->id,
                    'to_email' => $recipientEmail,
                    'status' => 'pending',
                    'queued_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('crm_email_queue_items')->insertOrIgnore($rows);
                $queuedCount += count($rows);
            }
        });

        $draft->update([
            'status' => 'draft',
            'sent_count' => 0,
            'failed_count' => 0,
            'notification_count' => 0,
            'sent_at' => null,
        ]);

        $message = "Marketing email queued for {$queuedCount} recipient".($queuedCount === 1 ? '' : 's').'. Cron will send it in configured batches.';

        return redirect()->route('courier.crm.emails.drafts.edit', $draft)->with('success', $message);
    }

    private function draftHasOpenQueue(CrmEmailDraft $draft): bool
    {
        return CrmEmailQueueItem::where('crm_email_draft_id', $draft->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    private function draftHasSentQueue(CrmEmailDraft $draft): bool
    {
        return CrmEmailQueueItem::where('crm_email_draft_id', $draft->id)
            ->where('status', 'sent')
            ->exists();
    }

    private function bulkEmailStatus(int $tenantId): array
    {
        $counts = CrmEmailQueueItem::where('tenant_id', $tenantId)
            ->selectRaw("SUM(status = 'pending') as pending_count, SUM(status = 'processing') as processing_count, SUM(status = 'sent') as sent_count, SUM(status = 'failed') as failed_count")
            ->first();

        return [
            'pending' => (int) ($counts->pending_count ?? 0),
            'processing' => (int) ($counts->processing_count ?? 0),
            'sent' => (int) ($counts->sent_count ?? 0),
            'failed' => (int) ($counts->failed_count ?? 0),
            'last_run_at' => SystemSetting::where('key', 'crm_bulk_email_cron_last_run_at')->value('value'),
            'last_result' => SystemSetting::where('key', 'crm_bulk_email_cron_last_result')->value('value'),
        ];
    }

    private function bulkEmailQueueItems(int $tenantId, Request $request): array
    {
        $status = $request->input('email_status', 'all');
        $allowed = ['all', 'pending', 'processing', 'sent', 'failed'];
        $status = in_array($status, $allowed, true) ? $status : 'all';

        $items = CrmEmailQueueItem::with(['draft', 'customer.user', 'user'])
            ->where('tenant_id', $tenantId)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(50, ['*'], 'email_queue_page')
            ->withQueryString();

        return [$items, $status];
    }

    private function targetCustomers(int $tenantId, array $data)
    {
        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('user', function ($query) {
                $query->whereNotNull('email')->where('email', '<>', '');
            });

        if ($data['target'] === 'never_shipped') {
            $customers->whereDoesntHave('packages');
        }

        if ($data['target'] === 'new_clients') {
            $customers->whereDate('created_at', '>=', now()->subDays(30));
        }

        if ($data['target'] === 'branch' && ! empty($data['branch_id'])) {
            $customers->where('outlet_id', $data['branch_id']);
        }

        if ($data['target'] === 'group' && ! empty($data['group'])) {
            $customers->where('email_group', $data['group']);
        }

        if ($data['target'] === 'selected') {
            $ids = collect($data['selected_customer_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
            $customers->whereIn('id', $ids ?: [0]);
        }

        return $customers;
    }

    private function formData(int $tenantId): array
    {
        $outlets = Outlet::where('tenant_id', $tenantId)->orderBy('name')->get();
        $groups = Customer::where('tenant_id', $tenantId)
            ->whereNotNull('email_group')
            ->where('email_group', '<>', '')
            ->distinct()
            ->orderBy('email_group')
            ->pluck('email_group');
        $drafts = CrmEmailDraft::with('branch')
            ->withCount([
                'queueItems as queued_count',
                'queueItems as pending_queue_count' => fn ($query) => $query->whereIn('status', ['pending', 'processing']),
                'queueItems as sent_queue_count' => fn ($query) => $query->where('status', 'sent'),
                'queueItems as failed_queue_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(20)
            ->get();
        $customers = Customer::with(['user', 'outlet'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->whereNotNull('email')->where('email', '<>', ''))
            ->orderBy('mailbox')
            ->get();

        return [$outlets, $groups, $drafts, $this->addressTemplates($tenantId), $customers];
    }

    private function addressTemplates(int $tenantId): array
    {
        $configured = TenantSetting::getValue($tenantId, 'warehouse_addresses');
        $decoded = $configured ? json_decode($configured, true) : null;

        if (is_array($decoded) && $decoded !== []) {
            return collect($decoded)
                ->filter(fn ($address) => ! empty($address['is_active']))
                ->values()
                ->all();
        }

        return [
            [
                'label' => 'USA Air',
                'country' => 'United States',
                'name_suffix' => 'OLOG',
                'address_1' => TenantSetting::getValue($tenantId, 'us_warehouse_address_1', '1807 SW 31st Ave'),
                'address_2_suffix' => '',
                'city' => TenantSetting::getValue($tenantId, 'us_warehouse_city', 'Hallandale'),
                'state' => TenantSetting::getValue($tenantId, 'us_warehouse_state', 'Florida'),
                'zip_code' => TenantSetting::getValue($tenantId, 'us_warehouse_zip', '33009'),
                'marks_suffix' => 'AIR',
                'is_active' => true,
            ],
            [
                'label' => 'USA Sea',
                'country' => 'United States',
                'name_suffix' => 'OLOG',
                'address_1' => TenantSetting::getValue($tenantId, 'us_warehouse_address_1', '1807 SW 31st Ave'),
                'address_2_suffix' => 'SEA',
                'city' => TenantSetting::getValue($tenantId, 'us_warehouse_city', 'Hallandale'),
                'state' => TenantSetting::getValue($tenantId, 'us_warehouse_state', 'Florida'),
                'zip_code' => TenantSetting::getValue($tenantId, 'us_warehouse_zip', '33009'),
                'marks_suffix' => 'SEA',
                'is_active' => true,
            ],
            [
                'label' => 'China Air',
                'country' => 'China',
                'entrance_no' => TenantSetting::getValue($tenantId, 'china_entrance_no', ''),
                'province' => TenantSetting::getValue($tenantId, 'china_province', ''),
                'city' => TenantSetting::getValue($tenantId, 'china_city', ''),
                'detailed_address' => TenantSetting::getValue($tenantId, 'china_detailed_address', ''),
                'number' => TenantSetting::getValue($tenantId, 'china_number', ''),
                'zip_code' => TenantSetting::getValue($tenantId, 'china_zip', ''),
                'marks_suffix' => 'AIR',
                'is_active' => true,
            ],
            [
                'label' => 'China Sea',
                'country' => 'China',
                'entrance_no' => TenantSetting::getValue($tenantId, 'china_entrance_no', ''),
                'province' => TenantSetting::getValue($tenantId, 'china_province', ''),
                'city' => TenantSetting::getValue($tenantId, 'china_city', ''),
                'detailed_address' => TenantSetting::getValue($tenantId, 'china_detailed_address', ''),
                'number' => TenantSetting::getValue($tenantId, 'china_number', ''),
                'zip_code' => TenantSetting::getValue($tenantId, 'china_zip', ''),
                'marks_suffix' => 'SEA',
                'is_active' => true,
            ],
        ];
    }

    private function selectedCustomerIds($value, int $tenantId): array
    {
        $ids = collect(is_array($value) ? $value : [$value])
            ->flatMap(fn ($item) => is_array($item) ? $item : explode(',', (string) $item))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
    private function authorizeDraft(CrmEmailDraft $draft): void
    {
        abort_unless($draft->tenant_id === auth()->user()->tenant_id, 404);
    }

    private function sanitizeBody(string $body): string
    {
        $body = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $body);
        $body = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $body);
        $body = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '$1="#"', $body);
        $body = strip_tags($body, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><div><span><h1><h2><h3><h4><h5><h6><blockquote><table><thead><tbody><tr><td><th><hr>');

        return trim($body);
    }

    private function storeAttachments(Request $request): array
    {
        $attachments = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $directory = public_path('uploads/crm-email-attachments/'.auth()->user()->tenant_id);
            File::ensureDirectoryExists($directory);
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'url' => asset('uploads/crm-email-attachments/'.auth()->user()->tenant_id.'/'.$filename),
                'path' => 'uploads/crm-email-attachments/'.auth()->user()->tenant_id.'/'.$filename,
            ];
        }

        return $attachments;
    }

    private function mergeAttachments(array $existing, array $new): array
    {
        return collect($existing)->merge($new)->values()->all();
    }

    private function renderTokens(string $content, Customer $customer): string
    {
        $customer->loadMissing(['user', 'tenant', 'outlet']);
        $tokens = [
            'company_name' => optional($customer->tenant)->company_name,
            'courier_name' => optional($customer->tenant)->company_name,
            'courier_email' => optional($customer->tenant)->email,
            'courier_phone' => optional($customer->tenant)->phone,
            'customer_name' => optional($customer->user)->full_name,
            'first_name' => optional($customer->user)->first_name,
            'last_name' => optional($customer->user)->last_name,
            'customer_email' => optional($customer->user)->email,
            'email' => optional($customer->user)->email,
            'phone' => optional($customer->user)->phone,
            'address' => trim(collect([optional($customer->user)->address_line1, optional($customer->user)->address_line2])->filter()->implode(', ')),
            'customer_address' => trim(collect([optional($customer->user)->address_line1, optional($customer->user)->address_line2])->filter()->implode(', ')),
            'city' => optional($customer->user)->city,
            'parish' => optional($customer->user)->parish,
            'mailbox' => $customer->mailbox,
            'branch_name' => optional($customer->outlet)->name,
            'branch_address' => optional($customer->outlet)->address,
            'pickup_location' => optional($customer->outlet)->name,
        ];

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn ($matches) => (string) ($tokens[$matches[1]] ?? $matches[0]), $content) ?? $content;
    }
}
