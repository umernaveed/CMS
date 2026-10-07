@extends('layouts.app')

@section('title', 'Purchase Invoices')

@section('content')
@php
    $purchaseInvoiceFiles = $package->purchaseReceipts->map(fn ($receipt) => [
        'label' => $receipt->original_name ?: basename($receipt->path),
        'url' => route('customer.packages.receipts.show', [$package, $receipt]),
        'date' => optional($receipt->created_at)->format('M d, Y h:i A'),
    ]);

    if ($package->invoice_file) {
        $purchaseInvoiceFiles->push([
            'label' => basename($package->invoice_file),
            'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($package->invoice_file),
            'date' => null,
        ]);
    }
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Purchase Invoices</h1>
        <p class="text-muted mb-0">{{ $package->tracking_number }}</p>
    </div>
    <a href="{{ route('customer.packages.show', $package) }}" class="btn btn-outline-secondary">Back to Package</a>
</div>

<div class="panel p-4">
    <div class="list-group">
        @forelse($purchaseInvoiceFiles as $file)
            <a href="{{ $file['url'] }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2">
                <span><i class="fas fa-file-invoice me-2"></i>{{ $file['label'] }}</span>
                <small class="text-muted text-nowrap">{{ $file['date'] ?: 'Uploaded' }}</small>
            </a>
        @empty
            <div class="text-muted">No purchase invoices uploaded yet.</div>
        @endforelse
    </div>
</div>
@endsection