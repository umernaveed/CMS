@extends('layouts.app')

@section('title', 'Courier Wallet')
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div><h1 class="h3 mb-1">Courier Wallet</h1><p class="text-muted mb-0">Credits added by the System Admin and amounts applied to courier bills.</p></div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" target="_blank" href="{{ route('courier.finance.wallet.print', request()->query()) }}">Print</a>
        <a class="btn btn-primary" href="{{ route('courier.finance.wallet.download', request()->query()) }}">Download CSV</a>
    </div>
</div>
<div class="panel p-4 mb-3"><div class="text-muted">Available Wallet Balance</div><div class="display-6 fw-bold text-primary">JMD {{ number_format((float) $tenant->credit_balance_jmd, 2) }}</div></div>
@include('courier.finance._wallet-table', ['transactions' => $transactions])
@endsection
