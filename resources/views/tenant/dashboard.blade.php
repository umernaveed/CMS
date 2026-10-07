@extends('layouts.app')

@section('title', 'Courier Dashboard')

@section('content')
<h1>Courier Dashboard</h1>
<p>Welcome to the courier panel.</p>
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Packages</h5>
                <p class="card-text">Manage packages</p>
                <a href="{{ route('courier.packages.index') }}" class="btn btn-primary">Go</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Customers</h5>
                <p class="card-text">Manage customers</p>
                <a href="{{ route('courier.customers.index') }}" class="btn btn-primary">Go</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Invoices</h5>
                <p class="card-text">Manage invoices</p>
                <a href="{{ route('courier.invoices.index') }}" class="btn btn-primary">Go</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Reports</h5>
                <p class="card-text">View reports</p>
                <a href="{{ route('courier.finance.reports') }}" class="btn btn-primary">Go</a>
            </div>
        </div>
    </div>
</div>
@endsection
