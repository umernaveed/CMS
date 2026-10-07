<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Logistics Packages</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .no-print { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { margin: 0 0 16px; color: #6b7280; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print</button>
    <h1>Logistics Packages</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead><tr><th>Uploaded</th><th>Tracking</th><th>HAWB</th><th>Courier</th><th>Mailbox</th><th>Client</th><th>Weight / Volume</th><th>Total</th><th>Assignment</th><th>Manifest</th></tr></thead>
        <tbody>
            @forelse($packages as $package)
                @php($unknown = $package->location_status === 'inhouse_unknown_room' || ! $package->courier_package_id)
                <tr>
                    <td>{{ optional($package->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $package->tracking_number }}</td>
                    <td>{{ optional($package->courierPackage)->tracking_number ?: '-' }}</td>
                    <td>{{ optional($package->tenant)->company_name ?: '-' }}</td>
                    <td>{{ optional($package->courierPackage)->mailbox ?: '-' }}</td>
                    <td>{{ optional(optional(optional($package->courierPackage)->customer)->user)->full_name ?: '-' }}</td>
                    <td>{{ $package->measureDisplay() }}</td>
                    <td>JMD {{ number_format((float) $package->amount, 2) }}</td>
                    <td>{{ $unknown ? 'Unknown' : 'Assigned' }}</td>
                    <td>{{ optional($package->manifest)->manifest_number ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No logistics packages found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
