@extends('layouts.app')

@section('title', 'Affiliate & Loyalty')

@section('content')
@php
    $paymentMethods = ['online' => 'Online', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'debit_card' => 'Debit Card', 'other' => 'Other'];
    $availablePoints = (float) ($rewardPoints ?? 0);
    $estimatedValue = $availablePoints * (float) ($pointValueJmd ?? 1);
@endphp

<style>
    .loyalty-stat .stat-label { color:var(--muted, #64748b); font-size:.875rem; }
    .loyalty-stat .stat-value { font-size:1.35rem; font-weight:800; color:var(--brand-strong); }
    .loyalty-overview { overflow:hidden; }
    .loyalty-referral-icon { background:color-mix(in srgb, var(--brand) 12%, white); color:var(--brand); }
    .loyalty-referral-code { background:var(--brand-strong); color:var(--sidebar-text, #fff); border-radius:8px; padding:1rem; font-size:1.2rem; font-weight:800; text-align:center; letter-spacing:.04em; }
    .loyalty-copy-btn { background:linear-gradient(90deg,var(--brand),var(--brand-strong)); color:var(--sidebar-active-text, #fff); border:0; border-radius:7px; font-weight:800; }
    .loyalty-copy-url { background:color-mix(in srgb, var(--brand) 6%, white); border:1px solid color-mix(in srgb, var(--brand) 18%, #e5e7eb); border-radius:7px; padding:.7rem; font-size:.82rem; color:var(--brand-strong); word-break:break-all; }
    .loyalty-metric-row { display:flex; justify-content:space-between; gap:1rem; padding:.75rem 0; border-bottom:1px solid var(--line, #e5e7eb); }
    .loyalty-metric-row:last-child { border-bottom:0; }
    .loyalty-reward-item { border-bottom:1px solid var(--line, #e5e7eb); padding:.7rem 0; }
    .loyalty-reward-item:last-child { border-bottom:0; }
    .loyalty-alert { background:color-mix(in srgb, var(--brand) 14%, white); border:0; color:var(--brand-strong); }
    .loyalty-form-button { background:linear-gradient(90deg,var(--brand),var(--brand-strong)); border:0; color:var(--sidebar-active-text, #fff); font-weight:800; }
</style>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
    <div>
        <h1 class="h3 mb-1">Affiliate & Loyalty</h1>
        <p class="text-muted mb-0">Track your referral link, reward points, wallet balance, and recent loyalty activity.</p>
    </div>
    <a href="{{ route('customer.wallet.top-ups.history') }}" class="btn btn-outline-primary"><i class="fas fa-clock-rotate-left me-1"></i>Top-up History</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="panel p-3 loyalty-stat"><div class="stat-label">Wallet Balance</div><div class="stat-value">JMD {{ number_format($walletBalance ?? 0, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3 loyalty-stat"><div class="stat-label">Reward Points</div><div class="stat-value">{{ number_format($rewardPoints ?? 0, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3 loyalty-stat"><div class="stat-label">Referral Points</div><div class="stat-value">{{ number_format($referralPoints ?? 0, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3 loyalty-stat"><div class="stat-label">Referrals</div><div class="stat-value">{{ number_format($referralCount ?? 0) }}</div></div></div>
</div>

<div class="panel loyalty-overview mb-4">
    <div class="row g-0">
        <div class="col-lg-4 p-4 border-end">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="loyalty-referral-icon rounded p-3"><i class="fas fa-gift"></i></div>
                <div>
                    <h2 class="h5 mb-1">Referral Link</h2>
                    <div class="text-muted small">Share your link to earn rewards.</div>
                </div>
            </div>
            <div class="loyalty-referral-code mb-3">{{ $referralCode ?? optional(auth()->user())->referral_code }}</div>
            <button type="button" class="btn loyalty-copy-btn w-100 mb-3" data-copy-referral="{{ $referralUrl }}"><i class="fas fa-link me-1"></i>Copy Referral Link</button>
            <div class="loyalty-copy-url">{{ $referralUrl }}</div>
        </div>
        <div class="col-lg-4 p-4 border-end">
            <div class="text-muted small mb-1">Available Points</div>
            <div class="display-6 fw-bold mb-3">{{ number_format($availablePoints, 2) }}</div>
            <div class="loyalty-metric-row"><span class="text-muted">Estimated Value</span><strong>JMD {{ number_format($estimatedValue, 2) }}</strong></div>
            <div class="loyalty-metric-row"><span class="text-muted">Wallet Balance</span><strong>JMD {{ number_format($walletBalance ?? 0, 2) }}</strong></div>
            <div class="loyalty-metric-row"><span class="text-muted">Completed Referrals</span><strong>{{ number_format($referralCount ?? 0) }}</strong></div>
            <div class="loyalty-metric-row"><span class="text-muted">Referral Points</span><strong>{{ number_format($referralPoints ?? 0, 2) }}</strong></div>
        </div>
        <div class="col-lg-4 p-4">
            <h2 class="h5 mb-3">Recent Rewards</h2>
            @forelse(($rewardTransactions ?? collect()) as $transaction)
                <div class="loyalty-reward-item">
                    <div class="d-flex justify-content-between gap-2">
                        <strong>{{ number_format((float) $transaction->points, 2) }} pts</strong>
                        <small class="text-muted">{{ optional($transaction->created_at)->format('M d, Y') }}</small>
                    </div>
                    <div class="text-muted small">{{ $transaction->description ?: ucfirst(str_replace('_', ' ', $transaction->source_type ?? 'Reward')) }}</div>
                </div>
            @empty
                <div class="text-muted small">No points earned yet.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Add Funds</h2>
    <div class="alert loyalty-alert mb-4">Top-ups remain pending until courier approval. Once approved, the JMD amount is converted to reward points at JMD {{ number_format($pointValueJmd ?? 1, 4) }} per point.</div>
    <form method="POST" action="{{ route('customer.wallet.top-up') }}" enctype="multipart/form-data" class="row g-3">
        @csrf
        <div class="col-md-3"><label class="form-label">Amount JMD</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select" required>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Reference</label><input name="reference_number" class="form-control" placeholder="Transaction or receipt number"></div>
        <div class="col-md-3"><label class="form-label">Receipt</label><input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
        <div class="col-12"><label class="form-label">Note</label><textarea name="description" rows="2" class="form-control"></textarea></div>
        <div class="col-md-3"><button class="btn loyalty-form-button w-100"><i class="fas fa-wallet me-1"></i>Submit Top-up</button></div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-copy-referral]').forEach(function (button) {
        button.addEventListener('click', function () {
            const referralUrl = button.getAttribute('data-copy-referral');
            if (!referralUrl) return;
            navigator.clipboard.writeText(referralUrl).then(function () {
                const original = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i>Copied';
                setTimeout(function () { button.innerHTML = original; }, 1600);
            });
        });
    });
});
</script>
@endsection