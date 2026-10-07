@extends('layouts.app')

@section('content')
@php
    $editingDraft = isset($draft) ? $draft : null;
    $formAction = $editingDraft ? route('courier.crm.emails.drafts.update', $editingDraft) : route('courier.crm.emails.send');
    $selectedCustomerIds = collect(old('selected_customer_ids', $selectedCustomerIds ?? []))->map(fn ($id) => (int) $id)->filter()->values()->all();
    $selectedTarget = old('target', optional($editingDraft)->target ?: ($selectedCustomerIds ? 'selected' : 'all'));
    $selectedBranch = old('branch_id', optional($editingDraft)->branch_id);
    $selectedGroup = old('group', optional($editingDraft)->email_group);
    $bodyHtml = old('body', optional($editingDraft)->body);
    $mergeTagGroups = [
        'Customer Information' => ['customer_name', 'customer_email', 'phone', 'mailbox', 'address', 'city', 'parish'],
        'Company Information' => ['company_name', 'courier_name', 'courier_email', 'courier_phone'],
        'Branch' => ['branch_name', 'branch_address', 'pickup_location'],
        'Packages & Billing' => ['tracking_number', 'status', 'invoice_number', 'invoice_total', 'package_count'],
    ];
    $addressBlocks = collect($addressTemplates ?? [])->map(function ($address) {
        $label = trim((string) ($address['label'] ?? 'Shipping Address'));
        $nameSuffix = trim((string) ($address['name_suffix'] ?? ''));
        $address2Suffix = trim((string) ($address['address_2_suffix'] ?? ''));
        $marksSuffix = trim((string) ($address['marks_suffix'] ?? ''));
        $nameLine = trim('{{ customer_name }} '.$nameSuffix);
        $mailboxLine = trim('{{ mailbox }} '.$address2Suffix);
        $marksLine = trim('Marks: {{ mailbox }} '.$marksSuffix);
        $lines = collect([
            $nameLine,
            $marksSuffix ? $marksLine : null,
            $address['entrance_no'] ?? null,
            $address['address_1'] ?? null,
            $mailboxLine !== '{{ mailbox }}' ? $mailboxLine : null,
            $address['detailed_address'] ?? null,
            trim(collect([$address['city'] ?? null, $address['state'] ?? null, $address['province'] ?? null, $address['zip_code'] ?? null])->filter()->implode(', ')),
            $address['country'] ?? null,
            $address['number'] ?? null,
            $address['note'] ?? null,
        ])->filter()->map(fn ($line) => e($line))->implode('<br>');

        return [
            'label' => $label,
            'html' => '<div style="margin:12px 0; padding:12px; border:1px solid #d8dee8; border-radius:8px;"><strong>'.e($label).'</strong><br>'.$lines.'</div>',
        ];
    })->values()->all();
@endphp
<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h1 class="h3 mb-1">{{ $editingDraft ? 'Edit Marketing Email Draft' : 'Send Marketing Email' }}</h1>
                            <p class="text-muted mb-0">Save reusable drafts, then send them by email and to customers' notification centers.</p>
                        </div>
                        @if($editingDraft)
                            <span class="badge {{ $editingDraft->status === 'sent' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($editingDraft->status) }}</span>
                        @endif
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @php
                        $bulkEmailStatus = $bulkEmailStatus ?? [
                            'pending' => 0,
                            'processing' => 0,
                            'sent' => 0,
                            'failed' => 0,
                            'last_run_at' => null,
                            'last_result' => null,
                        ];
                    @endphp
                    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-bold">Bulk Email Cron Status</div>
                            <div class="small text-muted">
                                Last run:
                                {{ ! empty($bulkEmailStatus['last_run_at']) ? \Carbon\Carbon::parse($bulkEmailStatus['last_run_at'])->format('M d, Y h:i A') : 'Not recorded yet' }}
                                @if(! empty($bulkEmailStatus['last_result']))
                                    <span class="d-block">{{ $bulkEmailStatus['last_result'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-warning text-dark">Pending {{ number_format($bulkEmailStatus['pending']) }}</span>
                            <span class="badge bg-info text-dark">Processing {{ number_format($bulkEmailStatus['processing']) }}</span>
                            <span class="badge bg-success">Sent {{ number_format($bulkEmailStatus['sent']) }}</span>
                            <span class="badge bg-danger">Failed {{ number_format($bulkEmailStatus['failed']) }}</span>
                        </div>
                    </div>

                    @php
                        $emailQueueItems = $emailQueueItems ?? collect();
                        $emailQueueStatus = $emailQueueStatus ?? request('email_status', 'all');
                        $emailStatusLabels = [
                            'all' => 'All',
                            'pending' => 'Pending',
                            'processing' => 'Processing',
                            'sent' => 'Sent',
                            'failed' => 'Failed',
                        ];
                        $emailStatusBadges = [
                            'pending' => 'bg-warning text-dark',
                            'processing' => 'bg-info text-dark',
                            'sent' => 'bg-success',
                            'failed' => 'bg-danger',
                        ];
                    @endphp
                    <div class="card border mb-4">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-bold">Bulk Email Status Log</div>
                                <div class="small text-muted">Latest queued recipient records by delivery status.</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($emailStatusLabels as $statusValue => $statusLabel)
                                    @php
                                        $statusQuery = array_merge(request()->except('email_queue_page'), ['email_status' => $statusValue]);
                                        $statusButtonClass = $emailQueueStatus === $statusValue ? 'btn-primary' : 'btn-outline-secondary';
                                    @endphp
                                    <a class="btn btn-sm {{ $statusButtonClass }}" href="{{ request()->url() }}?{{ http_build_query($statusQuery) }}">
                                        {{ $statusLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Recipient</th>
                                        <th>Draft</th>
                                        <th>Queued</th>
                                        <th>Processing</th>
                                        <th>Sent</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emailQueueItems as $queueItem)
                                        <tr>
                                            <td><span class="badge {{ $emailStatusBadges[$queueItem->status] ?? 'bg-secondary' }}">{{ ucfirst($queueItem->status) }}</span></td>
                                            <td>
                                                <div class="fw-semibold">{{ optional(optional($queueItem->customer)->user)->full_name ?: optional($queueItem->user)->full_name ?: 'Customer' }}</div>
                                                <div class="small text-muted">{{ $queueItem->to_email }}</div>
                                                @if(optional($queueItem->customer)->mailbox)
                                                    <div class="small text-muted">{{ $queueItem->customer->mailbox }}</div>
                                                @endif
                                            </td>
                                            <td>{{ optional($queueItem->draft)->subject ?: 'Deleted draft' }}</td>
                                            <td class="text-nowrap">{{ optional($queueItem->queued_at)->format('M d, Y h:i A') ?: '-' }}</td>
                                            <td class="text-nowrap">{{ optional($queueItem->processing_at)->format('M d, Y h:i A') ?: '-' }}</td>
                                            <td class="text-nowrap">{{ optional($queueItem->sent_at)->format('M d, Y h:i A') ?: '-' }}</td>
                                            <td class="small text-danger" style="max-width: 260px; white-space: normal;">{{ $queueItem->error_message ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-3">No email queue records found for this status.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(method_exists($emailQueueItems, 'links'))
                            <div class="card-footer bg-white">{{ $emailQueueItems->links('pagination::bootstrap-5') }}</div>
                        @endif
                    </div>

                    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if($editingDraft)
                            @method('PUT')
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Send To</label>
                                <select name="target" id="email-target" class="form-select" required>
                                    <option value="all" {{ $selectedTarget === 'all' ? 'selected' : '' }}>All clients from all branches</option>
                                    <option value="never_shipped" {{ $selectedTarget === 'never_shipped' ? 'selected' : '' }}>Clients who have never shipped</option>
                                    <option value="new_clients" {{ $selectedTarget === 'new_clients' ? 'selected' : '' }}>New clients (last 30 days)</option>
                                    <option value="branch" {{ $selectedTarget === 'branch' ? 'selected' : '' }}>Clients from a specific branch</option>
                                    <option value="group" {{ $selectedTarget === 'group' ? 'selected' : '' }}>Marketing email group</option>
                                    <option value="selected" {{ $selectedTarget === 'selected' ? 'selected' : '' }}>Selected clients only</option>
                                </select>
                            </div>

                            <div class="col-md-6 email-target-branch" style="display: none;">
                                <label class="form-label">Branch</label>
                                <select name="branch_id" class="form-select" data-email-branch>
                                    <option value="">Select a branch</option>
                                    @foreach($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (string) $selectedBranch === (string) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 email-target-group" style="display: none;">
                                <label class="form-label">Marketing Group</label>
                                <select name="group" class="form-select" data-email-group>
                                    <option value="">Select a marketing group</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group }}" {{ $selectedGroup === $group ? 'selected' : '' }}>{{ $group }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 email-target-selected" style="display: none;">
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <label class="form-label mb-0">Selected Clients</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small text-muted" data-selected-customer-count>0 selected</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all-customers>Select All</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-selected-customers>Clear</button>
                                        </div>
                                    </div>
                                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Search selected clients" data-customer-recipient-search>
                                    <div class="row g-2" style="max-height: 260px; overflow:auto;">
                                        @foreach($customers as $customer)
                                            @php
                                                $customerLabel = trim(($customer->mailbox ?: '').' '.(optional($customer->user)->full_name ?: '').' '.(optional($customer->user)->email ?: '').' '.(optional($customer->outlet)->name ?: ''));
                                            @endphp
                                            <div class="col-md-6" data-customer-recipient-row data-search-text="{{ strtolower($customerLabel) }}">
                                                <label class="form-check border rounded bg-white px-3 py-2 h-100">
                                                    <input class="form-check-input me-2" type="checkbox" name="selected_customer_ids[]" value="{{ $customer->id }}" data-selected-customer-checkbox @checked(in_array((int) $customer->id, $selectedCustomerIds, true))>
                                                    <span class="form-check-label">
                                                        <strong>{{ $customer->mailbox }}</strong> - {{ optional($customer->user)->full_name ?: 'Unnamed client' }}
                                                        <span class="d-block small text-muted">{{ optional($customer->user)->email }}{{ optional($customer->outlet)->name ? ' / '.optional($customer->outlet)->name : '' }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-3 bg-white">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <div>
                                            <label class="form-label mb-0">Recipient Preview</label>
                                            <div class="small text-muted" data-recipient-preview-summary>Loading recipients...</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-refresh-recipient-preview>
                                            <i class="fas fa-rotate me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="table-responsive" style="max-height: 260px; overflow:auto;">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Mailbox</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Branch</th>
                                                </tr>
                                            </thead>
                                            <tbody data-recipient-preview-list>
                                                <tr>
                                                    <td colspan="4" class="text-muted">Loading recipients...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <input name="subject" class="form-control" value="{{ old('subject', optional($editingDraft)->subject) }}" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Message Body</label>
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-2 p-2 border rounded bg-light" role="group" aria-label="Message body editor">
                                    <select class="form-select form-select-sm" style="width: 150px;" data-crm-editor-font>
                                        <option value="">Font</option>
                                        <option value="Arial">Arial</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Georgia">Georgia</option>
                                        <option value="Tahoma">Tahoma</option>
                                        <option value="Courier New">Courier New</option>
                                    </select>
                                    <select class="form-select form-select-sm" style="width: 95px;" data-crm-editor-size>
                                        <option value="">Size</option>
                                        <option value="2">12px</option>
                                        <option value="3">14px</option>
                                        <option value="4">16px</option>
                                        <option value="5">18px</option>
                                        <option value="6">24px</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="bold" title="Bold"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="italic" title="Italic"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="underline" title="Underline"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="strikeThrough" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="superscript" title="Superscript">x<sup>2</sup></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="subscript" title="Subscript">x<sub>2</sub></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="justifyLeft" title="Align left"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="justifyCenter" title="Align center"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="justifyRight" title="Align right"><i class="fas fa-align-right"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                                    <label class="btn btn-outline-secondary mb-0" title="Text color">
                                        <i class="fas fa-font"></i><input type="color" value="#0f172a" class="visually-hidden" data-crm-editor-color>
                                    </label>
                                    <label class="btn btn-outline-secondary mb-0" title="Highlight color">
                                        <i class="fas fa-highlighter"></i><input type="color" value="#fff4b8" class="visually-hidden" data-crm-editor-highlight>
                                    </label>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-link title="Link"><i class="fas fa-link"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-image title="Image"><i class="fas fa-image"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-table title="Table"><i class="fas fa-table"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-columns title="Columns"><i class="fas fa-columns"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-pagebreak title="Page break"><i class="fas fa-file-lines"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-smart-text title="Smart Text">Smart Text</button>
                                    <button type="button" class="btn btn-outline-secondary" data-crm-editor-command="removeFormat" title="Clear formatting"><i class="fas fa-eraser"></i></button>
                                    <select class="form-select form-select-sm" style="width: 210px;" data-crm-editor-token>
                                        <option value="">Merge tag</option>
                                        @foreach($mergeTagGroups as $group => $tokens)
                                            <optgroup label="{{ $group }}">
                                                @foreach($tokens as $token)
                                                    <option value="&#123;&#123; {{ $token }} &#125;&#125;">&#123;&#123; {{ $token }} &#125;&#125;</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    <select class="form-select form-select-sm" style="width: 220px;" data-crm-editor-address>
                                        <option value="">Shipping address block</option>
                                        @foreach($addressBlocks as $index => $block)
                                            <option value="{{ $index }}">{{ $block['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea name="body" id="crm-email-body" class="d-none" required>{{ $bodyHtml }}</textarea>
                                <div id="crm-email-editor" class="form-control" contenteditable="true" style="min-height: 260px; overflow:auto;">{!! $bodyHtml !!}</div>
                                <div class="form-text">Customers receive this rich message by email and in their in-app notification center when sent.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Attach Documents</label>
                                <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp">
                                <div class="form-text">Attach customs forms, authorization forms, onboarding forms, or marketing documents.</div>
                                @if(! empty(optional($editingDraft)->attachments_json))
                                    <div class="small text-muted mt-2">Saved: {{ collect($editingDraft->attachments_json)->pluck('name')->filter()->implode(', ') }}</div>
                                @endif
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2 pt-3">
                                <button class="btn btn-outline-primary" name="action" value="save" type="submit"><i class="fas fa-save me-1"></i>Save Draft</button>
                                @if($editingDraft)
                                    <button class="btn btn-outline-primary" name="action" value="save_new" type="submit"><i class="fas fa-copy me-1"></i>Save as New Draft</button>
                                @endif
                                <button class="btn btn-primary" name="action" value="send" type="submit"><i class="fas fa-paper-plane me-1"></i>Queue Email & Notification</button>
                                <a href="{{ route('courier.crm.emails.create') }}" class="btn btn-outline-secondary">New Draft</a>
                                <a href="{{ route('courier.customers.index') }}" class="btn btn-outline-secondary">Back to Customers</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-1">Saved Drafts</h2>
                    <p class="text-muted small mb-3">Each saved item is stored separately. Open any draft, edit it, or save it as another draft.</p>
                    @forelse($drafts as $savedDraft)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-bold">{{ $savedDraft->subject }}</div>
                                    <div class="text-muted small">
                                        {{ str_replace('_', ' ', ucfirst($savedDraft->target)) }}
                                        @if($savedDraft->branch)
                                            - {{ $savedDraft->branch->name }}
                                        @elseif($savedDraft->email_group)
                                            - {{ $savedDraft->email_group }}
                                        @elseif($savedDraft->target === 'selected')
                                            - {{ count($savedDraft->target_customer_ids_json ?? []) }} selected client(s)
                                        @endif
                                    </div>
                                </div>
                                @if(($savedDraft->pending_queue_count ?? 0) > 0)
                                    <span class="badge bg-warning text-dark">Sending</span>
                                @else
                                    <span class="badge {{ $savedDraft->status === 'sent' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($savedDraft->status) }}</span>
                                @endif
                            </div>
                            <div class="text-muted small mt-2">
                                Updated {{ $savedDraft->updated_at->format('M d, Y h:i A') }}
                                @if($savedDraft->sent_at)
                                    <br>Sent {{ $savedDraft->sent_count }} email(s), {{ $savedDraft->notification_count }} notification(s)
                                @endif
                                @if(($savedDraft->queued_count ?? 0) > 0)
                                    <br>
                                    Queue:
                                    {{ number_format($savedDraft->sent_queue_count ?? 0) }} sent,
                                    {{ number_format($savedDraft->failed_queue_count ?? 0) }} failed,
                                    {{ number_format($savedDraft->pending_queue_count ?? 0) }} pending
                                @endif
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('courier.crm.emails.drafts.edit', $savedDraft) }}">Open</a>
                                <form method="POST" action="{{ route('courier.crm.emails.drafts.send', $savedDraft) }}" onsubmit="return confirm('Queue this draft to the selected clients by email and notification?')">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" @disabled(($savedDraft->pending_queue_count ?? 0) > 0 || ($savedDraft->sent_queue_count ?? 0) > 0)>Queue</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No drafts saved yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const crmAddressBlocks = @json($addressBlocks);
    const recipientPreviewUrl = @json(route('courier.crm.emails.recipients.preview'));
    const csrfRefreshUrl = @json(route('courier.csrf-token'));

    document.querySelectorAll('form[action*="/courier/crm/emails"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.csrfRefreshed === '1') {
                form.dataset.csrfRefreshed = '';
                return;
            }

            event.preventDefault();
            const submitter = event.submitter || document.activeElement;

            fetch(csrfRefreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => {
                    if (!response.ok) throw new Error('Unable to refresh form token.');
                    return response.json();
                })
                .then(data => {
                    if (data.token) {
                        const tokenField = form.querySelector('input[name="_token"]');
                        if (tokenField) tokenField.value = data.token;
                    }
                })
                .catch(() => {})
                .finally(() => {
                    form.dataset.csrfRefreshed = '1';
                    if (submitter && typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitter);
                    } else {
                        form.submit();
                    }
                });
        });
    });

    function updateEmailTargetFields() {
        const target = document.getElementById('email-target').value;
        document.querySelectorAll('.email-target-branch, .email-target-group, .email-target-selected').forEach(el => el.style.display = 'none');
        if (target === 'branch') {
            document.querySelector('.email-target-branch').style.display = 'block';
        } else if (target === 'group') {
            document.querySelector('.email-target-group').style.display = 'block';
        } else if (target === 'selected') {
            document.querySelector('.email-target-selected').style.display = 'block';
        }
    }
    document.getElementById('email-target').addEventListener('change', function () {
        updateEmailTargetFields();
        scheduleRecipientPreview();
    });
    updateEmailTargetFields();

    const selectedCustomerCheckboxes = Array.from(document.querySelectorAll('[data-selected-customer-checkbox]'));
    const selectedCustomerCount = document.querySelector('[data-selected-customer-count]');
    const updateSelectedCustomerCount = () => {
        const count = selectedCustomerCheckboxes.filter(checkbox => checkbox.checked).length;
        if (selectedCustomerCount) selectedCustomerCount.textContent = count + ' selected';
    };
    selectedCustomerCheckboxes.forEach(checkbox => checkbox.addEventListener('change', function () {
        updateSelectedCustomerCount();
        scheduleRecipientPreview();
    }));
    document.querySelector('[data-select-all-customers]')?.addEventListener('click', function () {
        selectedCustomerCheckboxes.forEach(checkbox => {
            if (checkbox.closest('[data-customer-recipient-row]')?.style.display !== 'none') checkbox.checked = true;
        });
        updateSelectedCustomerCount();
        scheduleRecipientPreview();
    });
    document.querySelector('[data-clear-selected-customers]')?.addEventListener('click', function () {
        selectedCustomerCheckboxes.forEach(checkbox => checkbox.checked = false);
        updateSelectedCustomerCount();
        scheduleRecipientPreview();
    });
    document.querySelector('[data-customer-recipient-search]')?.addEventListener('input', function (event) {
        const term = event.target.value.trim().toLowerCase();
        document.querySelectorAll('[data-customer-recipient-row]').forEach(function (row) {
            row.style.display = !term || row.dataset.searchText.includes(term) ? '' : 'none';
        });
    });
    updateSelectedCustomerCount();

    const recipientPreviewSummary = document.querySelector('[data-recipient-preview-summary]');
    const recipientPreviewList = document.querySelector('[data-recipient-preview-list]');
    const emailBranchField = document.querySelector('[data-email-branch]');
    const emailGroupField = document.querySelector('[data-email-group]');
    let recipientPreviewTimer = null;
    let recipientPreviewController = null;

    function escapePreviewText(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character];
        });
    }

    function recipientPreviewParams() {
        const params = new URLSearchParams();
        const target = document.getElementById('email-target').value;
        params.append('target', target);
        if (emailBranchField?.value) params.append('branch_id', emailBranchField.value);
        if (emailGroupField?.value) params.append('group', emailGroupField.value);
        selectedCustomerCheckboxes
            .filter(checkbox => checkbox.checked)
            .forEach(checkbox => params.append('selected_customer_ids[]', checkbox.value));

        return params;
    }

    function renderRecipientPreview(data) {
        const count = Number(data.count || 0);
        const shown = Number(data.shown || 0);
        if (recipientPreviewSummary) {
            recipientPreviewSummary.textContent = data.message || (count === 1 ? '1 recipient will receive this email.' : count + ' recipients will receive this email.');
        }

        if (!recipientPreviewList) return;
        if (!Array.isArray(data.customers) || data.customers.length === 0) {
            recipientPreviewList.innerHTML = '<tr><td colspan="4" class="text-muted">No recipients match this selection.</td></tr>';
            return;
        }

        recipientPreviewList.innerHTML = data.customers.map(function (customer) {
            return '<tr>'
                + '<td><strong>' + escapePreviewText(customer.mailbox) + '</strong></td>'
                + '<td>' + escapePreviewText(customer.name) + '</td>'
                + '<td>' + escapePreviewText(customer.email) + '</td>'
                + '<td>' + escapePreviewText(customer.branch) + '</td>'
                + '</tr>';
        }).join('');

        if (data.message && shown < count) {
            recipientPreviewList.insertAdjacentHTML('beforeend', '<tr><td colspan="4" class="text-muted">' + escapePreviewText(data.message) + '</td></tr>');
        }
    }

    function loadRecipientPreview() {
        if (!recipientPreviewSummary || !recipientPreviewList) return;
        if (recipientPreviewController) recipientPreviewController.abort();

        recipientPreviewController = new AbortController();
        recipientPreviewSummary.textContent = 'Loading recipients...';

        fetch(recipientPreviewUrl + '?' + recipientPreviewParams().toString(), {
            headers: { 'Accept': 'application/json' },
            signal: recipientPreviewController.signal,
        })
            .then(response => {
                if (!response.ok) throw new Error('Unable to load recipients.');
                return response.json();
            })
            .then(renderRecipientPreview)
            .catch(error => {
                if (error.name === 'AbortError') return;
                recipientPreviewSummary.textContent = 'Unable to load recipient preview.';
                recipientPreviewList.innerHTML = '<tr><td colspan="4" class="text-danger">Please refresh the page and try again.</td></tr>';
            });
    }

    function scheduleRecipientPreview() {
        window.clearTimeout(recipientPreviewTimer);
        recipientPreviewTimer = window.setTimeout(loadRecipientPreview, 200);
    }

    emailBranchField?.addEventListener('change', scheduleRecipientPreview);
    emailGroupField?.addEventListener('change', scheduleRecipientPreview);
    document.querySelector('[data-refresh-recipient-preview]')?.addEventListener('click', loadRecipientPreview);
    scheduleRecipientPreview();

    const crmEditor = document.getElementById('crm-email-editor');
    const crmBodyField = document.getElementById('crm-email-body');

    function syncCrmEditor() {
        if (crmEditor && crmBodyField) {
            crmBodyField.value = crmEditor.innerHTML.trim();
        }
    }

    if (crmEditor && crmBodyField) {
        document.querySelectorAll('[data-crm-editor-command]').forEach(function (button) {
            button.addEventListener('click', function () {
                crmEditor.focus();
                document.execCommand(button.dataset.crmEditorCommand, false, null);
                syncCrmEditor();
            });
        });

        document.querySelector('[data-crm-editor-font]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            crmEditor.focus();
            document.execCommand('fontName', false, event.target.value);
            event.target.value = '';
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-size]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            crmEditor.focus();
            document.execCommand('fontSize', false, event.target.value);
            event.target.value = '';
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-color]')?.addEventListener('input', function (event) {
            crmEditor.focus();
            document.execCommand('foreColor', false, event.target.value);
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-highlight]')?.addEventListener('input', function (event) {
            crmEditor.focus();
            document.execCommand('hiliteColor', false, event.target.value);
            syncCrmEditor();
        });

        const linkButton = document.querySelector('[data-crm-editor-link]');
        if (linkButton) {
            linkButton.addEventListener('click', function () {
                const url = prompt('Enter link URL');
                if (!url) return;
                crmEditor.focus();
                document.execCommand('createLink', false, url);
                syncCrmEditor();
            });
        }

        const imageButton = document.querySelector('[data-crm-editor-image]');
        if (imageButton) {
            imageButton.addEventListener('click', function () {
                const url = prompt('Enter image URL');
                if (!url) return;
                crmEditor.focus();
                document.execCommand('insertImage', false, url);
                syncCrmEditor();
            });
        }

        document.querySelector('[data-crm-editor-table]')?.addEventListener('click', function () {
            crmEditor.focus();
            document.execCommand('insertHTML', false, '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse; margin:14px 0;"><tbody><tr><td style="padding:10px; border:1px solid #dbe3ef; font-weight:700;">Label</td><td style="padding:10px; border:1px solid #dbe3ef;">Value</td></tr><tr><td style="padding:10px; border:1px solid #dbe3ef; font-weight:700;">Label</td><td style="padding:10px; border:1px solid #dbe3ef;">Value</td></tr></tbody></table>');
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-columns]')?.addEventListener('click', function () {
            crmEditor.focus();
            document.execCommand('insertHTML', false, '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse; margin:14px 0;"><tbody><tr><td width="50%" valign="top" style="width:50%; padding:12px; border:1px solid #dbe3ef;">Column one</td><td width="50%" valign="top" style="width:50%; padding:12px; border:1px solid #dbe3ef;">Column two</td></tr></tbody></table>');
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-pagebreak]')?.addEventListener('click', function () {
            crmEditor.focus();
            document.execCommand('insertHTML', false, '<hr style="page-break-after:always;">');
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-smart-text]')?.addEventListener('click', function () {
            crmEditor.focus();
            document.execCommand('insertHTML', false, '<p>Hi &#123;&#123; customer_name &#125;&#125;,</p><p>We are sharing this update from &#123;&#123; company_name &#125;&#125; so you have the latest shipping information for your account. Please review the details below and contact us if you need any help.</p>');
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-token]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            crmEditor.focus();
            document.execCommand('insertText', false, event.target.value);
            event.target.value = '';
            syncCrmEditor();
        });

        document.querySelector('[data-crm-editor-address]')?.addEventListener('change', function (event) {
            if (event.target.value === '') return;
            const block = crmAddressBlocks[event.target.value];
            if (!block || !block.html) return;
            crmEditor.focus();
            document.execCommand('insertHTML', false, block.html);
            event.target.value = '';
            syncCrmEditor();
        });

        crmEditor.addEventListener('input', syncCrmEditor);
        const form = crmEditor.closest('form');
        if (form) {
            form.addEventListener('submit', syncCrmEditor);
        }
        syncCrmEditor();
    }
</script>
@endsection

