<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $bill->bill_number }}</title>
    @php
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
        $paymentDetails = trim((string) ($settings['courier_bill_payment_instructions'] ?? $settings['banking_details'] ?? $settings['company_banking_details'] ?? ''));
    @endphp
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        .bill-document {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 23px 18px;
        }
        .bill-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid #111827;
        }
        .bill-brand {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .bill-logo {
            width: 52px;
            height: 52px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
            flex: 0 0 auto;
        }
        .bill-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .bill-logo-fallback {
            font-size: 18px;
            font-weight: 800;
        }
        .bill-company h2,
        .bill-title h1 {
            margin: 0;
            color: #0f172a;
            font-weight: 800;
        }
        .bill-company h2 {
            font-size: 18px;
            line-height: 1.15;
        }
        .bill-muted {
            color: #475569;
        }
        .bill-title {
            text-align: right;
        }
        .bill-title h1 {
            font-size: 26px;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .bill-meta {
            display: grid;
            gap: 6px;
            margin-top: 10px;
            margin-left: auto;
            max-width: 250px;
            text-align: left;
        }
        .bill-meta-row {
            display: grid;
            grid-template-columns: 100px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
        }
        .bill-meta-row span,
        .bill-summary-row span {
            color: #64748b;
        }
        .bill-meta-row strong,
        .bill-summary-row strong {
            color: #0f172a;
            text-align: right;
        }
        .bill-status {
            display: inline-flex;
            justify-content: center;
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .bill-status.paid { background: #dcfce7; color: #166534; }
        .bill-status.cancelled { background: #e5e7eb; color: #374151; }
        .bill-status.review { background: #dbeafe; color: #1e40af; }
        .bill-status.due { background: #fef3c7; color: #92400e; }
        .bill-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 20px;
        }
        .bill-box {
            min-height: 116px;
            border: 1px solid #dfe3e8;
            border-radius: 5px;
            background: #f8fafc;
            padding: 12px;
        }
        .bill-section-title {
            margin: 18px 0 8px;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .bill-box-title {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .bill-table th {
            background: #111827;
            color: #fff;
            padding: 12px 8px;
            font-size: 10px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .bill-table th:nth-child(1) { width: 68%; }
        .bill-table th:nth-child(2) { width: 6%; }
        .bill-table th:nth-child(3) { width: 14%; }
        .bill-table th:nth-child(4),
        .bill-table th:nth-child(5) { width: 6%; }
        .bill-table td {
            padding: 11px 8px;
            vertical-align: top;
            color: #0f172a;
        }
        .bill-table .text-end,
        .text-end {
            text-align: right;
        }
        .bill-description {
            color: #475569;
            white-space: pre-line;
        }
        .bill-payment-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 285px;
            gap: 18px;
            align-items: start;
            margin-top: 30px;
        }
        .bill-payment-box {
            min-height: 175px;
        }
        .bill-summary {
            border: 1px solid #dfe3e8;
            border-radius: 5px;
            overflow: hidden;
            background: #fff;
        }
        .bill-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .bill-summary-row:last-child {
            border-bottom: 0;
        }
        .bill-summary-row.total {
            background: #111827;
        }
        .bill-summary-row.total span,
        .bill-summary-row.total strong {
            color: #fff;
            font-weight: 800;
        }
        .bill-footer {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #dfe3e8;
            color: #475569;
            font-size: 11px;
        }
        .bill-print-actions {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 23px;
            background: rgba(248, 250, 252, .96);
            border-bottom: 1px solid #e5e7eb;
        }
        .bill-print-actions button {
            border: 1px solid #0f766e;
            border-radius: 5px;
            background: #0f766e;
            color: #fff;
            padding: 7px 12px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        @media (max-width: 760px) {
            .bill-document { padding: 18px 14px; }
            .bill-header,
            .bill-grid,
            .bill-payment-grid {
                grid-template-columns: 1fr;
            }
            .bill-title { text-align: left; }
            .bill-meta { margin-left: 0; max-width: none; }
            .bill-table { min-width: 760px; }
            .bill-table-wrap { overflow-x: auto; }
        }
        @media print {
            .bill-print-actions { display: none; }
            .bill-document {
                max-width: none;
                padding: 24px 23px 18px;
            }
        }
    </style>
</head>
<body>
    <div class="bill-print-actions">
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="bill-document">
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
                    <div class="bill-meta-row"><span>Bill No.</span><strong>{{ $bill->bill_number }}</strong></div>
                    <div class="bill-meta-row"><span>Issue Date</span><strong>{{ optional($bill->issue_date)->format('M d, Y') ?: '-' }}</strong></div>
                    <div class="bill-meta-row"><span>Due Date</span><strong>{{ optional($bill->due_date)->format('M d, Y') ?: '-' }}</strong></div>
                    <div class="bill-meta-row"><span>Category</span><strong>{{ $bill->category ?: '-' }}</strong></div>
                    <div class="bill-meta-row"><span>Status</span><strong><span class="bill-status {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $bill->status)) }}</span></strong></div>
                </div>
            </div>
        </header>

        <section class="bill-grid">
            <div class="bill-box">
                <span class="bill-box-title">From</span>
                <strong>{{ $platformName }}</strong>
                <div class="bill-muted">
                    @if($platformAddress){{ $platformAddress }}<br>@endif
                    @if($platformEmail){{ $platformEmail }}<br>@endif
                    @if($platformPhone){{ $platformPhone }}@endif
                </div>
            </div>
            <div class="bill-box">
                <span class="bill-box-title">Billed To</span>
                <strong>{{ optional($tenant)->company_name ?: 'Courier' }}</strong>
                <div class="bill-muted">
                    @if($tenantAddress){{ $tenantAddress }}<br>@endif
                    @if(optional($tenant)->email){{ optional($tenant)->email }}<br>@endif
                    @if(optional($tenant)->phone){{ optional($tenant)->phone }}@endif
                </div>
            </div>
        </section>

        <section>
            <h2 class="bill-section-title">Bill Items</h2>
            <div class="bill-table-wrap">
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
                                    <div class="bill-description">{{ $description }}</div>
                                @endif
                            </td>
                            <td class="text-end">1</td>
                            <td>{{ $period ?: ucfirst(str_replace('_', ' ', $bill->source_type ?: 'service')) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($subtotal ?: $total, 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($subtotal ?: $total, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bill-payment-grid">
            <div class="bill-box bill-payment-box">
                <span class="bill-box-title">Payment Details</span>
                @if($paymentDetails)
                    <div class="bill-muted" style="white-space: pre-line">{{ $paymentDetails }}</div>
                @else
                    <div class="bill-muted">Use the payment proof section below after making payment through your agreed platform payment method.</div>
                @endif
            </div>
            <div class="bill-summary">
                <div class="bill-summary-row"><span>Subtotal</span><strong>{{ $currency }} {{ number_format($subtotal, 2) }}</strong></div>
                <div class="bill-summary-row"><span>Tax</span><strong>{{ $currency }} {{ number_format($tax, 2) }}</strong></div>
                <div class="bill-summary-row"><span>Total</span><strong>{{ $currency }} {{ number_format($total, 2) }}</strong></div>
                <div class="bill-summary-row"><span>Paid</span><strong>{{ $currency }} {{ number_format($paid, 2) }}</strong></div>
                <div class="bill-summary-row total"><span>Total Due</span><strong>{{ $currency }} {{ number_format($balance, 2) }}</strong></div>
            </div>
        </section>

        <footer class="bill-footer">
            <strong>Payment Terms:</strong> Please submit payment proof for review if this bill is not fully paid.
            @if($bill->notes)
                <br><strong>Notes:</strong> {{ $bill->notes }}
            @endif
        </footer>
    </main>
</body>
</html>
