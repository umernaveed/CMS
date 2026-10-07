<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifests</title>
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
    <h1>Manifests</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead><tr><th>Manifest</th><th>Cargo</th><th>Customs</th><th>Loading Port</th><th>Departure</th><th>Packages</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($manifests as $manifest)
                <tr>
                    <td>{{ $manifest->manifest_number }}</td>
                    <td>{{ $manifest->cargo_company ?: '-' }}</td>
                    <td>{{ optional($manifest->customsOffice)->name ?: '-' }}</td>
                    <td>{{ optional($manifest->loadingPort)->name ?: '-' }}</td>
                    <td>{{ optional($manifest->date_of_departure)->format('M d, Y') ?? '-' }}</td>
                    <td>{{ $manifest->items_count }}</td>
                    <td>{{ ucfirst($manifest->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No manifests found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
