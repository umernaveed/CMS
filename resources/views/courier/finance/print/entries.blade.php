@include('courier.finance.print._head', ['title' => 'Income / Expense'])
<table style="margin-bottom: 16px;"><thead><tr><th>Metric</th><th>Amount</th></tr></thead><tbody>
<tr><td>Total income</td><td>JMD {{ number_format($summary['income'] ?? 0, 2) }}</td></tr>
<tr><td>Total expense</td><td>JMD {{ number_format($summary['expenses'] ?? 0, 2) }}</td></tr>
<tr><td>Profit / Loss</td><td>JMD {{ number_format($summary['profitLoss'] ?? 0, 2) }}</td></tr>
</tbody></table>
<table><thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td>{{ optional($entry->entry_date)->format('M d, Y') }}</td><td>{{ ucfirst($entry->type) }}</td><td>{{ $entry->category }}</td><td>{{ $entry->description }}</td><td>JMD {{ number_format($entry->amount, 2) }}</td></tr>@empty<tr><td colspan="5">No entries found.</td></tr>@endforelse
</tbody></table>@include('courier.finance.print._foot')
