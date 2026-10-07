@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Customers</h1><p class="text-muted mb-0">Mailbox customers and account details.</p></div>
    <div class="d-flex gap-2">
        <form id="emailSelectedCustomers" method="GET" action="{{ route('courier.crm.emails.create') }}">
            <button type="submit" class="btn btn-outline-primary" data-email-selected-button disabled><i class="fas fa-paper-plane me-1"></i>Email Selected</button>
        </form>
        <form method="POST" action="{{ route('courier.customers.import-wms') }}" data-confirm="Import all current customers to WMS? Existing WMS customers will be updated.">
            @csrf
            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-file-import me-1"></i>Import to WMS</button>
        </form>
        <a href="{{ route('courier.customers.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.customers.create') }}" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Add Customer</a>
    </div>
</div>
@if(session('warning'))
    <div class="alert alert-warning">
        <div>{{ session('warning') }}</div>
        @if(session('wms_import_errors'))
            <ul class="mb-0 mt-2">
                @foreach(session('wms_import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
<form method="GET" action="{{ route('courier.customers.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, email, phone, or mailbox">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Outlet</label>
            <select name="outlet_id" class="form-select">
                <option value="">All outlets</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6 d-flex gap-2">
            <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
            <a href="{{ route('courier.customers.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th style="width:42px;"><input class="form-check-input" type="checkbox" data-select-all-page-customers></th><th>Customer</th><th>Mailbox</th><th>Outlet</th><th>Balance</th><th>Points</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="selected_customer_ids[]" value="{{ $customer->id }}" form="emailSelectedCustomers" data-customer-email-checkbox @disabled(! optional($customer->user)->email)></td>
                    <td><strong>{{ optional($customer->user)->full_name }}</strong><br><small class="text-muted">{{ optional($customer->user)->email ?: 'No email on file' }}</small></td>
                    <td>{{ $customer->mailbox }}</td>
                    <td>{{ optional($customer->outlet)->name ?? '-' }}</td>
                    <td>JMD {{ number_format($customer->balance_due ?? 0, 2) }}</td>
                    <td>{{ number_format($customer->total_points, 2) }}</td>
                    <td><span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('courier.customers.show', $customer) }}" class="dropdown-item"><i class="fas fa-up-right-from-square me-2"></i>Open</a></li>
                                <li><a href="{{ route('courier.crm.emails.create', ['selected_customer_ids' => $customer->id]) }}" class="dropdown-item {{ optional($customer->user)->email ? '' : 'disabled' }}"><i class="fas fa-paper-plane me-2"></i>Send Marketing Email</a></li>
                                <li><form method="POST" action="{{ route('courier.customers.welcome-email', $customer) }}" data-confirm="Resend the welcome email to this client?">@csrf<button class="dropdown-item"><i class="fas fa-envelope-open-text me-2"></i>Resend Welcome Email</button></form></li>
                                <li><form method="POST" action="{{ route('courier.customers.password-reset', $customer) }}" data-confirm="Send a password reset email to this client?">@csrf<button class="dropdown-item"><i class="fas fa-key me-2"></i>Send Password Reset</button></form></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No customers match the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $customers->links('pagination::bootstrap-5') }}</div>
</div>

<script>
    const customerEmailCheckboxes = Array.from(document.querySelectorAll('[data-customer-email-checkbox]'));
    const emailSelectedButton = document.querySelector('[data-email-selected-button]');
    const selectAllPageCustomers = document.querySelector('[data-select-all-page-customers]');
    const updateEmailSelectedButton = () => {
        const checkedCount = customerEmailCheckboxes.filter(checkbox => checkbox.checked).length;
        if (emailSelectedButton) {
            emailSelectedButton.disabled = checkedCount === 0;
            emailSelectedButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i>' + (checkedCount ? 'Email Selected (' + checkedCount + ')' : 'Email Selected');
        }
        if (selectAllPageCustomers) {
            const enabled = customerEmailCheckboxes.filter(checkbox => !checkbox.disabled);
            selectAllPageCustomers.checked = enabled.length > 0 && enabled.every(checkbox => checkbox.checked);
        }
    };
    customerEmailCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateEmailSelectedButton));
    selectAllPageCustomers?.addEventListener('change', function () {
        customerEmailCheckboxes.forEach(checkbox => {
            if (!checkbox.disabled) checkbox.checked = selectAllPageCustomers.checked;
        });
        updateEmailSelectedButton();
    });
    updateEmailSelectedButton();
</script>@endsection
