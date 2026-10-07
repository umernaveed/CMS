@extends('layouts.app')

@section('title', 'Finance Reports')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Finance Reports', 'subtitle' => 'Finance rollup across invoices, income, expenses, and open balances.', 'section' => 'reports'])
@include('courier.finance._summary-filter')
@include('courier.finance._summary-cards')
<div class="row g-3 mt-1">
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Open Invoice Balance</div><div class="h3 mb-0">JMD {{ number_format($openInvoices, 2) }}</div></div></div>
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Invoices</div><div class="h3 mb-0">{{ $invoiceCount }}</div></div></div>
    <div class="col-md-4"><div class="panel p-4"><div class="text-muted">Paid Invoices</div><div class="h3 mb-0">{{ $paidInvoiceCount }}</div></div></div>
</div>
<div class="panel p-4 mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h2 class="h5 mb-1">Official Balance Sheet</h2>
        <p class="text-muted mb-0">Generate an assets, liabilities, and equity snapshot for the selected period.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('courier.finance.balance-sheet', request()->query()) }}">Open Balance Sheet</a>
</div>
@endsection
