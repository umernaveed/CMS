@extends('layouts.app')

@section('title', 'Package Details')

@section('content')
@php($packageInvoiceItem = $package->activeInvoiceItem())
@php($packageInvoice = optional($packageInvoiceItem)->invoice)
@php($purchaseReceipts = $package->purchaseReceipts ?? collect())
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">{{ $package->tracking_number }}</h1><p class="text-muted mb-0">{{ $package->description ?: 'Package details' }}</p></div>
    <div class="d-flex gap-2">
        @if($packageInvoice)
            <a href="{{ route('courier.invoices.show', $packageInvoice) }}" class="btn btn-outline-primary">View Invoice</a>
        @elseif($package->customer_id)
            <a href="{{ route('courier.invoices.create', ['package_id' => $package->id]) }}" class="btn btn-outline-primary">Create Invoice</a>
        @endif
        @if($package->invoice_file)
            <a href="{{ route('courier.packages.receipt', $package) }}" class="btn btn-outline-secondary">Receipt</a>
        @endif
        <a href="{{ route('courier.packages.label', $package) }}" class="btn btn-outline-secondary" target="_blank">Print Label</a>
        <a href="{{ route('courier.packages.edit', $package) }}" class="btn btn-primary">Edit</a>
        <form method="POST" action="{{ route('courier.packages.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Shipment</h2>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted">Status</div><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'No status' }}</span></div>
                <div class="col-md-4"><div class="text-muted">Customer</div>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}</div>
                <div class="col-md-4"><div class="text-muted">Mailbox</div>{{ $package->mailbox }}</div>
                <div class="col-md-4"><div class="text-muted">Freight</div>{{ ucfirst($package->freight_type) }}</div>
                <div class="col-md-4"><div class="text-muted">Region / Label</div>{{ $package->normalizedRegion() }} / {{ $package->labelType() }}</div>
                <div class="col-md-4"><div class="text-muted">{{ $package->measureLabel() }}</div>{{ $package->measureDisplay() }}</div>
                <div class="col-md-4"><div class="text-muted">Price</div>JMD {{ number_format((float) (optional($packageInvoiceItem)->package_price_jmd ?? $package->price_jmd), 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Processing Fee</div>JMD {{ number_format((float) (optional($packageInvoiceItem)->processing_fee_jmd ?? $package->processing_fee_jmd), 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Unknown Fee</div>JMD {{ number_format((float) (optional($packageInvoiceItem)->unknown_fee_jmd ?? $package->unknown_fee_jmd), 2) }} @if($package->unknown_fee_reason)<br><small>{{ $package->unknown_fee_reason }}</small>@endif</div>
                <div class="col-md-4"><div class="text-muted">Storage Fee</div>JMD {{ number_format((float) (optional($packageInvoiceItem)->storage_fee_jmd ?? $package->storage_fee), 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Merchant</div>{{ $package->merchant ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Courier</div>{{ $package->courier ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Received</div>{{ optional($package->date_received)->format('M d, Y') ?? '-' }}</div>
            </div>
        </div>
        <div class="panel p-4 mt-3">
            <h2 class="h5 mb-3">Reassign Customer</h2>
            <form method="POST" action="{{ route('courier.packages.reassign', $package) }}" class="row g-2 align-items-end">
                @csrf @method('PATCH')
                <div class="col-md-8">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($package->customer_id == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><button class="btn btn-primary w-100">Reassign</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4 mb-3">
            <h2 class="h5 mb-3">Purchase Invoices</h2>
            @if($package->invoice_file || $purchaseReceipts->isNotEmpty())
                <div class="list-group">
                    @if($package->invoice_file)
                        <a href="{{ route('courier.packages.receipt', $package) }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                            <span><i class="fas fa-file-invoice me-2"></i>{{ basename($package->invoice_file) }}</span>
                            <small class="text-muted text-nowrap">Legacy</small>
                        </a>
                    @endif
                    @foreach($purchaseReceipts as $receipt)
                        <a href="{{ route('courier.packages.purchase-receipts.show', [$package, $receipt]) }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                            <span><i class="fas fa-file-invoice me-2"></i>{{ $receipt->original_name ?: basename($receipt->path) }}</span>
                            <small class="text-muted text-nowrap">{{ optional($receipt->created_at)->format('M d, Y h:i A') ?: 'Uploaded' }}</small>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-muted mb-0">No purchase invoices uploaded.</p>
            @endif
        </div>
        <div class="panel p-4">
            <h2 class="h5 mb-3">Update Tracking</h2>
            <form method="POST" action="{{ route('courier.packages.logs.store', $package) }}" class="mb-4">
                @csrf
                <div class="mb-3">
                    <label class="form-label">New Status</label>
                    <select name="status_id" class="form-select" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}" @selected($package->status_id == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Location</label>
                    <input name="location" class="form-control" value="{{ old('location', optional(auth()->user()->outlet)->name) }}" placeholder="Warehouse, branch, or delivery area">
                </div>
                <div class="mb-3">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Short tracking note for customer visibility">{{ old('note') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Log Time</label>
                    <input type="datetime-local" name="created_at" class="form-control" value="{{ old('created_at', now()->format('Y-m-d\\TH:i')) }}">
                </div>
                <button class="btn btn-primary w-100">Add Tracking Update</button>
            </form>

            <h2 class="h5 mb-3">Timeline</h2>
            @forelse($package->logs as $log)
                <div class="border-start ps-3 pb-3">
                    <div class="fw-semibold">{{ optional($log->status)->name ?? 'Update' }}</div>
                    <div class="small text-muted">{{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : '' }}</div>
                    <div>{{ $log->note }}</div>
                    @if($log->location || $log->user)
                        <div class="small text-muted mt-1">{{ $log->location ?: 'No location' }} @if($log->user) / {{ $log->user->full_name ?: $log->user->email }} @endif</div>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">No tracking updates yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

