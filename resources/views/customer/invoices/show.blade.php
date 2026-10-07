@extends('layouts.app')

@section('title', 'Invoice Details')
@section('page_title', $invoice->payment_status === 'paid' ? 'Receipt' : 'Invoice')
@section('breadcrumb', $invoice->invoice_number)

@section('content')
@include('invoices.receipt-styles')

<div class="receipt-actions">
    <a href="{{ route('customer.invoices.download', $invoice) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Download PDF</a>
    <a href="{{ route('customer.invoices.print', $invoice) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Print Invoice</a>
</div>

@include('invoices.receipt-card', ['invoice' => $invoice])

@if(in_array($invoice->payment_status, ['unpaid', 'partial']))
    <div class="panel p-4 mt-4">
        <h2 class="h5 mb-1">Upload Payment Receipt</h2>
        <p class="text-muted mb-3">Submit proof of payment for courier review. Your invoice will update after the courier approves the receipt.</p>
        <form method="POST" action="{{ route('customer.invoices.receipt.upload', $invoice) }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Amount Paid</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $invoice->total_jmd) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Reference Number</label>
                <input name="reference_number" class="form-control" value="{{ old('reference_number') }}" placeholder="Bank transfer/reference">
            </div>
            <div class="col-md-4">
                <label class="form-label">Receipt File</label>
                <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Submit Receipt</button>
            </div>
        </form>
    </div>
@endif

@if($invoice->payments->isNotEmpty())
    <div class="panel p-4 mt-4">
        <h2 class="h5 mb-3">Uploaded Payment Receipts</h2>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Payment</th><th>Status</th><th>Reference</th><th>Submitted</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @foreach($invoice->payments as $payment)
                    <tr>
                        <td><strong>{{ $payment->payment_number }}</strong></td>
                        <td><span class="badge badge-soft">{{ ucfirst($payment->status) }}</span></td>
                        <td>{{ $payment->reference_number ?: '-' }}</td>
                        <td>{{ optional($payment->paid_at)->format('M d, Y') ?? optional($payment->created_at)->format('M d, Y') }}</td>
                        <td class="text-end">JMD {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
