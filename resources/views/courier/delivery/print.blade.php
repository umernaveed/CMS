<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Requests</title>
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
    <h1>Delivery Requests</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead><tr><th>Request</th><th>Customer</th><th>Location</th><th>Packages</th><th>Recipient</th><th>Preferred</th><th>Assigned</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($requests as $request)
            <tr>
                <td>{{ $request->request_number }}<br>{{ ucfirst($request->delivery_type) }}</td>
                <td>{{ optional(optional($request->customer)->user)->full_name ?? '-' }}<br>{{ $request->mailbox }}</td>
                <td>{{ optional($request->area)->name ?? '-' }}<br>JMD {{ number_format($request->delivery_cost, 2) }}</td>
                <td>{{ $request->package_count }} package(s)<br>JMD {{ number_format($request->package_total, 2) }}</td>
                <td>{{ $request->recipient_name }}<br>{{ $request->recipient_phone }}<br>{{ $request->delivery_address }}</td>
                <td>{{ optional($request->preferred_date)->format('M d, Y') ?? '-' }}</td>
                <td>{{ optional($request->assignee)->full_name ?: optional($request->assignee)->email ?: '-' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $request->status)) }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No delivery requests found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
