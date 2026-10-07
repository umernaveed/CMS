<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { color: #64748b; margin: 0 0 16px; }
        table { border-collapse: collapse; font-size: 11px; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>Audit Trail</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>Courier</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP</th>
                <th>Route</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ optional($log->tenant)->company_name ?: 'Platform' }}</td>
                    <td>{{ optional($log->user)->full_name ?: optional($log->user)->email ?: 'System' }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->ip_address }}</td>
                    <td>{{ $log->new_data['route'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No audit records found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
