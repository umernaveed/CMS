<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} Report</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { color: #64748b; margin: 0 0 14px; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0; }
        .summary div { border: 1px solid #d1d5db; padding: 8px 10px; min-width: 150px; }
        .summary span { color: #64748b; display: block; font-size: 11px; text-transform: uppercase; }
        table { border-collapse: collapse; font-size: 11px; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>{{ $report['title'] }} Report</h1>
    <p>{{ $report['description'] }}</p>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>

    <div class="summary">
        @foreach($report['summary'] as $label => $value)
            <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach($report['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">No report rows found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
