@extends('layouts.app')
@section('title', 'Expense & Income')
@section('page_title', 'Expense & Income')
@section('breadcrumb', 'Finances / Expense & Income')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Expense & Income</h1>
        <p class="text-muted mb-0">Record platform income, expenses, reports, charts, and bulk ledger uploads.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('superadmin.finance.categories') }}" class="btn btn-outline-secondary"><i class="fas fa-tags me-1"></i>Categories</a>
        <a href="{{ route('superadmin.finance.entries.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Export</a>
        <a href="{{ route('superadmin.finance.entries.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-muted">Total System Revenue</div><div class="h3 text-success">JMD {{ number_format($summary['income'], 2) }}</div><small>Includes subscriptions and paid courier service charges</small></div></div>
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-muted">Total Expenses</div><div class="h3 text-danger">JMD {{ number_format($summary['expenses'], 2) }}</div></div></div>
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-muted">Profit / Loss</div><div class="h3 {{ $summary['profitLoss'] >= 0 ? 'text-primary' : 'text-danger' }}">JMD {{ number_format($summary['profitLoss'], 2) }}</div></div></div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Income vs Expense Chart</h2>
    <div style="height:280px"><canvas id="financeChart"></canvas></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <form method="POST" action="{{ route('superadmin.finance.entries.store') }}" class="panel p-4" data-ledger-form>
            @csrf
            <h2 class="h5 mb-3">Add Ledger Entry</h2>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select" data-ledger-type><option value="income">Income</option><option value="expense">Expense</option></select></div>
                <div class="col-md-3"><label class="form-label">Category</label><select name="category" class="form-select" data-ledger-category></select><div class="form-text"><a href="{{ route('superadmin.finance.categories') }}">Manage categories</a></div></div>
                <div class="col-md-3"><label class="form-label">New Category</label><input name="new_category" class="form-control" placeholder="Save on the fly"></div>
                <div class="col-md-3"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Date</label><input name="entry_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Reference</label><input name="reference_number" class="form-control"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
                <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Entry</button></div>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <form method="POST" action="{{ route('superadmin.finance.entries.import') }}" enctype="multipart/form-data" class="panel p-4 h-100">
            @csrf
            <h2 class="h5 mb-2">Bulk Upload</h2>
            <p class="text-muted small">Use the template to upload income and expense rows with the correct columns.</p>
            <input type="file" name="file" class="form-control mb-3" accept=".csv,.txt" required>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('superadmin.finance.entries.template') }}" class="btn btn-outline-secondary"><i class="fas fa-table me-1"></i>Template</a>
                <button class="btn btn-outline-primary"><i class="fas fa-file-arrow-up me-1"></i>Upload CSV</button>
            </div>
        </form>
    </div>
</div>

<form method="GET" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="">All</option><option value="income" @selected(request('type')==='income')>Income</option><option value="expense" @selected(request('type')==='expense')>Expense</option></select></div>
        <div class="col-md-3"><label class="form-label">Category</label><input name="category" value="{{ request('category') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-md-3 d-flex gap-2 flex-wrap"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button><a href="{{ route('superadmin.finance.entries') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Reference</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ optional($entry->entry_date)->format('M d, Y') }}</td>
                    <td><span class="badge {{ $entry->type === 'income' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($entry->type) }}</span></td>
                    <td>{{ $entry->category }}</td>
                    <td>{{ $entry->description ?: '-' }}</td>
                    <td>{{ $entry->reference_number ?: '-' }}</td>
                    <td class="text-end">JMD {{ number_format($entry->amount, 2) }}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#entryEdit{{ $entry->id }}"><i class="fas fa-pen me-1"></i>Edit</button>
                            <form method="POST" action="{{ route('superadmin.finance.entries.destroy', $entry) }}" data-confirm="Delete this finance entry?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button></form>
                        </div>
                    </td>
                </tr>
                <tr class="collapse" id="entryEdit{{ $entry->id }}">
                    <td colspan="7" class="bg-light">
                        <form method="POST" action="{{ route('superadmin.finance.entries.update', $entry) }}" class="row g-2 align-items-end p-2" data-ledger-form>
                            @csrf
                            @method('PUT')
                            <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select" data-ledger-type><option value="income" @selected($entry->type === 'income')>Income</option><option value="expense" @selected($entry->type === 'expense')>Expense</option></select></div>
                            <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select" data-ledger-category data-current-category="{{ $entry->category }}"></select></div>
                            <div class="col-md-2"><label class="form-label">New Category</label><input name="new_category" class="form-control" placeholder="Optional"></div>
                            <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0" class="form-control" value="{{ $entry->amount }}" required></div>
                            <div class="col-md-2"><label class="form-label">Date</label><input name="entry_date" type="date" class="form-control" value="{{ optional($entry->entry_date)->format('Y-m-d') }}" required></div>
                            <div class="col-md-2"><label class="form-label">Reference</label><input name="reference_number" class="form-control" value="{{ $entry->reference_number }}"></div>
                            <div class="col-md-2"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $entry->description }}"></div>
                            <div class="col-md-10"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ $entry->notes }}"></div>
                            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save</button></div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No entries found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $entries->links('pagination::bootstrap-5') }}</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ledgerCategories = @json($categories);

    function syncCategoryList(scope) {
        const type = scope.querySelector('[data-ledger-type]');
        const category = scope.querySelector('[data-ledger-category]');
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

    if (typeof Chart === 'undefined') return;
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: [
                {label: 'Income', data: @json($chart['income']), backgroundColor: '#10b981'},
                {label: 'Expenses', data: @json($chart['expenses']), backgroundColor: '#ef4444'}
            ]
        },
        options: {responsive: true, maintainAspectRatio: false}
    });
});
</script>
@endsection
