@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">{{ optional($customer->user)->full_name }}</h1><p class="text-muted mb-0">Mailbox {{ $customer->mailbox }}</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end"><form method="POST" action="{{ route('courier.customers.welcome-email', $customer) }}" data-confirm="Resend the welcome email to this client?">@csrf<button class="btn btn-outline-success">Resend Welcome</button></form><form method="POST" action="{{ route('courier.customers.password-reset', $customer) }}" data-confirm="Send a password reset email to this client?">@csrf<button class="btn btn-outline-primary">Send Reset Email</button></form><a href="{{ route('courier.customers.edit', $customer) }}" class="btn btn-primary">Edit</a><form method="POST" action="{{ route('courier.customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form></div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Profile</h2>
            <div class="mb-2 text-muted">Email</div><div class="mb-3">{{ optional($customer->user)->email }}</div>
            <div class="mb-2 text-muted">Phone</div><div class="mb-3">{{ optional($customer->user)->phone ?: '-' }}</div>
            <div class="mb-2 text-muted">TRN/SSN</div><div class="mb-3">{{ optional($customer->user)->trn ?: '-' }}</div>
            <div class="mb-2 text-muted">Marketing Group</div><div class="mb-3">{{ $customer->email_group ?: '-' }}</div>
            <div class="mb-2 text-muted">Account Type</div><div class="mb-3">{{ ucfirst($customer->account_type ?: 'personal') }}</div>
            <div class="mb-2 text-muted">Created</div><div class="mb-3">{{ optional($customer->created_at)->format('M d, Y h:i A') ?: '-' }}</div>
            <div class="mb-2 text-muted">Account Age</div><div class="mb-3">{{ $customer->created_at ? $customer->created_at->diffForHumans(null, true).' old' : '-' }}</div>
            <div class="mb-2 text-muted">Pricing Tier</div><div class="mb-3">{{ optional($customer->pricingTier)->name ?: 'Default' }}</div>
            @if($customer->account_type === 'business')
                <div class="mb-2 text-muted">Company</div><div class="mb-3">{{ $customer->company_name ?: '-' }}</div>
                <div class="mb-2 text-muted">Company Registration</div><div class="mb-3">{{ $customer->company_registration ?: '-' }}</div>
                <div class="mb-2 text-muted">Representative Role</div><div class="mb-3">{{ $customer->representative_role ?: '-' }}</div>
            @endif
                @php
                    $registrationDocuments = is_array($customer->registration_documents) ? $customer->registration_documents : [];
                    if ($customer->business_document_path && empty($registrationDocuments['business_documents'])) {
                        $registrationDocuments['business_documents'][] = [
                            'path' => $customer->business_document_path,
                            'original_name' => $customer->business_document_original_name ?: 'Business Registration',
                        ];
                    }
                    $registrationDocumentLabels = [
                        'identification_documents' => 'Identification',
                        'proof_of_address_documents' => 'Proof of address',
                        'trn_documents' => 'TRN',
                        'business_documents' => 'Business Registration',
                        'authorization_documents' => 'Authorization',
                        'other_documents' => 'Other',
                    ];
                @endphp
                <div class="mb-2 text-muted">Registration Documents</div>
                <div class="mb-3 d-flex flex-column gap-2">
                    @foreach($registrationDocumentLabels as $field => $label)
                        <div>
                            <div class="small text-muted">{{ $label }}</div>
                            @forelse(($registrationDocuments[$field] ?? []) as $index => $document)
                                @php($name = is_array($document) ? ($document['original_name'] ?? 'Download document') : basename((string) $document))
                                <a class="btn btn-sm btn-outline-primary mt-1" href="{{ route('courier.customers.registration-documents.download', [$customer, $field, $index]) }}">{{ $name }}</a>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('courier.customers.registration-documents.upload', $customer) }}" enctype="multipart/form-data" class="border rounded p-3 mb-3">
                    @csrf
                    <label class="form-label small fw-semibold">Attach sensitive documents</label>
                    <select name="document_type" class="form-select form-select-sm mb-2" required>
                        @foreach($registrationDocumentLabels as $field => $label)
                            <option value="{{ $field }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="file" name="documents[]" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" multiple required>
                    <button class="btn btn-sm btn-primary w-100">Upload Documents</button>
                </form>
            <div class="mb-2 text-muted">Balance Due</div><div class="mb-3 fw-bold">JMD {{ number_format($customer->balance_due ?? 0, 2) }}</div>
            <div class="mb-2 text-muted">Cash Wallet Balance</div><div class="mb-3 fw-bold text-success">JMD {{ number_format($walletBalance, 2) }}</div>
            <div class="mb-2 text-muted">Reward Points</div><div class="mb-3 fw-bold">{{ number_format($rewardPoints, 2) }} points</div>
            <div class="mb-2 text-muted">Address</div><div>{{ optional($customer->user)->address_line1 ?: '-' }}<br>{{ optional($customer->user)->city }} {{ optional($customer->user)->parish }}</div>
            <hr>
            <form method="POST" action="{{ route('courier.customers.wallet-credit', $customer) }}" class="row g-2">
                @csrf
                <div class="col-12"><label class="form-label">Add Funds Directly to Cash Wallet (JMD)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount JMD" required></div>
                <div class="col-12"><textarea name="description" rows="2" class="form-control" placeholder="Note or reason"></textarea></div>
                <div class="col-12"><button class="btn btn-outline-success w-100">Add Credit</button></div>
            </form>
            <hr>
            <form method="POST" action="{{ route('courier.customers.wallet-balance', $customer) }}" class="row g-2" data-confirm="Set this customer's cash wallet balance to the entered amount? This creates an approved adjustment transaction.">
                @csrf
                <div class="col-12"><label class="form-label">Set Cash Wallet Balance (JMD)</label><input type="number" step="0.01" min="0" name="balance" class="form-control @error('balance') is-invalid @enderror" value="{{ old('balance', number_format($walletBalance, 2, '.', '')) }}" required></div>
                @error('balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="col-12"><textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="Required reason for adjustment" required>{{ old('description') }}</textarea></div>
                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="col-12"><button class="btn btn-outline-warning w-100">Set Balance</button></div>
            </form>
        </div>
        @php($pendingTopUps = $customer->walletTransactions->where('status', 'pending')->filter(fn ($transaction) => filled($transaction->payment_method)))
        @if($pendingTopUps->isNotEmpty())
            <div class="panel p-4 mt-3">
                <h2 class="h5 mb-3">Pending Wallet Top-ups</h2>
                @php($topUpPointValue = (float) \App\Models\TenantSetting::getValue($customer->tenant_id, 'loyalty_point_value_jmd', 1))
                @foreach($pendingTopUps as $transaction)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">JMD {{ number_format((float) $transaction->amount, 2) }}</div>
                                <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $transaction->payment_method)) }}{{ $transaction->reference_number ? ' / '.$transaction->reference_number : '' }}</div>
                                <div class="small text-primary">
                                    Approval awards {{ $topUpPointValue > 0 ? number_format((float) $transaction->amount / $topUpPointValue, 2) : '0.00' }} points
                                    at JMD {{ number_format($topUpPointValue, 4) }} per point.
                                </div>
                            </div>
                            @if($transaction->receipt_path)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('courier.customers.wallet-top-ups.receipt', [$customer, $transaction]) }}" target="_blank" rel="noopener">Receipt</a>
                            @endif
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <form method="POST" action="{{ route('courier.customers.wallet-top-ups.approve', [$customer, $transaction]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('courier.customers.wallet-top-ups.reject', [$customer, $transaction]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Reject</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="col-lg-8">
        <div class="panel p-4 mb-3">
            <h2 class="h5 mb-3">Warehouse Addresses</h2>
            <div class="row g-3 mb-3">
                @forelse($customer->shippingAddresses as $address)
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-bold d-flex align-items-center gap-2">
                                    @if($address->display_flag_image_url)
                                        <img src="{{ $address->display_flag_image_url }}" alt="" style="width:24px;height:18px;object-fit:cover;border-radius:2px;">
                                    @elseif($address->display_flag)
                                        <span>{{ $address->display_flag }}</span>
                                    @endif
                                    <span>{{ $address->label }}</span>
                                </div>
                                <form method="POST" action="{{ route('courier.customers.addresses.destroy', [$customer, $address]) }}" onsubmit="return confirm('Delete this warehouse address for this customer?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                            <div class="small text-muted">{{ $address->country_code ?: 'Warehouse address' }}</div>
                            <div class="mt-2">{{ $address->name }}</div>
                            @if($address->marks)<div>Marks: {{ $address->marks }}</div>@endif
                            @if($address->address_1)<div>{{ $address->address_1 }}</div>@endif
                            @if($address->address_2)<div>Mailbox / Address 2: {{ $address->address_2 }}</div>@endif
                            @if($address->entrance_no)<div>Entrance No.: {{ $address->entrance_no }}</div>@endif
                            @if($address->country || $address->province || $address->city)<div>{{ $address->country }} {{ $address->province }} {{ $address->city }}</div>@endif
                            @if($address->state || $address->zip_code)<div>{{ $address->state }} {{ $address->zip_code }}</div>@endif
                            @if($address->detailed_address)<div>{{ $address->detailed_address }}</div>@endif
                            @if($address->number)<div>{{ $address->number }}</div>@endif
                            @if($address->note)<div class="small text-muted mt-2">{{ $address->note }}</div>@endif
                            <details class="mt-3">
                                <summary class="fw-semibold text-primary">Edit address</summary>
                                <form method="POST" action="{{ route('courier.customers.addresses.update', [$customer, $address]) }}" enctype="multipart/form-data" class="row g-2 mt-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-6"><label class="form-label small">Shipment type</label><input name="shipment_type" class="form-control form-control-sm" value="{{ $address->shipment_type }}" required></div>
                                    <div class="col-md-6"><label class="form-label small">Label</label><input name="label" class="form-control form-control-sm" value="{{ $address->label }}" required></div>
                                    <div class="col-md-6"><label class="form-label small">Country code</label><input name="country_code" class="form-control form-control-sm" value="{{ $address->country_code }}"></div>
                                    <div class="col-md-6"><label class="form-label small">Flag icon</label><input name="flag_icon" class="form-control form-control-sm" value="{{ $address->flag_icon }}"></div>
                                    <div class="col-12"><label class="form-label small">Replace flag image</label><input type="file" name="flag_upload" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.svg"></div>
                                    @if($address->flag_image)
                                        <div class="col-12"><label class="form-check small"><input type="checkbox" name="remove_flag_image" value="1" class="form-check-input"> Remove current flag image</label></div>
                                    @endif
                                    <div class="col-12"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" value="{{ $address->name }}" required></div>
                                    <div class="col-md-6"><label class="form-label small">Marks</label><input name="marks" class="form-control form-control-sm" value="{{ $address->marks }}"></div>
                                    <div class="col-md-6"><label class="form-label small">Address 1</label><input name="address_1" class="form-control form-control-sm" value="{{ $address->address_1 }}"></div>
                                    <div class="col-md-6"><label class="form-label small">Address 2</label><input name="address_2" class="form-control form-control-sm" value="{{ $address->address_2 }}"></div>
                                    <div class="col-md-6"><label class="form-label small">Entrance No.</label><input name="entrance_no" class="form-control form-control-sm" value="{{ $address->entrance_no }}"></div>
                                    <div class="col-md-4"><label class="form-label small">Country</label><input name="country" class="form-control form-control-sm" value="{{ $address->country }}"></div>
                                    <div class="col-md-4"><label class="form-label small">Province</label><input name="province" class="form-control form-control-sm" value="{{ $address->province }}"></div>
                                    <div class="col-md-4"><label class="form-label small">City</label><input name="city" class="form-control form-control-sm" value="{{ $address->city }}"></div>
                                    <div class="col-md-4"><label class="form-label small">State</label><input name="state" class="form-control form-control-sm" value="{{ $address->state }}"></div>
                                    <div class="col-md-4"><label class="form-label small">Zip code</label><input name="zip_code" class="form-control form-control-sm" value="{{ $address->zip_code }}"></div>
                                    <div class="col-md-4"><label class="form-label small">Number</label><input name="number" class="form-control form-control-sm" value="{{ $address->number }}"></div>
                                    <div class="col-12"><label class="form-label small">Detailed address</label><textarea name="detailed_address" rows="2" class="form-control form-control-sm">{{ $address->detailed_address }}</textarea></div>
                                    <div class="col-12"><label class="form-label small">Note</label><textarea name="note" rows="2" class="form-control form-control-sm">{{ $address->note }}</textarea></div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary">Save Address</button></div>
                                </form>
                            </details>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted">No warehouse addresses generated yet.</div>
                @endforelse
            </div>
            @if(auth()->user()->user_type === 'tenant_admin')
                <details>
                    <summary class="fw-semibold">Add another shipping address</summary>
                    <form method="POST" action="{{ route('courier.customers.addresses.store', $customer) }}" enctype="multipart/form-data" class="row g-2 mt-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label d-block">Apply Address To</label>
                            <div class="d-flex flex-wrap gap-3">
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" name="apply_to" value="specific" checked>
                                    <span class="form-check-label">This customer only</span>
                                </label>
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" name="apply_to" value="all">
                                    <span class="form-check-label">All customers</span>
                                </label>
                            </div>
                            <div class="text-muted small">Use tokens like {mailbox}, {name}, and {branch}. When applying to all customers, each customer gets their own mailbox value.</div>
                        </div>
                        <div class="col-md-3"><input name="shipment_type" class="form-control" placeholder="Shipment type" required></div>
                        <div class="col-md-3"><input name="label" class="form-control" placeholder="Label" required></div>
                        <div class="col-md-3"><input name="country_code" class="form-control" placeholder="US/CN"></div>
                        <div class="col-md-3"><input name="flag_icon" class="form-control" placeholder="Flag icon e.g. 🇺🇸"></div>
                        <div class="col-md-6"><input type="file" name="flag_upload" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg"></div>
                        <div class="col-md-4"><input name="name" class="form-control" placeholder="Name, e.g. {name} OLOG" value="{name}" required></div>
                        <div class="col-md-4"><input name="marks" class="form-control" placeholder="Marks"></div>
                        <div class="col-md-4"><input name="mailbox_line" class="form-control" placeholder="Mailbox line e.g. {mailbox} SEA" value="{mailbox}"></div>
                        <div class="col-md-4"><input name="address_1" class="form-control" placeholder="Address 1"></div>
                        <div class="col-md-4"><input name="address_2" class="form-control" placeholder="Address 2"></div>
                        <div class="col-md-3"><input name="entrance_no" class="form-control" placeholder="Entrance No."></div>
                        <div class="col-md-3"><input name="country" class="form-control" placeholder="Country"></div>
                        <div class="col-md-3"><input name="province" class="form-control" placeholder="Province"></div>
                        <div class="col-md-3"><input name="city" class="form-control" placeholder="City"></div>
                        <div class="col-md-3"><input name="state" class="form-control" placeholder="State"></div>
                        <div class="col-md-3"><input name="zip_code" class="form-control" placeholder="Zip Code"></div>
                        <div class="col-md-3"><input name="number" class="form-control" placeholder="Number"></div>
                        <div class="col-md-12"><textarea name="detailed_address" rows="2" class="form-control" placeholder="Detailed address"></textarea></div>
                        <div class="col-md-12"><textarea name="note" rows="2" class="form-control" placeholder="Note"></textarea></div>
                        <div class="col-md-3"><button class="btn btn-primary w-100">Add Address</button></div>
                    </form>
                </details>
            @endif
        </div>
        <div class="panel p-4 mb-3">
            <h2 class="h5 mb-3">Packages</h2>
            <div class="table-responsive"><table class="table"><tbody>@forelse($customer->packages as $package)<tr><td>{{ $package->tracking_number }}</td><td>{{ optional($package->status)->name }}</td><td class="text-end">JMD {{ number_format($package->price_jmd, 2) }}</td></tr>@empty<tr><td class="text-muted">No packages yet.</td></tr>@endforelse</tbody></table></div>
        </div>
        <div class="panel p-4">
            <h2 class="h5 mb-3">Invoices</h2>
            <div class="table-responsive"><table class="table"><tbody>@forelse($customer->invoices as $invoice)<tr><td>{{ $invoice->invoice_number }}</td><td>{{ ucfirst($invoice->payment_status) }}</td><td class="text-end">JMD {{ number_format($invoice->total_jmd, 2) }}</td></tr>@empty<tr><td class="text-muted">No invoices yet.</td></tr>@endforelse</tbody></table></div>
        </div>
    </div>
</div>
@endsection



