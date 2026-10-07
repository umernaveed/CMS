@extends('layouts.app')

@section('title', 'Courier Customers')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Courier Customers</h1>
        <p class="text-muted mb-0">Customers registered across courier workspaces.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.tenants.customers.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract</a>
        <a href="{{ route('superadmin.tenants.customers.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('superadmin.tenants.customers.download', request()->query()) }}" class="btn btn-primary"><i class="fas fa-download me-1"></i>Download</a>
    </div>
</div>

@include('superadmin.tenants._tabs')

<form method="GET" action="{{ route('superadmin.tenants.customers') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, email, phone, mailbox, courier">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select">
                <option value="">All couriers</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6 d-flex gap-2">
            <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
            <a href="{{ route('superadmin.tenants.customers') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Courier</th>
                    <th>Mailbox</th>
                    <th>Outlet</th>
                    <th>Packages</th>
                    <th>Invoices</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td>
                            <strong>{{ optional($customer->user)->full_name ?: 'Unnamed Customer' }}</strong>
                            <br><small class="text-muted">{{ optional($customer->user)->email ?: '-' }}</small>
                            @if(optional($customer->user)->phone)
                                <br><small class="text-muted">{{ $customer->user->phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if($customer->tenant)
                                <a href="{{ route('superadmin.tenants.show', $customer->tenant) }}">{{ $customer->tenant->company_name }}</a>
                            @else
                                <span class="text-muted">Deleted courier</span>
                            @endif
                        </td>
                        <td>{{ $customer->mailbox ?: '-' }}</td>
                        <td>{{ optional($customer->outlet)->name ?: '-' }}</td>
                        <td>{{ number_format($customer->packages_count) }}</td>
                        <td>{{ number_format($customer->invoices_count) }}</td>
                        <td>JMD {{ number_format((float) ($customer->current_balance ?? 0), 2) }}</td>
                        <td><span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No courier customers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $customers->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
