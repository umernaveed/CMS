@extends('layouts.app')

@section('title', 'Couriers')

@section('content')
@php
    $canCreateCouriers = auth()->user()->hasPermission('superadmin_courier_workspaces_create');
    $canEditCouriers = auth()->user()->hasPermission('superadmin_courier_workspaces_edit');
    $canDeleteCouriers = auth()->user()->hasPermission('superadmin_courier_workspaces_delete');
    $canOpenCourierPortals = auth()->user()->hasPermission('superadmin_courier_workspaces_open_portal');
    $billingLabels = [
        'subscription' => 'Subscription',
        'per_package' => 'Per Package',
        'hybrid' => 'Subscription + Per Package',
    ];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Couriers</h1>
        <p class="text-muted mb-0">Courier companies using the platform.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('superadmin.tenants.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract</a>
        <a href="{{ route('superadmin.tenants.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('superadmin.tenants.download', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
        @if($canCreateCouriers)
            <a href="{{ route('superadmin.tenants.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Courier</a>
        @endif
    </div>
</div>

@include('superadmin.tenants._tabs')

<form method="GET" action="{{ route('superadmin.tenants') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-xl-3 col-lg-4 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, code, domain, email, phone">
        </div>
        <div class="col-xl-2 col-lg-3 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['trial','active','suspended','expired','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-6">
            <label class="form-label">Billing</label>
            <select name="billing_model" class="form-select">
                <option value="">All billing</option>
                @foreach($billingLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('billing_model') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-6">
            <label class="form-label">Plan</label>
            <select name="plan_id" class="form-select">
                <option value="">All plans</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-1 col-lg-2 col-md-4">
            <label class="form-label">Show</label>
            <select name="per_page" class="form-select">
                @foreach([10, 15, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((string) request('per_page', 15) === (string) $size)>{{ $size }}</option>
                @endforeach
                <option value="all" @selected(request('per_page') === 'all')>All</option>
            </select>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-8 d-flex gap-2">
            <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
            <a href="{{ route('superadmin.tenants') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Company</th><th>Domain</th><th>Plan Usage</th><th>Status</th><th>Contact</th><th></th></tr></thead>
            <tbody>
            @forelse($tenants as $tenant)
                @php
                    $usage = $tenantUsage[$tenant->id] ?? null;
                    $courierCode = $tenantCodes[$tenant->id] ?? $tenant->subdomain ?? $tenant->slug;
                    $portalUrl = ($tenant->subdomain || $tenant->custom_domain) ? $tenant->portalUrl() : null;
                @endphp
                <tr>
                    <td>
                        <strong>{{ $tenant->company_name }}</strong>
                        <br><small class="text-muted">Code: {{ $courierCode ?: '-' }}</small>
                        <br><small class="text-muted">{{ $tenant->city ?: '-' }}, {{ $tenant->parish ?: '-' }}</small>
                    </td>
                    <td>
                        @if($portalUrl)
                            <a href="{{ $portalUrl }}" target="_blank">{{ $portalUrl }}</a>
                            <br><small class="text-muted">Customer signup: {{ $portalUrl }}/register-customer</small>
                            <br><small class="text-muted">Subdomain: {{ $tenant->subdomain ?: '-' }}</small>
                            <br><small class="text-muted">Custom domain: {{ $tenant->custom_domain ?: '-' }}</small>
                            <br><small class="text-muted">TRN / SSN: {{ $tenant->tax_identifier ?: '-' }}</small>
                            <br><small class="text-muted">Billing: {{ $billingLabels[$tenant->billing_model ?? 'subscription'] ?? ucfirst(str_replace('_', ' + ', $tenant->billing_model ?? 'subscription')) }}</small>
                        @else
                            -
                        @endif
                    </td>
                    <td style="min-width: 430px">
                        <div class="fw-semibold mb-2">{{ optional(optional($tenant->subscription)->plan)->name ?? 'No plan' }}</div>
                        @if($usage)
                            @include('superadmin.tenants._usage', ['usage' => $usage])
                            <div class="text-muted small mt-2">Storage limit: {{ $usage['storage_days_limit'] === null ? 'Unlimited' : $usage['storage_days_limit'].' days' }}</div>
                        @endif
                    </td>
                    <td><span class="badge badge-soft">{{ ucfirst($tenant->status) }}</span></td>
                    <td>{{ $tenant->email ?: '-' }}<br><small class="text-muted">{{ $tenant->phone ?: '-' }}</small></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('superadmin.tenants.show', $tenant) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                                @if($canOpenCourierPortals)
                                    <li><form method="POST" action="{{ route('superadmin.tenants.open-portal', $tenant) }}">@csrf <button class="dropdown-item" type="submit"><i class="fas fa-right-to-bracket me-2"></i>Open Portal</button></form></li>
                                @endif
                                @if($canEditCouriers)
                                    <li><a href="{{ route('superadmin.tenants.edit', $tenant) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                                @endif
                                @if($canDeleteCouriers)
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('superadmin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this courier and all courier data?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No couriers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
        <div class="text-muted small">
            @if($tenants->total())
                Showing {{ number_format($tenants->firstItem()) }} to {{ number_format($tenants->lastItem()) }} of {{ number_format($tenants->total()) }} results
            @else
                Showing 0 results
            @endif
        </div>
        {{ $tenants->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection