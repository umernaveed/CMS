@extends('layouts.app')

@section('title', 'Reports')
@section('page_title', 'Reports')
@section('breadcrumb', 'Reports')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Reports</h1>
        <p class="text-muted mb-0">Run reports across platform modules, including income, expenses, and profit / loss.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('superadmin.reports.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract</a>
        <a href="{{ route('superadmin.reports.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('superadmin.reports.download', request()->query()) }}" class="btn btn-primary"><i class="fas fa-download me-1"></i>Download</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6"><div class="panel p-3"><div class="text-muted">Couriers</div><div class="h3 mb-0">{{ number_format($stats['couriers']) }}</div><small>{{ number_format($stats['activeCouriers']) }} active</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="panel p-3"><div class="text-muted">Customers</div><div class="h3 mb-0">{{ number_format($stats['customers']) }}</div><small>{{ number_format($stats['packages']) }} packages</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="panel p-3"><div class="text-muted">Income</div><div class="h3 mb-0 text-success">JMD {{ number_format($stats['income'], 2) }}</div><small>Filtered finance period</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="panel p-3"><div class="text-muted">Profit / Loss</div><div class="h3 mb-0 {{ $stats['profitLoss'] >= 0 ? 'text-primary' : 'text-danger' }}">JMD {{ number_format($stats['profitLoss'], 2) }}</div><small>Expenses: JMD {{ number_format($stats['expenses'], 2) }}</small></div></div>
</div>

<form method="GET" action="{{ route('superadmin.reports.index') }}" class="panel p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Module</label>
            <select name="module" class="form-select">
                @foreach($modules as $key => $module)
                    <option value="{{ $key }}" @selected($moduleKey === $key)>{{ $module['title'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select">
                <option value="">All couriers</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Status / Action</label>
            <input name="status" class="form-control" value="{{ request('status') }}" placeholder="paid, active, opened">
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Finance Type</label>
            <select name="finance_type" class="form-select">
                <option value="">All</option>
                <option value="income" @selected(request('finance_type') === 'income')>Income</option>
                <option value="expense" @selected(request('finance_type') === 'expense')>Expense</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-xl-10 col-md-8">
            <label class="form-label">Search</label>
            <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Search the selected module">
        </div>
        <div class="col-xl-2 col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Run</button>
            <a href="{{ route('superadmin.reports.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel mb-4">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="h5 mb-1">{{ $report['title'] }}</h2>
            <p class="text-muted mb-0">{{ $report['description'] }}</p>
        </div>
        <span class="badge badge-soft">{{ $report['rows']->total() ?? $report['rows']->count() }} rows</span>
    </div>
    <div class="row g-0 border-bottom">
        @foreach($report['summary'] as $label => $value)
            <div class="col-xl-2 col-md-4 col-6 p-3 border-end">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fw-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
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
                    <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted py-4">No report rows found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($report['rows'], 'links'))
        <div class="p-3">{{ $report['rows']->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
