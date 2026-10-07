@include('courier.finance.print._head', ['title' => $section === 'reports' ? 'Finance Reports' : 'Profit / Loss'])
<table><thead><tr><th>Metric</th><th>Amount</th></tr></thead><tbody>
<tr><td>Invoice income</td><td>JMD {{ number_format($invoiceIncome, 2) }}</td></tr>
<tr><td>Manual income</td><td>JMD {{ number_format($manualIncome, 2) }}</td></tr>
<tr><td>Expenses</td><td>JMD {{ number_format($expenses, 2) }}</td></tr>
<tr><td>Profit / Loss</td><td>JMD {{ number_format($profitLoss, 2) }}</td></tr>
</tbody></table>@include('courier.finance.print._foot')
