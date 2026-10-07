@extends('layouts.app')

@section('title', 'Delete Account')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-3 bg-danger bg-opacity-10 text-danger d-grid" style="width: 48px; height: 48px; place-items: center;">
                <i class="fas fa-user-xmark fa-lg"></i>
            </div>
            <div>
                <h1 class="h3 mb-1">Delete Account</h1>
                <p class="text-muted mb-0">Permanently remove your customer account and portal access.</p>
            </div>
        </div>

        <div class="alert alert-warning">
            Deleting your account cannot be undone. Your login, profile, authorized users, pre-alerts, purchase requests, referral history, and wallet activity will be removed. Courier operational records such as previous packages, invoices, payments, and delivery records may be retained without your active account where required for business, tax, or legal purposes.
        </div>

        <div class="panel p-4">
            <form method="POST" action="{{ route('customer.account.destroy') }}" onsubmit="return confirm('Delete your account permanently? This cannot be undone.');">
                @csrf
                @method('DELETE')

                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input @error('confirm_deletion') is-invalid @enderror" type="checkbox" value="1" id="confirm_deletion" name="confirm_deletion" required>
                    <label class="form-check-label" for="confirm_deletion">
                        I understand this action is permanent and I want to delete my account.
                    </label>
                    @error('confirm_deletion')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete My Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
