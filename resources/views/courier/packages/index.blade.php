@extends('layouts.app')

@section('title', 'Packages')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Packages</h1><p class="text-muted mb-0">Packages attached to an in-house manifest.</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('courier.packages.aging', request()->query()) }}" class="btn btn-outline-secondary"><i class="fas fa-hourglass-half me-1"></i>Aging @if(! empty($agingPackageCount))<span class="badge bg-danger ms-1">{{ $agingPackageCount }}</span>@endif</a>
        <a href="{{ route('courier.packages.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.packages.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.packages.template') }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Template</a>
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#bulkUpload"><i class="fas fa-upload me-1"></i>Bulk Upload</button>
        <a href="{{ route('courier.packages.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Package</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="packageTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="packages-tab" data-bs-toggle="tab" data-bs-target="#packagesTab" type="button" role="tab" aria-controls="packagesTab" aria-selected="true"><i class="fas fa-boxes-stacked me-1"></i>Packages</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="handover-tab" data-bs-toggle="tab" data-bs-target="#handoverHistory" type="button" role="tab" aria-controls="handoverHistory" aria-selected="false"><i class="fas fa-barcode me-1"></i>Scan Out / Handover History</button>
    </li>
</ul>

<div class="tab-content" id="packageTabsContent">
    <section class="tab-pane fade show active" id="packagesTab" role="tabpanel" aria-labelledby="packages-tab" tabindex="0">
        <form method="GET" action="{{ route('courier.packages.index') }}" class="panel p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tracking, mailbox, merchant"></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>@endforeach</select></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(request('status_id') == $status->id)>{{ $status->name }}</option>@endforeach</select></div>
                <div class="col-lg-1 col-md-6"><label class="form-label">Freight</label><select name="freight_type" class="form-select"><option value="">All</option>@foreach(($shippingModes ?? collect())->where('is_active', true) as $mode)<option value="{{ $mode->code }}" @selected(request('freight_type') === $mode->code)>{{ $mode->name }}</option>@endforeach</select></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('courier.packages.index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </div>
        </form>

        <div class="collapse mb-3" id="bulkUpload">
            <form method="POST" action="{{ route('courier.packages.import') }}" enctype="multipart/form-data" class="panel p-3">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-8"><label class="form-label">CSV File</label><input type="file" name="file" class="form-control" accept=".csv,.txt" required></div>
                    <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button><a href="{{ route('courier.packages.template') }}" class="btn btn-outline-secondary">Download Template</a></div>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>HAWB</th><th>Tracking</th><th>Customer</th><th>Ship By</th><th>Weight / Volume</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($packages as $package)
                        @php($packageInvoiceItem = $package->activeInvoiceItem())
                        @php($packageInvoice = optional($packageInvoiceItem)->invoice)
                        <tr>
                            <td><strong>{{ $package->tracking_number }}</strong><br><small class="text-muted">{{ optional($package->date_received)->format('M d, Y') ?? '-' }}</small></td>
                            <td>{{ $package->supplier_tracking ?: '-' }}<br><small class="text-muted">{{ $package->merchant ?: $package->courier }}</small></td>
                            <td>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $package->mailbox }}</small></td>
                            <td>{{ $package->labelType() }} or {{ ucfirst($package->delivery_type) }}<br><small class="text-muted">{{ $package->normalizedRegion() }}</small></td>
                            <td>{{ $package->measureDisplay() }}</td>
                            <td>@if($packageInvoiceItem) JMD {{ number_format((float) $packageInvoiceItem->total_jmd, 2) }} @else <span class="text-muted">-</span> @endif</td>
                            <td><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'No status' }}</span></td>
                            <!--<td>@if($package->invoice_file)<a href="{{ route('courier.packages.receipt', $package) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a>@else<span class="text-muted">-</span>@endif</td>-->
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                    @if($packageInvoice)
                                        <li><a class="dropdown-item" href="{{ route('courier.invoices.show', $packageInvoice) }}"><i class="fas fa-file-invoice me-2"></i>Invoice</a></li>
                                    @elseif($package->customer_id)
                                        <li><a class="dropdown-item" href="{{ route('courier.invoices.create', ['package_id' => $package->id]) }}"><i class="fas fa-file-circle-plus me-2"></i>Create Invoice</a></li>
                                    @endif
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reassignPackage{{ $package->id }}"><i class="fas fa-user-tag me-2"></i>Reassign</button></li>
                                    <li><a class="dropdown-item" href="{{ route('courier.packages.label', $package) }}" target="_blank"><i class="fas fa-barcode me-2"></i>Label</a></li>
                                    <li><a class="dropdown-item" href="{{ route('courier.packages.show', $package) }}"><i class="fas fa-up-right-from-square me-2"></i>Open</a></li>
                                    <li><a class="dropdown-item" href="{{ route('courier.packages.edit', $package) }}"><i class="fas fa-pen me-2"></i>Modify</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('courier.packages.destroy', $package) }}" data-confirm="Delete this package?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                    </ul>
                                </div>
                                <div class="modal fade text-start" id="reassignPackage{{ $package->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('courier.packages.reassign', $package) }}" class="modal-content">
                                            @csrf @method('PATCH')
                                            <div class="modal-header"><h5 class="modal-title">Reassign Package</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                            <div class="modal-body">
                                                <label class="form-label">New Customer</label>
                                                <select name="customer_id" class="form-select" required>
                                                    @foreach($customers as $customer)
                                                        <option value="{{ $customer->id }}" @selected($package->customer_id == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reassign</button></div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No packages yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $packages->links('pagination::bootstrap-5') }}</div>
        </div>
    </section>

    <section class="tab-pane fade" id="handoverHistory" role="tabpanel" aria-labelledby="handover-tab" tabindex="0">
        <div class="panel">
            <div class="p-3 border-bottom">
                <h2 class="h5 mb-1">Scan Out / Handover History</h2>
                <p class="text-muted small mb-0">Recent packages verified and handed over to clients.</p>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Package</th><th>Customer</th><th>Verified By</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                    @forelse($handoverLogs as $log)
                        @php($handoverPackage = $log->package)
                        <tr>
                            <td><strong>{{ optional($handoverPackage)->tracking_number ?? 'Package #'.$log->package_id }}</strong><br><small class="text-muted">{{ $log->note ?: 'Package handover recorded' }}</small></td>
                            <td>{{ optional(optional(optional($handoverPackage)->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ optional($handoverPackage)->mailbox ?: '-' }}</small></td>
                            <td>{{ optional($log->user)->full_name ?? optional($log->user)->email ?? 'System' }}<br><small class="text-muted">{{ $log->location ?: '-' }}</small></td>
                            <td><span class="badge" style="background: {{ optional($log->status)->color ?? '#64748b' }}">{{ optional($log->status)->name ?? 'No status' }}</span></td>
                            <td>{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No handover history yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const packagesTab = document.getElementById('packages-tab');
    if (packagesTab && typeof bootstrap !== 'undefined') {
        bootstrap.Tab.getOrCreateInstance(packagesTab).show();
    }

    if (window.location.hash === '#handoverHistory') {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
});
</script>
@endsection
