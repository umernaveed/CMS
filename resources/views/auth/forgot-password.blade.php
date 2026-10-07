@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
@php
    $tenantContext = $currentTenant ?? null;
    $brandName = $tenantContext ? $tenantContext->company_name : ($platformName ?? config('app.name', 'Courier SaaS'));
    $settings = $platformSettings ?? collect();
    $loginBackground = $settings['login_background_url'] ?? 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1800&q=80';
@endphp

<style>
    .auth-mini-shell {
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 24px;
        background:
            linear-gradient(135deg, rgba(2, 6, 23, .82), rgba(7, 59, 112, .76)),
            url("{{ $loginBackground }}") center/cover;
    }
    .auth-mini-card {
        width: min(520px, 100%);
        background: #fff;
        border-radius: 22px;
        padding: 38px;
        box-shadow: 0 28px 70px rgba(2, 6, 23, .32);
    }
    .auth-mini-mark {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: var(--brand-strong);
        color: #fff;
        margin-bottom: 18px;
    }
    .auth-mini-card .form-control {
        min-height: 52px;
        border-radius: 12px;
        background: #f8fafc;
    }
    .auth-mini-card .btn {
        min-height: 52px;
        border-radius: 12px;
    }
</style>

<div class="auth-mini-shell">
    <div class="auth-mini-card">
        <div class="auth-mini-mark"><i class="fas fa-key"></i></div>
        <h1 class="h3 fw-bold mb-2">Reset your password</h1>
        <p class="text-muted mb-4">Enter your email and {{ $brandName }} will send you a reset link.</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">Back to Login</a>
            </div>
        @else
            @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>
                <button class="btn btn-primary w-100">Send Reset Link</button>
                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="fw-bold">Back to Login</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
