@extends('layouts.app')

@section('title', 'Aging Packages')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Aging Packages <span class="badge bg-danger">{{ $packages->total() }}</span></h1><p class="text-muted mb-0">Invoiced pickup and delivery packages still waiting on the client.</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('courier.packages.export', array_merge(request()->query(), ['aging' => 1])) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.packages.print', array_merge(request()->query(), ['aging' => 1])) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print / Download</a>
        <a href="{{ route('courier.packages.index') }}" class="btn btn-outline-secondary">All Packages</a>
    </div>
</div>

<form method="GET" action="{{ route('courier.packages.aging') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tracking, mailbox, merchant"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(request('status_id') == $status->id)>{{ $status->name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Minimum Age</label><input type="number" name="min_age" class="form-control" value="{{ request('min_age', $minimumAge ?? 7) }}" min="0" placeholder="Days"></div>
        <div class="col-lg-3 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('courier.packages.aging') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<form method="POST" action="{{ route('courier.packages.aging.reminders') }}" class="panel">
    @csrf
    <input type="hidden" name="min_age" value="{{ $minimumAge ?? max((int) request('min_age', 7), 0) }}">
    <input type="hidden" name="send_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center p-3 border-bottom">
        <div class="text-muted small">Select packages, then email each client a reminder for their selected packages.</div>
        <button class="btn btn-primary" type="submit" data-aging-email-selected disabled><i class="fas fa-envelope me-1"></i>Email Selected</button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th style="width: 42px;"><input class="form-check-input" type="checkbox" id="selectAgingPackages" aria-label="Select all packages"></th><th>Tracking</th><th>Customer</th><th>Status</th><th>Invoiced</th><th>Age</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                @php
                    $customerEmail = optional(optional($package->customer)->user)->email;
                    $agingInvoice = $package->activeIssuedInvoice();
                    $invoiceDate = optional($agingInvoice)->created_at;
                    $ageDays = $package->invoiceAgingDays();
                    $agingStorageFee = $package->invoiceAgingStorageFee($minimumAge ?? 7);
                @endphp
                <tr>
                    <td><input class="form-check-input aging-package-check" type="checkbox" name="package_ids[]" value="{{ $package->id }}" aria-label="Select {{ $package->tracking_number }}" @disabled(! $customerEmail)></td>
                    <td><strong>{{ $package->tracking_number }}</strong><br><small class="text-muted">{{ $package->merchant ?: $package->courier }}</small></td>
                    <td>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $package->mailbox }}{{ $customerEmail ? ' / '.$customerEmail : ' / no email' }}</small></td>
                    <td><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'No status' }}</span></td>
                    <td>{{ optional($invoiceDate)->format('M d, Y') ?? '-' }}</td>
                    <td>{{ number_format($ageDays) }} days</td>
                    <td>JMD {{ number_format((float) $package->price_jmd + (float) $package->processing_fee_jmd + $agingStorageFee, 2) }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('courier.packages.show', $package) }}"><i class="fas fa-eye me-2"></i>View</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No aging packages found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $packages->links('pagination::bootstrap-5') }}</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAgingPackages');
    const emailButton = document.querySelector('[data-aging-email-selected]');
    const reminderForm = emailButton ? emailButton.closest('form') : null;
    const checkboxes = Array.from(document.querySelectorAll('.aging-package-check:not(:disabled)'));
    const updateEmailButton = function () {
        if (!emailButton) return;
        const checkedCount = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        emailButton.disabled = checkedCount === 0;
        emailButton.innerHTML = '<i class="fas fa-envelope me-1"></i>' + (checkedCount ? 'Email Selected (' + checkedCount + ')' : 'Email Selected');
    };

    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateEmailButton();
    });
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            selectAll.checked = checkboxes.length > 0 && checkboxes.every(function (item) { return item.checked; });
            updateEmailButton();
        });
    });
    if (reminderForm) {
        reminderForm.addEventListener('submit', function () {
            if (!emailButton || emailButton.disabled) return;
            emailButton.disabled = true;
            emailButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
        });
    }
    updateEmailButton();
});
</script>
@endsection
