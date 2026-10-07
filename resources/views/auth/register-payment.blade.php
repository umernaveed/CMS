@extends('layouts.app')

@section('title', 'Subscription Payment')

@section('content')
@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<div class="login-shell register-shell py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="login-card bg-white">
                    <div class="row g-0">
                        <div class="col-lg-5 login-aside p-5 text-white">
                            <div class="register-kicker mb-4"><i class="fas fa-credit-card"></i> Final step</div>
                            <div class="brand-mark mb-4"><i class="fas fa-credit-card"></i></div>
                            <h1 class="display-6 fw-bold mb-3">Complete payment</h1>
                            <p class="text-white-50 mb-4">Your workspace is activated only after the selected payment gateway confirms a successful payment.</p>
                            <div class="register-feature mb-3">
                                <div class="text-white-50 small">Selected package</div>
                                <div class="h4 mb-1">{{ $plan->name }}</div>
                                <div>{{ ucfirst($registration['billing_cycle']) }} billing</div>
                            </div>
                            <div class="register-feature">
                                <div class="text-white-50 small">Today</div>
                                <div class="h4 mb-1">JMD {{ number_format($todayAmount, 2) }}</div>
                                <div class="text-white-50 small">
                                    Charged now to activate this subscription.
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 p-5 register-form-pane">
                            @if(isset($errors) && $errors->any())
                                <div class="alert alert-danger">
                                    <div class="fw-bold mb-1">Payment could not be completed</div>
                                    {{ $errors->first() }}
                                </div>
                            @endif
                            @if(session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                                <div>
                                    <div class="text-uppercase text-primary fw-bold small mb-2">Final step</div>
                                    <h2 class="h1 fw-bold mb-2">Choose gateway</h2>
                                    <p class="text-muted mb-0">The courier subscription is activated only after a real checkout payment is confirmed.</p>
                                </div>
                                <a href="{{ route('tenant.register') }}" class="btn btn-outline-secondary">Back</a>
                            </div>

                            <div class="panel p-3 mb-4">
                                <div class="row g-2">
                                    <div class="col-md-6"><span class="text-muted">Company</span><br><strong>{{ $registration['company_name'] }}</strong></div>
                                    <div class="col-md-6"><span class="text-muted">Admin Email</span><br><strong>{{ $registration['email'] }}</strong></div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('tenant.register.payment.complete') }}">
                                @csrf
                                <h3 class="h5 mb-3 register-section-title"><i class="fas fa-lock"></i>Secure Subscription Payment</h3>
                                @if(empty($availableGateways))
                                    <div class="alert alert-warning">
                                        No live checkout gateway is configured. Please contact support or ask the platform administrator to configure PayPal, Dime, or Amber Pay checkout first.
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Payment Gateway</label>
                                        <div class="row g-3">
                                            @foreach($availableGateways as $gateway)
                                                <div class="col-md-6">
                                                    <label class="panel plan-option p-3 h-100 d-flex align-items-center gap-3">
                                                        <input type="radio" name="gateway" value="{{ $gateway }}" @checked(old('gateway', $activeGateway) === $gateway) required>
                                                        <span>
                                                            <span class="fw-bold d-block">{{ $gatewayLabels[$gateway] ?? ucfirst(str_replace('_', ' ', $gateway)) }}</span>
                                                            <span class="text-muted small">Redirects to configured hosted checkout.</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        @error('gateway')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="authorize_subscription" value="1" id="authorize_subscription" required>
                                            <label class="form-check-label" for="authorize_subscription">
                                                I authorize this payment to activate the selected subscription.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 pt-2">
                                        <button class="btn btn-primary btn-lg w-100" @disabled(empty($availableGateways))><i class="fas fa-check me-2"></i>Continue to Gateway</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
