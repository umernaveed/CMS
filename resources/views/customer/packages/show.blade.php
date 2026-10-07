@extends('layouts.app')

@section('title', 'Track Package')

@section('content')
@php
    $purchaseInvoiceFiles = $package->purchaseReceipts->map(fn ($receipt) => [
        'path' => $receipt->path,
        'label' => $receipt->original_name ?: basename($receipt->path),
        'url' => route('customer.packages.receipts.show', [$package, $receipt]),
        'date' => optional($receipt->created_at)->format('M d, Y h:i A'),
    ]);
    if ($package->invoice_file && ! $purchaseInvoiceFiles->contains('path', $package->invoice_file)) {
        $purchaseInvoiceFiles->push([
            'path' => $package->invoice_file,
            'label' => basename($package->invoice_file),
            'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($package->invoice_file),
            'date' => null,
        ]);
    }
@endphp
@php($activeInvoiceItem = $package->activeInvoiceItem())
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $package->tracking_number }}</h1>
        <p class="text-muted mb-0">{{ $package->description ?: 'Package tracking details' }}</p>
    </div>
    @if($purchaseInvoiceFiles->isNotEmpty())
        <a href="{{ route('customer.packages.receipt', $package) }}" target="_blank" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i>View Purchase Invoices</a>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Package Details</h2>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted">Status</div><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'Processing' }}</span></div>
                <div class="col-md-4"><div class="text-muted">Mailbox</div>{{ $package->mailbox }}</div>
                <div class="col-md-4"><div class="text-muted">Delivery Type</div>{{ ucfirst($package->delivery_type) }}</div>
                <div class="col-md-4"><div class="text-muted">Merchant</div>{{ $package->merchant ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Courier</div>{{ $package->courier ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">{{ $package->measureLabel() }}</div>{{ $package->measureDisplay() }}</div>
                <div class="col-md-4"><div class="text-muted">Package Price</div>{{ $package->customerVisibleCostDisplay() }}</div>
                <div class="col-md-4"><div class="text-muted">Storage Fee</div>JMD {{ number_format((float) (optional($activeInvoiceItem)->storage_fee_jmd ?? $package->storage_fee), 2) }}</div>
                <div class="col-md-4"><div class="text-muted">Received</div>{{ optional($package->date_received)->format('M d, Y') ?? '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4 mb-3">
            <h2 class="h5 mb-3">Purchase Invoices</h2>
            @if($purchaseInvoiceFiles->isNotEmpty())
                <div class="list-group mb-3">
                    @foreach($purchaseInvoiceFiles as $index => $file)
                        <a href="{{ $file['url'] }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                            <span><i class="fas fa-file-invoice me-2"></i>{{ $file['label'] }}</span>
                            <small class="text-muted text-nowrap">{{ $file['date'] ?: 'Uploaded' }}</small>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-muted small">Upload purchase invoices or receipts for this package.</p>
            @endif
            <form method="POST" action="{{ route('customer.packages.receipt.upload', $package) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="purchase_receipts[]" class="form-control @error('purchase_receipts') is-invalid @enderror @error('purchase_receipts.*') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                @error('purchase_receipts')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('purchase_receipts.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Upload up to 5 files. Accepted formats: PDF, JPEG, JPG, PNG.</div>
                <button class="btn btn-primary w-100 mt-3"><i class="fas fa-upload me-1"></i>Upload Invoice Files</button>
            </form>
        </div>
        <div class="panel p-4">
            <h2 class="h5 mb-3">Tracking Timeline</h2>
            @forelse($package->logs as $log)
                <div class="border-start ps-3 pb-3">
                    <div class="fw-semibold">{{ optional($log->status)->name ?? 'Update' }}</div>
                    <div class="small text-muted">{{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : '' }}</div>
                    <div>{{ $log->note }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">No tracking updates have been posted yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection




