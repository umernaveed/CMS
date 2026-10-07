<form class="row g-2 mb-3">
    <div class="col-md-4"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Reference or notes"></div>
    <div class="col-md-2"><select name="type" class="form-select"><option value="">All types</option><option value="credit" @selected(request('type') === 'credit')>Credit</option><option value="adjustment" @selected(request('type') === 'adjustment')>Adjustment</option><option value="debit" @selected(request('type') === 'debit')>Debit</option><option value="applied" @selected(request('type') === 'applied')>Applied to bill</option></select></div>
    <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
    <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<div class="panel table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Notes</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            <tr><td>{{ optional($transaction->created_at)->format('M d, Y h:i A') }}</td><td>{{ ucfirst($transaction->type) }}</td><td>{{ $transaction->reference_number ?: '-' }}</td><td>{{ $transaction->notes ?: '-' }}</td><td class="text-end {{ (float) $transaction->amount_jmd >= 0 ? 'text-success' : 'text-danger' }}">JMD {{ number_format((float) $transaction->amount_jmd, 2) }}</td><td class="text-end">JMD {{ number_format((float) $transaction->balance_after_jmd, 2) }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No wallet transactions found.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if(method_exists($transactions, 'links'))<div class="p-3">{{ $transactions->links('pagination::bootstrap-5') }}</div>@endif
</div>
