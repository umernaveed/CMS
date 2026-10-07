@extends('layouts.app')

@section('title', 'Income / Expense')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Income / Expense', 'subtitle' => 'Track manual income and operating expenses.', 'section' => 'entries'])
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('courier.finance.categories') }}" class="btn btn-outline-secondary"><i class="fas fa-tags me-1"></i>Categories</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Total Income</div><div class="h3 mb-0 text-success">JMD {{ number_format($summary['income'] ?? 0, 2) }}</div></div></div>
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Total Expense</div><div class="h3 mb-0 text-danger">JMD {{ number_format($summary['expenses'] ?? 0, 2) }}</div></div></div>
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Profit / Loss</div><div class="h3 mb-0 {{ ($summary['profitLoss'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">JMD {{ number_format($summary['profitLoss'] ?? 0, 2) }}</div></div></div>
</div>
<form method="POST" action="{{ route('courier.finance.entries.store') }}" class="panel p-3 mb-3" data-ledger-form>@csrf
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select" data-ledger-type><option value="income">Income</option><option value="expense">Expense</option></select></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select" data-ledger-category required></select></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
        <div class="col-md-3"><label class="form-label">Description</label><input name="description" class="form-control"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
    </div>
</form>
<form method="GET" class="panel p-3 mb-3"><div class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="">All</option><option value="income" @selected(request('type')==='income')>Income</option><option value="expense" @selected(request('type')==='expense')>Expense</option></select></div>
    <div class="col-md-3"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ request('category') }}"></div>
    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('courier.finance.entries') }}">Reset</a></div>
</div></form>
<div class="panel"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th></th></tr></thead>
    <tbody>@forelse($entries as $entry)<tr>
        <td>{{ optional($entry->entry_date)->format('M d, Y') }}</td>
        <td><span class="badge {{ $entry->type === 'income' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($entry->type) }}</span></td>
        <td>{{ $entry->category }}</td>
        <td>{{ $entry->description ?: '-' }}</td>
        <td class="text-end">JMD {{ number_format($entry->amount, 2) }}</td>
        <td class="text-end">
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#courierEntryEdit{{ $entry->id }}"><i class="fas fa-pen me-1"></i>Edit</button>
                <form method="POST" action="{{ route('courier.finance.entries.destroy', $entry) }}" data-confirm="Delete this entry?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
            </div>
        </td>
    </tr>
    <tr class="collapse" id="courierEntryEdit{{ $entry->id }}">
        <td colspan="6" class="bg-light">
            <form method="POST" action="{{ route('courier.finance.entries.update', $entry) }}" class="row g-2 align-items-end p-2" data-ledger-form>
                @csrf
                @method('PUT')
                <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select" data-ledger-type><option value="income" @selected($entry->type === 'income')>Income</option><option value="expense" @selected($entry->type === 'expense')>Expense</option></select></div>
                <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select" data-ledger-category data-current-category="{{ $entry->category }}" required></select></div>
                <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0" class="form-control" value="{{ $entry->amount }}" required></div>
                <div class="col-md-2"><label class="form-label">Date</label><input name="entry_date" type="date" class="form-control" value="{{ optional($entry->entry_date)->format('Y-m-d') }}" required></div>
                <div class="col-md-3"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $entry->description }}"></div>
                <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-save"></i></button></div>
            </form>
        </td>
    </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No entries found.</td></tr>@endforelse</tbody>
</table></div><div class="p-3">{{ $entries->links('pagination::bootstrap-5') }}</div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ledgerCategories = @json($categories);

    function syncCategoryList(form) {
        const type = form.querySelector('[data-ledger-type]');
        const category = form.querySelector('[data-ledger-category]');
        if (!type || !category) return;
        const current = category.dataset.currentCategory || category.value;
        const options = ledgerCategories[type.value] || [];
        category.innerHTML = '';
        category.appendChild(new Option('Select category', ''));
        options.forEach(function (name) {
            category.appendChild(new Option(name, name));
        });
        if (current && options.includes(current)) {
            category.value = current;
        }
    }

    document.querySelectorAll('[data-ledger-form]').forEach(syncCategoryList);
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-ledger-type]')) {
            const form = event.target.closest('[data-ledger-form]') || document;
            const category = form.querySelector('[data-ledger-category]');
            if (category) category.dataset.currentCategory = '';
            syncCategoryList(form);
        }
    });
});
</script>
@endsection
