@extends('layouts.app')

@section('title', 'Courier Details')

@section('content')
@php
    $canEditCouriers = auth()->user()->hasPermission('superadmin_courier_workspaces_edit');
    $canDeleteCouriers = auth()->user()->hasPermission('superadmin_courier_workspaces_delete');
    $canOpenCourierPortals = auth()->user()->hasPermission('superadmin_courier_workspaces_open_portal');
    $canManageCourierCredits = auth()->user()->hasPermission('superadmin_courier_workspaces_credit');
@endphp
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $tenant->company_name }}</h1>
        <p class="text-muted mb-0">{{ $tenant->email }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($canOpenCourierPortals)
        <form method="POST" action="{{ route('superadmin.tenants.open-portal', $tenant) }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Open Portal</button>
        </form>
        @endif
        @if($canEditCouriers)
            <a href="{{ route('superadmin.tenants.edit', $tenant) }}" class="btn btn-primary">Edit</a>
        @endif
        @if($canDeleteCouriers)
            <form method="POST" action="{{ route('superadmin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this courier and all courier data?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="panel p-4 h-100">
            <h2 class="h5 mb-3">Company Profile</h2>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted">Status</div><span class="badge badge-soft">{{ ucfirst($tenant->status) }}</span></div>
                <div class="col-md-4"><div class="text-muted">Subdomain</div>{{ $tenant->subdomain ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Custom Domain</div>{{ $tenant->custom_domain ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Phone</div>{{ $tenant->phone ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">TRN / SSN</div>{{ $tenant->tax_identifier ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Billing Model</div>{{ ucfirst(str_replace('_', ' + ', $tenant->billing_model ?? 'subscription')) }}</div>
                <div class="col-md-4"><div class="text-muted">Expected Monthly Packages</div>{{ $tenant->expected_monthly_packages ? number_format($tenant->expected_monthly_packages) : '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Base Fee / Logged Package</div>JMD {{ number_format((float) $tenant->per_package_base_fee_jmd, 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Warehouse Package Service Fee</div>JMD {{ number_format((float) $tenant->package_service_fee_jmd, 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Credit Balance</div><strong class="text-success">JMD {{ number_format((float) $tenant->credit_balance_jmd, 2) }}</strong></div>
                <div class="col-md-4"><div class="text-muted">City</div>{{ $tenant->city ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Parish</div>{{ $tenant->parish ?: '-' }}</div>
                <div class="col-12"><div class="text-muted">Address</div>{{ $tenant->address ?: '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <h2 class="h5 mb-3">Subscription</h2>
            <div class="mb-2"><div class="text-muted">Plan</div>{{ optional(optional($tenant->subscription)->plan)->name ?? 'No plan' }}</div>
            <div class="mb-2"><div class="text-muted">Billing</div>{{ optional($tenant->subscription)->billing_cycle ? ucfirst($tenant->subscription->billing_cycle) : '-' }}</div>
            <div class="mb-2"><div class="text-muted">Trial Ends</div>{{ optional($tenant->trial_ends_at)->format('M d, Y') ?? '-' }}</div>
            <div><div class="text-muted">Subscription Ends</div>{{ optional($tenant->subscription_ends_at)->format('M d, Y') ?? '-' }}</div>
        </div>
    </div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Registration Documents</h2>
    @php($documentLabels = [
        'government_id_documents' => 'Government ID',
        'tax_documents' => 'TRN / EIN Document',
        'business_documents' => 'Business Registration',
    ])
    <div class="row g-3">
        @foreach($documentLabels as $field => $label)
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">{{ $label }}</div>
                    @forelse(($onboardingDocuments[$field] ?? []) as $index => $path)
                        <div class="mb-2">
                            <div class="text-muted small text-truncate mb-2">{{ basename($path) }}</div>
                            <div class="d-flex flex-wrap gap-1">
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('superadmin.tenants.onboarding-documents.view', [$tenant, $field, $index]) }}">View</a>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('superadmin.tenants.onboarding-documents.print', [$tenant, $field, $index]) }}">Print</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.tenants.onboarding-documents.download', [$tenant, $field, $index]) }}">Download</a>
                            </div>
                        </div>
                    @empty
                        <span class="text-muted small">No file uploaded.</span>
                    @endforelse
                    @if($canEditCouriers)
                        <form method="POST" action="{{ route('superadmin.tenants.onboarding-documents.upload', [$tenant, $field]) }}" enctype="multipart/form-data" class="mt-3">
                            @csrf
                            <input type="file" name="document" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required>
                            <button class="btn btn-sm btn-outline-primary">Upload</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Plan Usage</h2>
            @include('superadmin.tenants._usage', ['usage' => $usage])
            <div class="text-muted small mt-3">Storage limit: {{ $usage['storage_days_limit'] === null ? 'Unlimited' : $usage['storage_days_limit'].' days' }}</div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Accounts</h2>
            <div class="row g-3">
                <div class="col-6"><div class="text-muted">Users</div><div class="h4">{{ $tenant->users->count() }}</div></div>
                <div class="col-6"><div class="text-muted">Outlets</div><div class="h4">{{ $tenant->outlets->count() }}</div></div>
            </div>
        </div>
    </div>
</div>

<div class="panel p-4 mt-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Courier Credit Wallet</h2>
            <p class="text-muted mb-0">Credits are automatically applied first to future courier bills until the balance is used.</p>
            @if($canManageCourierCredits)
                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('superadmin.tenants.wallet', $tenant) }}">View Full History</a>
            @endif
        </div>
        @if($canManageCourierCredits)
        <form method="POST" action="{{ route('superadmin.tenants.credit', $tenant) }}" class="d-flex gap-2 flex-wrap">
            @csrf
            <select name="action" class="form-select" style="width: 170px;">
                <option value="add">Add credit</option>
                <option value="subtract">Subtract credit</option>
            </select>
            <input name="amount_jmd" type="number" step="0.01" min="0.01" class="form-control" style="width: 180px;" placeholder="Amount JMD" required>
            <input name="notes" class="form-control" style="width: 260px;" placeholder="Reason / adjustment note">
            <button class="btn btn-primary">Save Adjustment</button>
        </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Notes</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
                @forelse($tenant->creditTransactions as $transaction)
                    <tr>
                        <td>{{ optional($transaction->created_at)->format('M d, Y h:i A') }}</td>
                        <td>{{ ucfirst($transaction->type) }}</td>
                        <td>{{ $transaction->reference_number ?: '-' }}</td>
                        <td>{{ $transaction->notes ?: '-' }}</td>
                        <td class="text-end {{ (float) $transaction->amount_jmd >= 0 ? 'text-success' : 'text-danger' }}">JMD {{ number_format((float) $transaction->amount_jmd, 2) }}</td>
                        <td class="text-end">JMD {{ number_format((float) $transaction->balance_after_jmd, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No credit transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
