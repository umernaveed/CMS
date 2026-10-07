@extends('layouts.app')

@section('title', 'Bill')
@section('content')
@php
    $paymentMethods = ['bank_transfer' => 'Bank Transfer', 'online' => 'Online Payment', 'cash' => 'Cash', 'cheque' => 'Cheque', 'debit_card' => 'Debit Card', 'other' => 'Other'];
    $settings = \App\Models\SystemSetting::pluck('value', 'key');
    $assetUrl = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };
    $platformLogo = $assetUrl($settings['company_logo'] ?? $settings['app_logo'] ?? null);
    $platformName = $settings['company_name'] ?? config('app.name', 'Courier SaaS');
    $platformAddress = collect([$settings['company_address'] ?? null, $settings['company_city'] ?? null, $settings['company_country'] ?? null])->filter()->implode(', ');
    $platformEmail = $settings['company_email'] ?? $settings['support_email'] ?? config('mail.from.address');
    $platformPhone = $settings['company_phone'] ?? $settings['support_phone'] ?? null;
    $tenant = $bill->tenant;
    $tenantAddress = collect([optional($tenant)->address, optional($tenant)->city, optional($tenant)->parish, optional($tenant)->country])->filter()->implode(', ');
    $currency = $bill->currency ?: 'JMD';
    $subtotal = (float) $bill->subtotal_jmd;
    $tax = (float) $bill->tax_jmd;
    $total = (float) $bill->total_jmd;
    $paid = (float) $bill->amount_paid_jmd;
    $balance = (float) $bill->balance_jmd;
    $period = collect([optional($bill->issue_date)->format('M d, Y'), optional($bill->due_date)->format('M d, Y')])->filter()->implode(' - ');
    $description = $bill->public_description ?: $bill->description ?: $bill->title;
    $statusClass = $bill->status === 'paid' ? 'paid' : ($bill->status === 'cancelled' ? 'cancelled' : ($bill->status === 'under_review' ? 'review' : 'due'));
    $bankingDetails = trim((string) ($settings['banking_details'] ?? $settings['company_banking_details'] ?? ''));
@endphp

<style>
    .bill-actions { display:flex; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; }
    .bill-sheet { max-width:980px; margin:0 auto 1.5rem; background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; box-shadow:0 12px 30px rgba(15,23,42,.08); }
    .bill-document { padding:34px; color:#111827; font-family:Arial, Helvetica, sans-serif; }
    .bill-header { display:grid; grid-template-columns:1fr 1fr; gap:28px; padding-bottom:22px; border-bottom:2px solid #111827; }
    .bill-brand { display:flex; gap:14px; align-items:flex-start; }
    .bill-logo { width:68px; height:68px; border:1px solid #e5e7eb; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#fff; }
    .bill-logo img { max-width:100%; max-height:100%; object-fit:contain; }
    .bill-logo-fallback { font-weight:800; font-size:1.2rem; color:#111827; }
    .bill-company h2, .bill-title h1 { margin:0; font-weight:800; color:#111827; }
    .bill-company h2 { font-size:1.35rem; }
    .bill-muted { color:#64748b; font-size:.9rem; line-height:1.5; }
    .bill-title { text-align:right; }
    .bill-title h1 { font-size:1.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .bill-meta { margin-top:12px; display:inline-grid; gap:5px; text-align:left; min-width:280px; }
    .bill-meta div, .bill-summary-row { display:flex; justify-content:space-between; gap:18px; }
    .bill-meta span, .bill-summary-row span { color:#64748b; }
    .bill-meta strong, .bill-summary-row strong { color:#111827; }
    .bill-status { display:inline-flex; padding:.25rem .55rem; border-radius:999px; font-size:.75rem; font-weight:800; text-transform:uppercase; }
    .bill-status.paid { background:#dcfce7; color:#166534; }
    .bill-status.cancelled { background:#e5e7eb; color:#374151; }
    .bill-status.review { background:#dbeafe; color:#1e40af; }
    .bill-status.due { background:#fef3c7; color:#92400e; }
    .bill-section { margin-top:26px; }
    .bill-section h3 { margin:0 0 10px; font-size:.95rem; text-transform:uppercase; letter-spacing:.05em; color:#111827; }
    .bill-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .bill-box { border:1px solid #e5e7eb; border-radius:7px; padding:16px; background:#f8fafc; min-height:100%; }
    .bill-table { width:100%; border-collapse:collapse; margin-top:10px; }
    .bill-table th { background:#111827; color:#fff; padding:11px; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; }
    .bill-table td { padding:13px 11px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
    .bill-table tbody tr:last-child td { border-bottom:0; }
    .bill-table .text-end { text-align:right; }
    .bill-item-description { white-space:pre-line; }
    .bill-summary { margin-left:auto; max-width:380px; border:1px solid #e5e7eb; border-radius:7px; overflow:hidden; }
    .bill-summary-row { padding:11px 14px; border-bottom:1px solid #e5e7eb; }
    .bill-summary-row:last-child { border-bottom:0; }
    .bill-summary-row.total { background:#111827; color:#fff; font-size:1.05rem; }
    .bill-summary-row.total span, .bill-summary-row.total strong { color:#fff; }
    .bill-footer-note { border-top:1px solid #e5e7eb; padding-top:18px; color:#64748b; font-size:.9rem; line-height:1.55; }
    @media (max-width: 768px) {
        .bill-document { padding:20px; }
        .bill-header, .bill-grid { grid-template-columns:1fr; }
        .bill-title { text-align:left; }
        .bill-meta { min-width:0; width:100%; }
    }
    @media print {
        body * { visibility:hidden; }
        .bill-sheet, .bill-sheet * { visibility:visible; }
        .bill-sheet { position:absolute; left:0; top:0; width:100%; max-width:none; margin:0; border:0; box-shadow:none; border-radius:0; }
        .bill-document { padding:22px; }
        .sidebar, .topbar, .bill-actions, .no-print { display:none !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap no-print">
    <div>
        <h1 class="h3 mb-1">{{ $bill->bill_number }}</h1>
        <p class="text-muted mb-0">{{ $bill->title }}</p>
    </div>
    <div class="bill-actions">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print / Download</button>
        <a href="{{ route('courier.finance.bills') }}" class="btn btn-outline-secondary">Back to Billing</a>
    </div>
</div>

<section class="bill-sheet">
    <div class="bill-document">
        <header class="bill-header">
            <div class="bill-brand">
                <div class="bill-logo">
                    @if($platformLogo)
                        <img src="{{ $platformLogo }}" alt="{{ $platformName }} logo">
                    @else
                        <span class="bill-logo-fallback">{{ collect(explode(' ', $platformName))->filter()->take(2)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('') ?: 'CS' }}</span>
                    @endif
                </div>
                <div class="bill-company">
                    <h2>{{ $platformName }}</h2>
                    <div class="bill-muted">
                        @if($platformAddress){{ $platformAddress }}<br>@endif
                        @if($platformEmail){{ $platformEmail }}<br>@endif
                        @if($platformPhone){{ $platformPhone }}@endif
                    </div>
                </div>
            </div>
            <div class="bill-title">
                <h1>Bill</h1>
                <div class="bill-meta">
                    <div><span>Bill No.</span><strong>{{ $bill->bill_number }}</strong></div>
                    <div><span>Issue Date</span><strong>{{ optional($bill->issue_date)->format('M d, Y') ?: '-' }}</strong></div>
                    <div><span>Due Date</span><strong>{{ optional($bill->due_date)->format('M d, Y') ?: '-' }}</strong></div>
                    <div><span>Category</span><strong>{{ $bill->category ?: '-' }}</strong></div>
                    <div><span>Status</span><strong><span class="bill-status {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $bill->status)) }}</span></strong></div>
                </div>
            </div>
        </header>

        <section class="bill-section bill-grid">
            <div class="bill-box">
                <h3>From</h3>
                <strong>{{ $platformName }}</strong>
                <div class="bill-muted mt-1">
                    @if($platformAddress){{ $platformAddress }}<br>@endif
                    @if($platformEmail){{ $platformEmail }}<br>@endif
                    @if($platformPhone){{ $platformPhone }}@endif
                </div>
            </div>
            <div class="bill-box">
                <h3>Billed To</h3>
                <strong>{{ optional($tenant)->company_name ?: 'Courier' }}</strong>
                <div class="bill-muted mt-1">
                    @if($tenantAddress){{ $tenantAddress }}<br>@endif
                    @if(optional($tenant)->email){{ optional($tenant)->email }}<br>@endif
                    @if(optional($tenant)->phone){{ optional($tenant)->phone }}@endif
                </div>
            </div>
        </section>

        <section class="bill-section">
            <h3>Bill Items</h3>
            <table class="bill-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th>Period / Type</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>{{ $bill->title }}</strong>
                            @if($description && $description !== $bill->title)
                                <div class="bill-muted bill-item-description">{{ $description }}</div>
                            @endif
                        </td>
                        <td class="text-end">1</td>
                        <td>{{ $period ?: ucfirst(str_replace('_', ' ', $bill->source_type ?: 'service')) }}</td>
                        <td class="text-end">{{ $currency }} {{ number_format($subtotal ?: $total, 2) }}</td>
                        <td class="text-end">{{ $currency }} {{ number_format($subtotal ?: $total, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="bill-section bill-grid">
            <div class="bill-box">
                <h3>Payment Details</h3>
                @if($bankingDetails)
                    <div class="bill-muted" style="white-space:pre-line">{{ $bankingDetails }}</div>
                @else
                    <div class="bill-muted">Use the payment proof section below after making payment through your agreed platform payment method.</div>
                @endif
            </div>
            <div>
                <div class="bill-summary">
                    <div class="bill-summary-row"><span>Subtotal</span><strong>{{ $currency }} {{ number_format($subtotal, 2) }}</strong></div>
                    <div class="bill-summary-row"><span>Tax</span><strong>{{ $currency }} {{ number_format($tax, 2) }}</strong></div>
                    <div class="bill-summary-row"><span>Total</span><strong>{{ $currency }} {{ number_format($total, 2) }}</strong></div>
                    <div class="bill-summary-row"><span>Paid</span><strong>{{ $currency }} {{ number_format($paid, 2) }}</strong></div>
                    <div class="bill-summary-row total"><span>Total Due</span><strong>{{ $currency }} {{ number_format($balance, 2) }}</strong></div>
                </div>
            </div>
        </section>

        <section class="bill-section bill-footer-note">
            <strong>Payment Terms:</strong>
            Please submit payment proof for review if this bill is not fully paid.
            @if($bill->notes)
                <br><strong>Notes:</strong> {{ $bill->notes }}
            @endif
        </section>
    </div>
</section>

@if(! in_array($bill->status, ['paid','cancelled'], true))
    <div class="panel p-4 mb-4 no-print">
        <h2 class="h5 mb-3">Submit Payment Proof</h2>
        <form method="POST" action="{{ route('courier.finance.bills.payments.store', $bill) }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" step="0.01" min="0.01" max="{{ $bill->balance_jmd }}" name="amount_jmd" class="form-control" value="{{ $bill->balance_jmd }}" required></div>
            <div class="col-md-6"><label class="form-label">Method</label><select name="payment_method" class="form-select" required>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Reference Number</label><input name="reference_number" class="form-control"></div>
            <div class="col-12"><label class="form-label">Payment Receipt</label><input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Upload Receipt For Review</button></div>
        </form>
    </div>
@endif

<div class="panel no-print">
    <div class="p-3 border-bottom">
        <h2 class="h5 mb-0">Payment History</h2>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Submitted</th><th>Method</th><th>Reference</th><th>Status</th><th class="text-end">Amount</th><th>Receipt</th><th>Admin Notes</th></tr></thead>
            <tbody>
            @forelse($bill->payments as $payment)
                <tr>
                    <td>{{ optional($payment->submitted_at)->format('M d, Y h:i A') ?: optional($payment->created_at)->format('M d, Y') }}</td>
                    <td>{{ $paymentMethods[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                    <td>{{ $payment->reference_number ?: '-' }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span></td>
                    <td class="text-end">JMD {{ number_format($payment->amount_jmd, 2) }}</td>
                    <td>@if($payment->receipt_path)<a href="{{ route('courier.finance.bill-payments.receipt', $payment) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Download / Print</a>@else - @endif</td>
                    <td>{{ $payment->admin_notes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No payments submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
