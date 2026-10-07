@extends('layouts.app')

@section('title', 'Balance Sheet')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Balance Sheet', 'subtitle' => 'Assets, liabilities, and equity for the selected period.', 'section' => 'balance-sheet'])
@include('courier.finance._summary-filter')
<div class="row g-3">
    <div class="col-md-6 col-xl-3"><div class="panel p-4"><div class="text-muted">Cash and Cash Equivalents</div><div class="h3 mb-0">JMD {{ number_format($cash, 2) }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="panel p-4"><div class="text-muted">Accounts Receivable</div><div class="h3 mb-0">JMD {{ number_format($receivables, 2) }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="panel p-4"><div class="text-muted">Total Assets</div><div class="h3 mb-0 text-success">JMD {{ number_format($totalAssets, 2) }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="panel p-4"><div class="text-muted">Retained Earnings</div><div class="h3 mb-0">JMD {{ number_format($equity, 2) }}</div></div></div>
</div>
<div class="panel mt-3">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Section</th><th>Metric</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <tr><td>Assets</td><td>Cash and cash equivalents</td><td class="text-end">JMD {{ number_format($cash, 2) }}</td></tr>
                <tr><td>Assets</td><td>Accounts receivable</td><td class="text-end">JMD {{ number_format($receivables, 2) }}</td></tr>
                <tr class="table-light"><td><strong>Assets</strong></td><td><strong>Total assets</strong></td><td class="text-end"><strong>JMD {{ number_format($totalAssets, 2) }}</strong></td></tr>
                @forelse($expenseCategories as $category => $amount)
                    <tr><td>Liabilities</td><td>{{ $category }}</td><td class="text-end">JMD {{ number_format($amount, 2) }}</td></tr>
                @empty
                    <tr><td>Liabilities</td><td class="text-muted">No liabilities recorded</td><td class="text-end">JMD 0.00</td></tr>
                @endforelse
                <tr class="table-light"><td><strong>Liabilities</strong></td><td><strong>Total liabilities</strong></td><td class="text-end"><strong>JMD {{ number_format($expenses, 2) }}</strong></td></tr>
                <tr><td>Equity</td><td>Retained earnings</td><td class="text-end">JMD {{ number_format($equity, 2) }}</td></tr>
                <tr class="table-light"><td><strong>Liabilities + Equity</strong></td><td><strong>Total liabilities + equity</strong></td><td class="text-end"><strong>JMD {{ number_format($liabilitiesAndEquity, 2) }}</strong></td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
