@php
    $companyName = $platformSettings['company_name'] ?? config('app.name', 'Courier SaaS');
    $companyAddress = $platformSettings['company_address'] ?? '';
    $companyEmail = $platformSettings['company_email'] ?? ($platformSettings['mail_from_address'] ?? config('mail.from.address'));
    $companyPhone = $platformSettings['company_phone'] ?? '';
    $banking = trim((string) ($platformSettings['courier_bill_payment_instructions'] ?? ''));
    $exchangeRate = (float) optional($invoice->items->first())->exchange_rate_jmd;
    $isSeaInvoice = $invoice->items->contains(fn ($item) => in_array($item->weight_unit, ['cuft', 'cbm'], true));
    $weightLb = $invoice->items->sum(fn ($item) => $item->weight_unit === 'lb' ? (float) $item->weight : (float) $item->weight * 2.20462262);
    $weightKg = $invoice->items->sum(fn ($item) => $item->weight_unit === 'kg' ? (float) $item->weight : (float) $item->weight * 0.45359237);
    $volumeCuft = $invoice->items->sum(fn ($item) => $item->weight_unit === 'cbm' ? (float) $item->weight * 35.3147 : ($item->weight_unit === 'cuft' ? (float) $item->weight : 0));
    $volumeCbm = $volumeCuft / 35.3147;
    $freightWeight = $invoice->items->sum(fn ($item) => (float) $item->freight_weight);
    $freightUnit = optional($invoice->items->first())->freight_unit ?: 'kg';
    $freightRate = (float) optional($invoice->items->first())->freight_rate;
    $warehouseUsd = $invoice->items->sum(fn ($item) => (float) $item->warehouse_fee);
    $serviceUsd = $invoice->items->sum(fn ($item) => (float) $item->service_fee);
    $freightUsd = $invoice->items->sum(fn ($item) => (float) $item->freight_charge);
    $totalUsd = $invoice->items->sum(fn ($item) => (float) $item->amount_usd);
    $status = ucfirst(str_replace('_', ' ', $invoice->status));
    $paymentUrl = $paymentUrl ?? optional($invoice->tenant)->portalUrl();
    $formatMeasure = function ($item) {
        $measure = (float) $item->weight;
        if ($item->weight_unit === 'cbm') {
            return number_format($measure * 35.3147, 2).' cu.ft / '.number_format($measure, 3).' CBM';
        }
        if ($item->weight_unit === 'cuft') {
            return number_format($measure, 2).' cu.ft / '.number_format($measure / 35.3147, 3).' CBM';
        }

        $lb = $item->weight_unit === 'kg' ? $measure * 2.20462262 : $measure;
        $kg = $item->weight_unit === 'lb' ? $measure * 0.45359237 : $measure;

        return number_format($lb, 2).' lbs / '.number_format($kg, 2).' kg';
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { size: letter; margin: .5cm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #1e293b; font-family: Arial, sans-serif; font-size: 12px; }
        .bar { max-width: 980px; margin: 18px auto 10px; text-align: right; }
        .btn { display: inline-block; background: #2563eb; color: #fff; border-radius: 8px; padding: 10px 14px; text-decoration: none; border: 0; font-weight: 700; cursor: pointer; }
        .invoice { max-width: 980px; margin: 0 auto 24px; background: #fff; border-radius: 12px; box-shadow: 0 18px 40px rgba(15,23,42,.14); overflow: hidden; }
        .head { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 22px; display: grid; grid-template-columns: 1.1fr 1fr 1.6fr; gap: 18px; }
        .block { border-right: 1px solid #e2e8f0; padding-right: 14px; min-height: 120px; }
        .block:last-child { border-right: 0; padding-right: 0; }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 22px; line-height: 1.1; color: #0f172a; }
        h2 { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: 8px; }
        .muted { color: #64748b; }
        .small { font-size: 11px; line-height: 1.45; }
        .details { display: grid; grid-template-columns: 88px 1fr; gap: 4px 8px; }
        .status { display: inline-block; border-radius: 99px; padding: 4px 8px; font-weight: 800; font-size: 10px; border: 1px solid #cbd5e1; }
        .paid { color: #166534; background: #dcfce7; border-color: #86efac; }
        .partial { color: #92400e; background: #fef3c7; border-color: #fcd34d; }
        .unpaid { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
        .void { color: #475569; background: #f1f5f9; border-color: #cbd5e1; }
        .body { padding: 22px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; text-align: left; font-size: 11px; padding: 7px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 6px 7px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        .right { text-align: right; }
        .mono { font-family: Consolas, monospace; font-size: 10px; }
        .section { margin-bottom: 18px; }
        .section h3 { font-size: 13px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 8px; }
        .bottom { display: grid; grid-template-columns: 1.4fr .9fr; gap: 22px; border-top: 1px solid #e2e8f0; padding-top: 18px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; white-space: pre-line; }
        .summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        .sum-row { display: flex; justify-content: space-between; gap: 12px; margin: 7px 0; }
        .total { background: #2563eb; color: #fff; border-radius: 8px; padding: 10px; font-weight: 900; }
        .footer { background: #1e293b; color: #cbd5e1; text-align: center; padding: 12px; }
        @media print {
            body { background: #fff; font-size: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .bar, .pay-online { display: none !important; }
            .invoice { max-width: none; margin: 0; box-shadow: none; border-radius: 0; }
            .head, .body { padding: 12px; }
            th { font-size: 9px; padding: 4px; }
            td { font-size: 9px; padding: 3px 4px; }
            .section { margin-bottom: 10px; }
            .box, .summary { background: #fff; }
            .footer { background: #fff; color: #64748b; border-top: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body>
    <div class="bar"><button class="btn" onclick="window.print()">Download PDF / Print</button></div>
    <main class="invoice">
        <section class="head">
            <div class="block">
                <h1>{{ $companyName }}</h1>
                <div class="small muted" style="margin-top:8px;">{{ $companyAddress ?: '-' }}<br>{{ $companyEmail ?: '-' }}<br>{{ $companyPhone ?: '-' }}</div>
            </div>
            <div class="block">
                <h2>Billed To</h2>
                <div class="small"><strong>{{ optional($invoice->tenant)->company_name ?: '-' }}</strong><br>{{ optional($invoice->tenant)->address ?: '-' }}<br>{{ optional($invoice->tenant)->email ?: '-' }}<br>{{ optional($invoice->tenant)->phone ?: '-' }}</div>
            </div>
            <div class="block">
                <div class="details small">
                    <h2 style="grid-column:1 / -1;">Invoice Details</h2>
                    <span class="muted">Invoice No:</span><strong class="right">{{ $invoice->invoice_number }}</strong>
                    <span class="muted">Date:</span><strong class="right">{{ $invoice->created_at->format('M d, Y') }}</strong>
                    <span class="muted">Due Date:</span><strong class="right">{{ $invoice->created_at->copy()->addDays(14)->format('M d, Y') }}</strong>
                    <span class="muted">AWB / BL:</span><strong class="right">{{ optional($invoice->manifest)->warehouse_reference ?: optional($invoice->manifest)->manifest_number }}</strong>
                    <span class="muted">Status:</span><strong class="right"><span class="status {{ $invoice->status }}">{{ $status }}</span></strong>
                </div>
            </div>
        </section>
        <section class="body">
            <div class="section">
                <h3>Shipment Details</h3>
                <table>
                    <thead><tr><th>Packages</th><th>{{ $isSeaInvoice ? 'Total Volume' : 'Total Weight' }}</th><th>Freight (US${{ number_format($freightRate, 2) }}/{{ $freightUnit }})</th><th>Service Fee (USD)</th><th class="right">Total Cost</th></tr></thead>
                    <tbody><tr><td>{{ $invoice->items->count() }}</td><td>{{ $isSeaInvoice ? number_format($volumeCuft, 2).' cu.ft / '.number_format($volumeCbm, 3).' CBM' : number_format($weightLb, 2).' lbs / '.number_format($weightKg, 2).' kg' }}</td><td>{{ number_format($freightWeight, 2) }} {{ $freightUnit }} / US${{ number_format($freightUsd, 2) }}</td><td>{{ $invoice->items->count() }} pkg / US${{ number_format($serviceUsd, 2) }}</td><td class="right">US${{ number_format($totalUsd, 2) }} / JM${{ number_format((float) $invoice->subtotal, 2) }}</td></tr></tbody>
                </table>
            </div>
            <div class="section">
                <h3>Packages Details</h3>
                <table>
                    <thead><tr><th>HAWB</th><th>Tracking #</th><th>Measure</th><th class="right">Warehouse Fee</th><th class="right">Freight</th><th class="right">Total (USD)</th></tr></thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr><td>{{ $item->hawbNumber() ?: '-' }}</td><td class="mono">{{ $item->displayTrackingNumber() }}</td><td>{{ $formatMeasure($item) }}</td><td class="right">US${{ number_format((float) $item->warehouse_fee, 2) }}</td><td class="right">US${{ number_format((float) $item->freight_charge, 2) }}</td><td class="right">US${{ number_format((float) $item->amount_usd, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bottom">
                <div>
                    @if($banking)<div class="box"><strong>Payment Instructions</strong><br>{{ $banking }}</div>@endif
                    <div class="box" style="margin-top:10px;"><strong>Important Notes</strong><br>Exchange rate applied: JM${{ number_format($exchangeRate, 4) }} to US$1.00<br>Please include invoice number on all payments.</div>
                    <div class="pay-online" style="margin-top:12px;"><a class="btn" href="{{ $paymentUrl }}">Pay / Upload Proof</a></div>
                </div>
                <div class="summary">
                    <h3>Invoice Summary</h3>
                    @if($warehouseUsd > 0)<div class="sum-row"><span>Warehouse Fee</span><strong>US${{ number_format($warehouseUsd, 2) }}</strong></div>@endif
                    @if($serviceUsd > 0)<div class="sum-row"><span>Service Fee</span><strong>US${{ number_format($serviceUsd, 2) }}</strong></div>@endif
                    @if($freightUsd > 0)<div class="sum-row"><span>Freight</span><strong>US${{ number_format($freightUsd, 2) }}</strong></div>@endif
                    @if((float) $invoice->discount_jmd > 0)<div class="sum-row"><span>Discount</span><strong>JM${{ number_format((float) $invoice->discount_jmd, 2) }} CR</strong></div>@endif
                    @if((float) $invoice->amount_paid_jmd > 0)<div class="sum-row"><span>Paid</span><strong>JM${{ number_format((float) $invoice->amount_paid_jmd, 2) }}</strong></div>@endif
                    <div class="sum-row"><span>Total Due (USD)</span><strong>US${{ number_format($totalUsd, 2) }}</strong></div>
                    <div class="sum-row total"><span>Total Due (JMD)</span><strong>JM${{ number_format((float) $invoice->balance_jmd, 2) }}</strong></div>
                </div>
            </div>
        </section>
        <div class="footer">Thank you for choosing {{ $companyName }}. Questions? Contact {{ $companyEmail }}.</div>
    </main>
</body>
</html>
