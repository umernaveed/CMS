<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer List</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .meta { color: #64748b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print</button>
    <h1>Customer List</h1>
    <div class="meta">{{ optional(auth()->user()->tenant)->company_name }} | {{ now()->format('M d, Y h:i A') }}</div>
    <table>
        <thead>
            <tr><th>Customer</th><th>Contact</th><th>Mailbox</th><th>Outlet</th><th>Balance</th><th>Points</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ optional($customer->user)->full_name }}</td>
                    <td>{{ optional($customer->user)->email }}<br>{{ optional($customer->user)->phone }}</td>
                    <td>{{ $customer->mailbox }}</td>
                    <td>{{ optional($customer->outlet)->name ?? '-' }}</td>
                    <td>JMD {{ number_format($customer->balance_due ?? 0, 2) }}</td>
                    <td>{{ number_format($customer->total_points, 2) }}</td>
                    <td>{{ $customer->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No customers match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
