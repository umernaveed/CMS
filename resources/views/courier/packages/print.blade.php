<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Printout</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { color: #64748b; margin: 0 0 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>Packages</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead>
            <tr><th>Tracking</th><th>Customer</th><th>Status</th><th>Freight</th><th>Weight / Volume</th><th>Received</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse($packages as $package)
                @php($packageInvoiceItem = $package->activeInvoiceItem())
                <tr>
                    <td><strong>{{ $package->tracking_number }}</strong><br>{{ $package->supplier_tracking }}</td>
                    <td>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}<br>{{ $package->mailbox }}</td>
                    <td>{{ optional($package->status)->name ?? 'No status' }}</td>
                    <td>{{ ucfirst($package->freight_type) }} / {{ ucfirst($package->delivery_type) }}</td>
                    <td>{{ $package->measureDisplay() }}</td>
                    <td>{{ optional($package->date_received)->format('M d, Y') ?? '-' }}</td>
                    <td>@if($packageInvoiceItem) JMD {{ number_format((float) $packageInvoiceItem->total_jmd, 2) }} @else - @endif</td>
                </tr>
            @empty
                <tr><td colspan="7">No packages found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>

