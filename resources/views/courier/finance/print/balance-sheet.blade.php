@include('courier.finance.print._head', ['title' => 'Official Balance Sheet'])
<table><thead><tr><th>Section</th><th>Metric</th><th>Amount</th></tr></thead><tbody>
<tr><td>Assets</td><td>Cash and cash equivalents</td><td>JMD {{ number_format($cash, 2) }}</td></tr>
<tr><td>Assets</td><td>Accounts receivable</td><td>JMD {{ number_format($receivables, 2) }}</td></tr>
<tr><td><strong>Assets</strong></td><td><strong>Total assets</strong></td><td><strong>JMD {{ number_format($totalAssets, 2) }}</strong></td></tr>
@forelse($expenseCategories as $category => $amount)<tr><td>Liabilities</td><td>{{ $category }}</td><td>JMD {{ number_format($amount, 2) }}</td></tr>@empty<tr><td>Liabilities</td><td>No liabilities recorded</td><td>JMD 0.00</td></tr>@endforelse
<tr><td><strong>Liabilities</strong></td><td><strong>Total liabilities</strong></td><td><strong>JMD {{ number_format($expenses, 2) }}</strong></td></tr>
<tr><td>Equity</td><td>Retained earnings</td><td>JMD {{ number_format($equity, 2) }}</td></tr>
<tr><td><strong>Liabilities + Equity</strong></td><td><strong>Total liabilities + equity</strong></td><td><strong>JMD {{ number_format($liabilitiesAndEquity, 2) }}</strong></td></tr>
</tbody></table>
@include('courier.finance.print._foot')
