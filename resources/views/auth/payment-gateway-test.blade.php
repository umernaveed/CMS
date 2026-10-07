@extends('layouts.app')

@section('title', 'Test Gateway')

@section('content')
<div class="login-shell register-shell py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="panel p-4 bg-white">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="brand-mark"><i class="fas fa-credit-card"></i></div>
                        <div>
                            <div class="text-uppercase text-primary fw-bold small">Sandbox checkout</div>
                            <h1 class="h3 fw-bold mb-0">Test Gateway</h1>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table">
                            <tr><th>Company</th><td>{{ $payment->tenant->company_name }}</td></tr>
                            <tr><th>Plan</th><td>{{ $payment->subscription->plan->name }}</td></tr>
                            <tr><th>Reference</th><td>{{ $payment->transaction_id }}</td></tr>
                            <tr><th>Amount</th><td>{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td></tr>
                        </table>
                    </div>

                    <div class="d-flex gap-2">
                        <a class="btn btn-primary flex-fill" href="{{ URL::temporarySignedRoute('tenant.register.payment.success', now()->addMinutes(30), $payment) }}">Approve Payment</a>
                        <a class="btn btn-outline-secondary" href="{{ URL::temporarySignedRoute('tenant.register.payment.cancel', now()->addMinutes(30), $payment) }}">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
