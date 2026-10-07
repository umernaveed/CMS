@extends('layouts.app')

@section('title', 'Payment Pending')

@section('content')
<div class="login-shell register-shell py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="panel p-4 bg-white">
                    <div class="brand-mark mb-4"><i class="fas fa-clock"></i></div>
                    <h1 class="h3 fw-bold mb-2">Payment pending</h1>
                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
                    <p class="text-muted mb-4">Your courier account was created, but the subscription is waiting for payment confirmation.</p>
                    <div class="panel p-3 mb-4">
                        <div class="text-muted small">Reference</div>
                        <div class="fw-bold">{{ $payment->transaction_id }}</div>
                        <div class="text-muted small mt-2">Company</div>
                        <div class="fw-bold">{{ $payment->tenant->company_name }}</div>
                    </div>
                    <a href="{{ route('login') }}" class="btn btn-primary w-100">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
