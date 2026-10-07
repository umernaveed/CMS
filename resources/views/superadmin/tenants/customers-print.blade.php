<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Courier Customers</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .no-print { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { margin: 0 0 16px; color: #6b7280; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print</button>
    <h1>Courier Customers</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Courier</th>
                <th>Mailbox</th>
                <th>Outlet</th>
                <th>Packages</th>
                <th>Invoices</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ optional($customer->user)->full_name ?: 'Unnamed Customer' }}</td>
                    <td>{{ optional($customer->user)->email ?: '-' }}</td>
                    <td>{{ optional($customer->user)->phone ?: '-' }}</td>
                    <td>{{ optional($customer->tenant)->company_name ?: '-' }}</td>
                    <td>{{ $customer->mailbox ?: '-' }}</td>
                    <td>{{ optional($customer->outlet)->name ?: '-' }}</td>
                    <td>{{ number_format($customer->packages_count) }}</td>
                    <td>{{ number_format($customer->invoices_count) }}</td>
                    <td>JMD {{ number_format((float) ($customer->current_balance ?? 0), 2) }}</td>
                    <td>{{ $customer->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No courier customers found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>