@extends('layouts.app')

@section('title', 'Invoice Details')
@section('page_title', $invoice->payment_status === 'paid' ? 'Receipt' : 'Invoice')
@section('breadcrumb', $invoice->invoice_number)

@section('content')
@include('invoices.receipt-styles')

@if($invoice->isLocked())
    <div class="alert alert-secondary d-flex align-items-start gap-2">
        <i class="fas fa-lock mt-1"></i>
        <div>
            <strong>Voided and archived.</strong>
            This invoice is permanently locked and cannot be reopened, edited, paid, verified, or deleted.
        </div>
    </div>
@endif

<div class="receipt-actions">
    @if(! $invoice->isLocked() && $invoice->payment_status === 'paid' && $invoice->require_handoff_verification)
        @if($invoice->verified_at)
            <span class="badge bg-success align-self-center">Verified {{ $invoice->verified_at->format('M d, Y h:i A') }}</span>
        @else
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#verifyHandover{{ $invoice->id }}"><i class="fas fa-barcode me-1"></i>Verify Packages & Handover</button>
        @endif
    @endif
    @if(! $invoice->isLocked() && in_array($invoice->payment_status, ['unpaid', 'partial']))
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#payInvoice{{ $invoice->id }}">Pay / Close</button>
    @endif
    <a href="{{ route('courier.invoices.download', $invoice) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    @if(! $invoice->isLocked())
        <a href="{{ route('courier.invoices.edit', $invoice) }}" class="btn btn-primary">Edit</a>
        <form method="POST" action="{{ route('courier.invoices.void', $invoice) }}" data-confirm="Void this invoice?">@csrf <button class="btn btn-outline-danger">Void</button></form>
        <form method="POST" action="{{ route('courier.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this invoice?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    @endif
</div>

@if(! $invoice->isLocked() && in_array($invoice->payment_status, ['unpaid', 'partial']))
    @include('courier.invoices._pay_modal', ['invoice' => $invoice, 'paymentMethods' => $paymentMethods])
@endif

@include('invoices.receipt-card', ['invoice' => $invoice])

<div class="panel table-responsive mt-4">
    <div class="p-3 border-bottom">
        <h2 class="h5 mb-0">Payment Proof Receipts</h2>
    </div>
    <table class="table align-middle mb-0">
        <thead><tr><th>Payment</th><th>Method</th><th>Status</th><th>Reference</th><th class="text-end">Applied</th><th class="text-end">Received</th><th class="text-end">Change</th><th>Receipt</th></tr></thead>
        <tbody>
            @forelse($invoice->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}<br><span class="text-muted small">{{ optional($payment->paid_at)->format('M d, Y h:i A') ?: '-' }}</span></td>
                    <td>{{ optional($payment->method)->name ?: '-' }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ $payment->reference_number ?: '-' }}</td>
                    <td class="text-end">JMD {{ number_format($payment->amount, 2) }}</td>
                    <td class="text-end">{{ (float) $payment->amount_received > 0 ? 'JMD '.number_format($payment->amount_received, 2) : '-' }}</td>
                    <td class="text-end">
                        @if((float) $payment->change_due > 0)
                            JMD {{ number_format($payment->change_due, 2) }}<br>
                            <span class="small text-muted">{{ $payment->change_to_wallet ? 'Credited to wallet' : 'Returned to customer' }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($payment->receipt_path)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-receipt-preview="{{ route('courier.finance.payments.receipt', $payment) }}"><i class="fas fa-eye me-1"></i>Preview</button>
                            @if($payment->status !== 'approved')
                                <form method="POST" action="{{ route('courier.finance.payments.approve', $payment) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Approve & Attach</button>
                                </form>
                            @endif
                        @else
                            <span class="text-muted">No receipt attached</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No payment receipts attached yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('courier.finance._receipt-preview-modal')

@if(! $invoice->isLocked() && $invoice->payment_status === 'paid' && $invoice->require_handoff_verification && ! $invoice->verified_at)
    <div class="modal fade invoice-checkout-modal" id="verifyHandover{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form method="POST" action="{{ route('courier.invoices.verify', $invoice) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Scan Packages Out</h5>
                        <div class="text-muted small">{{ $invoice->invoice_number }} / {{ optional(optional($invoice->customer)->user)->full_name }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Tracking Numbers / HAWB Barcodes</label>
                    <textarea name="tracking_numbers" class="form-control" rows="6" placeholder="Scan or paste one tracking number per line" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" required autofocus></textarea>
                    <div class="form-text">Every package on this invoice must be scanned before handover. This field is intentionally never prefilled.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fas fa-barcode me-1"></i>Verify & Complete Handover</button>
                </div>
            </form>
        </div>
    </div>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-handoff-toggle]').forEach(function (toggle) {
        const modal = toggle.closest('.modal');
        const panel = modal ? modal.querySelector('[data-handoff-panel]') : null;
        const sync = function () {
            if (panel) panel.style.display = toggle.checked ? '' : 'none';
        };
        toggle.addEventListener('change', sync);
        sync();
    });
});
</script>
@endsection

