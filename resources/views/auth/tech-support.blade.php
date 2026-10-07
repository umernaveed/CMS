@extends('layouts.app')

@section('title', 'Tech Support')

@section('content')
<style>
    .tech-support-shell {
        min-height: calc(100vh - 1px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background:
            linear-gradient(135deg, rgba(0, 46, 93, .94), rgba(11, 30, 54, .88)),
            url("https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1800&q=80") center/cover;
    }

    .tech-support-card {
        width: min(100%, 460px);
        padding: 34px;
        border-radius: 16px;
        background: #ff6b00;
        box-shadow: 0 22px 55px rgba(0, 0, 0, .28);
        color: #fff;
    }

    .tech-support-icon {
        width: 54px;
        height: 54px;
        display: grid;
        place-items: center;
        margin-bottom: 20px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .15);
        font-size: 22px;
    }

    .tech-support-card h1 {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 900;
    }

    .tech-support-card p {
        margin-bottom: 24px;
        color: rgba(255, 255, 255, .9);
    }

    .tech-support-card .form-label {
        font-weight: 800;
    }

    .tech-support-card .form-control {
        min-height: 48px;
        border: 2px solid rgba(255, 255, 255, .65);
        border-radius: 7px;
    }

    .tech-support-submit {
        width: 100%;
        min-height: 48px;
        margin-top: 16px;
        border: 0;
        border-radius: 7px;
        background: #000;
        color: #fff;
        font-weight: 850;
    }

    .tech-support-submit:hover,
    .tech-support-submit:focus {
        background: #111827;
        color: #fff;
    }

    .tech-support-back {
        display: block;
        margin-top: 20px;
        color: #fff;
        font-weight: 750;
        text-align: center;
        text-decoration: none;
    }

    .tech-support-back:hover {
        color: #fff;
        text-decoration: underline;
    }
</style>

<div class="tech-support-shell">
    <main class="tech-support-card">
        <div class="tech-support-icon"><i class="fas fa-headset"></i></div>
        <h1>Tech Support</h1>
        <p>Enter the access code provided for {{ $tenant->company_name }}.</p>

        <form method="POST" action="{{ route('tech-support.login.store') }}">
            @csrf
            <label for="access_code" class="form-label">Tech Support Code</label>
            <input
                id="access_code"
                name="access_code"
                class="form-control @error('access_code') is-invalid @enderror"
                value="{{ old('access_code') }}"
                placeholder="Scan barcode or type code"
                autocomplete="one-time-code"
                autofocus
                required
            >
            @error('access_code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <button class="btn tech-support-submit" type="submit">Enter Tech Support</button>
        </form>

        <a href="{{ route('login') }}" class="tech-support-back">
            <i class="fas fa-arrow-left me-1"></i> Back to sign in
        </a>
    </main>
</div>
@endsection
