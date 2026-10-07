<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Courier Activity Log</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>Courier Activity Log</h1>
    <table>
        <thead><tr><th>Action</th><th>Module</th><th>Description</th><th>User</th><th>When</th></tr></thead>
        <tbody>
            @forelse($activities as $activity)
                <tr>
                    <td>{{ $activity->action }}</td>
                    <td>{{ ucfirst($activity->module) }}</td>
                    <td>{{ $activity->description }}</td>
                    <td>{{ optional($activity->user)->full_name ?: optional($activity->user)->email ?: 'System' }}</td>
                    <td>{{ optional($activity->created_at)->format('M d, Y h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No activity records found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
