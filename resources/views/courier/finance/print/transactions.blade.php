@include('courier.finance.print._head', ['title' => 'Transactions'])
<table><thead><tr><th>Invoice</th><th>Customer</th><th>Packages</th><th>Paid</th><th>Total</th><th>Verified</th></tr></thead><tbody>
@forelse($transactions as $invoice)<tr><td>{{ $invoice->invoice_number }}</td><td>{{ optional(optional($invoice->customer)->user)->full_name ?? '-' }}</td><td>{{ $invoice->items->count() }}</td><td>{{ optional($invoice->paid_at)->format('M d, Y') ?? '-' }}</td><td>JMD {{ number_format($invoice->total_jmd, 2) }}</td><td>{{ $invoice->verified_at ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="6">No transactions found.</td></tr>@endforelse
</tbody></table>@include('courier.finance.print._foot')
