@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
@php
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
        width: min(560px, 100%);
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
        <div class="auth-mini-mark"><i class="fas fa-lock"></i></div>
        @if(! empty($invalidToken))
            <h1 class="h3 fw-bold mb-2">Reset link expired</h1>
            <p class="text-muted mb-4">This password reset link is invalid or has expired. Request a new reset link to continue.</p>
            <div class="alert alert-danger">This password reset token is invalid.</div>
            <div class="d-grid gap-2">
                <a href="{{ route('password.request') }}" class="btn btn-primary">Request New Reset Link</a>
                <a href="{{ route('login') }}" class="btn btn-outline-primary">Back to Login</a>
            </div>
        @else
            <h1 class="h3 fw-bold mb-2">Choose a new password</h1>
            <p class="text-muted mb-4">Use at least 8 characters for your new password.</p>

            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="tenant_id" value="{{ $tenantId ?? '' }}">
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Reset Password</button>
            </form>
        @endif
    </div>
</div>
@endsection
