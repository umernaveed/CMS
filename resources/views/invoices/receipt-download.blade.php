<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} {{ $invoice->payment_status === 'paid' ? 'Receipt' : 'Invoice' }}</title>
    <style>
        :root {
            --brand: #135fd2;
            --brand-strong: #073b70;
            --line: #e5e7eb;
            --shadow: none;
        }
        body {
            margin: 0;
            padding: 12px;
            background: #fff;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .text-end { text-align: right; }
    </style>
    @include('invoices.receipt-styles')
</head>
<body>
    <div class="receipt-actions">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    @include('invoices.receipt-card', ['invoice' => $invoice])
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
