<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expense & Income Print</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 13px; margin: 28px; }
        h1 { font-size: 24px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 22px 0; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 14px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .value { font-size: 20px; font-weight: 700; margin-top: 6px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 9px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .text-end { text-align: right; }
        .income { color: #047857; }
        .expense { color: #b91c1c; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 18px;">
        <button onclick="window.print()">Print</button>
    </div>

    <h1>Expense & Income Report</h1>
    <div class="muted">Generated {{ now()->format('M d, Y h:i A') }}</div>

    <div class="summary">
        <div class="card">
            <div class="label">Total Income</div>
            <div class="value income">JMD {{ number_format($summary['income'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Total Expenses</div>
            <div class="value expense">JMD {{ number_format($summary['expenses'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Profit / Loss</div>
            <div class="value {{ $summary['profitLoss'] >= 0 ? 'income' : 'expense' }}">JMD {{ number_format($summary['profitLoss'], 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Reference</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($entries as $entry)
            <tr>
                <td>{{ optional($entry->entry_date)->format('M d, Y') }}</td>
                <td class="{{ $entry->type === 'income' ? 'income' : 'expense' }}">{{ ucfirst($entry->type) }}</td>
                <td>{{ $entry->category }}</td>
                <td>{{ $entry->description ?: '-' }}</td>
                <td>{{ $entry->reference_number ?: '-' }}</td>
                <td class="text-end">JMD {{ number_format($entry->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:24px;">No entries found.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
