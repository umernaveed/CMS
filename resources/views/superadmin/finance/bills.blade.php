@extends('layouts.app')

@section('title', 'Billing')
@section('page_title', 'Billing')
@section('breadcrumb', 'Finance / Billing')

@section('content')
@php
    $canGenerateManifest = auth()->user()->hasPermission('manage_manifests');
@endphp
<style>
    .billing-table-wrap { max-height: 62vh; overflow: auto; }
    .billing-table-wrap thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; }
    tr[id^="logistics-invoice-"]:target { outline: 3px solid #2563eb; outline-offset: -3px; background: #eff6ff; }
    .billing-type-pill { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
    .billing-doc-form { min-width: 230px; }
</style>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
    <div>
        <h1 class="h3 mb-1">Billing</h1>
        <p class="text-muted mb-0">System Admin bills and shipment invoices in one place.</p>
    </div>
    <a class="btn btn-outline-primary" href="{{ route('courier.finance.wallet') }}">View Wallet History</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Total Bills</div><div class="h4 mb-0">JMD {{ number_format($summary['total'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Paid</div><div class="h4 mb-0 text-success">JMD {{ number_format($summary['paid'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Outstanding</div><div class="h4 mb-0 text-danger">JMD {{ number_format($summary['outstanding'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Credit Balance</div><div class="h4 mb-0 text-primary">JMD {{ number_format($summary['creditBalance'], 2) }}</div></div></div>
</div>

<form class="row g-2 mb-3">
    <div class="col-md-5"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Bill, invoice, manifest, AWB"></div>
    <div class="col-md-4"><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['issued','unpaid','under_review','partial','paid','cancelled','void'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="panel table-responsive billing-table-wrap">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Details</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Balance</th>
                <th>Date</th>
                <th>Documents</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($billingRecords as $entry)
                @if($entry['type'] === 'bill')
                    @php($bill = $entry['record'])
                    <tr>
                        <td><strong>{{ $bill->bill_number }}</strong><br><span class="text-muted small">{{ $bill->title }}</span></td>
                        <td><span class="badge text-bg-primary billing-type-pill"><i class="fas fa-file-invoice-dollar"></i>Bill</span></td>
                        <td>{{ $bill->category }}<br><span class="text-muted small">{{ ucfirst(str_replace('_', ' ', $bill->source_type)) }}</span></td>
                        <td><span class="badge bg-{{ $bill->isLocked() ? 'secondary' : ($bill->status === 'paid' ? 'success' : ($bill->status === 'under_review' ? 'info' : ($bill->status === 'partial' ? 'warning text-dark' : 'secondary'))) }}">{{ $bill->isLocked() ? 'Voided / Archived' : ucfirst(str_replace('_', ' ', $bill->status)) }}</span></td>
                        <td class="text-end">JMD {{ number_format($bill->total_jmd, 2) }}</td>
                        <td class="text-end">JMD {{ number_format($bill->amount_paid_jmd, 2) }}</td>
                        <td class="text-end">JMD {{ number_format($bill->balance_jmd, 2) }}</td>
                        <td>{{ optional($bill->issue_date)->format('M d, Y') }}<br><span class="text-muted small">Due {{ optional($bill->due_date)->format('M d, Y') ?: '-' }}</span></td>
                        <td><span class="text-muted small">Open bill to upload proof.</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('courier.finance.bills.show', $bill) }}">{{ $bill->isLocked() ? 'View Archive' : 'View' }}</a></td>
                    </tr>
                @else
                    @php
                        $invoice = $entry['record'];
                        $sourceManifest = $invoice->manifest;
                        $sourceShipment = $sourceManifest?->shipment;
                        $masterAwb = $sourceShipment?->master_number ?: $sourceManifest?->warehouse_reference ?: $sourceManifest?->manifest_number ?: '-';
                        $generatedManifests = $invoice->items->map(fn ($item) => $item->package?->courierPackage?->manifest)->filter()->unique('id')->values();
                        $generatedManifest = $generatedManifests->first();
                    @endphp
                    <tr id="logistics-invoice-{{ $invoice->id }}">
                        <td><strong>{{ $invoice->invoice_number }}</strong><br><span class="text-muted small">System: {{ optional($sourceManifest)->manifest_number ?: '-' }}</span></td>
                        <td><span class="badge text-bg-info billing-type-pill"><i class="fas fa-ship"></i>Shipment Invoice</span></td>
                        <td>
                            <strong>{{ $masterAwb }}</strong><br>
                            <span class="text-muted small">{{ ucfirst($invoice->billing_scope) }} | {{ $invoice->items->count() }} items</span>
                            @if($generatedManifest)
                                <br><a class="small" href="{{ route('courier.manifests.show', $generatedManifest) }}">Courier: {{ $generatedManifest->manifest_number }}</a>
                            @endif
                        </td>
                        <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : ($invoice->status === 'under_review' ? 'bg-info' : ($invoice->status === 'void' ? 'bg-secondary' : 'bg-warning text-dark')) }}">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</span></td>
                        <td class="text-end">JMD {{ number_format($invoice->total, 2) }}</td>
                        <td class="text-end">JMD {{ number_format($invoice->amount_paid_jmd, 2) }}</td>
                        <td class="text-end">JMD {{ number_format($invoice->balance_jmd, 2) }}</td>
                        <td>{{ optional($invoice->created_at)->format('M d, Y') }}<br><span class="text-muted small">Paid {{ optional($invoice->paid_at)->format('M d, Y h:i A') ?: '-' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('courier.finance.logistics-invoices.supplier-invoice', $invoice) }}" enctype="multipart/form-data" class="billing-doc-form d-flex gap-2 flex-wrap mb-2">
                                @csrf
                                <input type="file" name="supplier_invoice" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                <button class="btn btn-sm btn-outline-primary">{{ $invoice->supplier_invoice_path ? 'Replace' : 'Upload' }}</button>
                            </form>
                            @if($invoice->supplier_invoice_original_name)
                                <a href="{{ route('courier.finance.logistics-invoices.supplier-invoice.view', $invoice) }}" target="_blank" class="text-muted small d-inline-block mb-2">{{ $invoice->supplier_invoice_original_name }}</a>
                            @endif
                            @if(! in_array($invoice->status, ['paid','void'], true))
                                <form method="POST" action="{{ route('courier.finance.logistics-invoices.receipt', $invoice) }}" enctype="multipart/form-data" class="billing-doc-form d-flex gap-2 flex-wrap">
                                    @csrf
                                    <input type="file" name="receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                    <button class="btn btn-sm btn-outline-primary">Proof</button>
                                </form>
                            @elseif($invoice->receipt_path)
                                <span class="text-muted small">Proof uploaded</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-print me-1"></i>Print</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a href="{{ route('courier.finance.logistics-invoices.download', $invoice) }}" target="_blank" class="dropdown-item">Full Invoice</a></li>
                                        <li><a href="{{ route('courier.finance.logistics-invoices.thermal', $invoice) }}" target="_blank" class="dropdown-item">View Thermal</a></li>
                                        <li><a href="{{ route('courier.finance.logistics-invoices.thermal.download', $invoice) }}" class="dropdown-item">Download Thermal</a></li>
                                    </ul>
                                </div>
                                @if($generatedManifest)
                                    <a class="btn btn-sm btn-primary" href="{{ route('courier.manifests.show', $generatedManifest) }}">Open Manifest</a>
                                @elseif($canGenerateManifest)
                                    <form method="POST" action="{{ route('courier.finance.logistics-invoices.manifest', $invoice) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-primary">Generate Manifest</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No bills or shipment invoices have been issued yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3">{{ $billingRecords->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
