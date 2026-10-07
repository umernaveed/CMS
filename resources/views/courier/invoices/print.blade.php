<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { color: #64748b; margin: 0 0 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>Invoices</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th>Mailbox</th><th>Status</th><th>Due</th><th>Total</th></tr></thead>
        <tbody>
        @forelse($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ optional(optional($invoice->customer)->user)->full_name ?? 'Unassigned' }}</td>
                <td>{{ $invoice->mailbox }}</td>
                <td>{{ ucfirst($invoice->payment_status) }} / {{ ucfirst($invoice->invoice_status) }}</td>
                <td>{{ optional($invoice->due_date)->format('M d, Y') ?? '-' }}</td>
                <td>JMD {{ number_format($invoice->total_jmd, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No invoices found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
