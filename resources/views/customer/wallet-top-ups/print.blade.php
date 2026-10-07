<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Top-up History</title>
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
    <h1>Wallet Top-up History</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead>
            <tr><th>Date</th><th>Amount</th><th>Points Awarded</th><th>Method</th><th>Reference</th><th>Description</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse($topUps as $topUp)
                <tr>
                    <td>{{ optional($topUp->created_at)->format('M d, Y h:i A') }}</td>
                    <td>JMD {{ number_format((float) $topUp->amount, 2) }}</td>
                    <td>{{ $topUp->status === 'approved' ? number_format((float) $topUp->points_awarded, 2) : '-' }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $topUp->payment_method)) }}</td>
                    <td>{{ $topUp->reference_number ?: '-' }}</td>
                    <td>{{ $topUp->description ?: '-' }}</td>
                    <td>{{ $topUp->status === 'cancelled' ? 'Rejected' : ucfirst($topUp->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No top-up submissions found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
