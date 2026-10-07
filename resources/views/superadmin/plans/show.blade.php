@extends('layouts.app')

@section('title', 'Plan Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $plan->name }}</h1>
        <p class="text-muted mb-0">{{ $plan->description ?: 'Subscription plan details' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.plans.edit', $plan) }}" class="btn btn-primary">Edit</a>
        <form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this subscription plan?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Pricing & Limits</h2>
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted">Monthly</div><div class="h5">JMD {{ number_format($plan->monthly_price, 2) }}</div></div>
                <div class="col-md-3"><div class="text-muted">Yearly</div><div class="h5">JMD {{ number_format($plan->yearly_price, 2) }}</div></div>
                <div class="col-md-3"><div class="text-muted">Billing Model</div>{{ ucfirst(str_replace('_', ' + ', $plan->billing_model ?? 'subscription')) }}</div>
                <div class="col-md-3"><div class="text-muted">Logged Package Fee</div>JMD {{ number_format((float) $plan->per_package_base_fee_jmd, 2) }}</div>
                <div class="col-md-3"><div class="text-muted">Shipped Package Service Charge</div>JMD {{ number_format((float) $plan->package_service_fee_jmd, 2) }}</div>
                <div class="col-md-3"><div class="text-muted">Status</div><span class="badge badge-soft">{{ $plan->is_active ? 'Active' : 'Hidden' }}</span></div>
                <div class="col-md-3"><div class="text-muted">Sort</div>{{ $plan->sort_order }}</div>
                <div class="col-md-3"><div class="text-muted">Users</div>{{ $plan->max_users ?? 'Unlimited' }}</div>
                <div class="col-md-3"><div class="text-muted">Customers</div>{{ $plan->max_customers ?? 'Unlimited' }}</div>
                <div class="col-md-3"><div class="text-muted">Packages / Month</div>{{ $plan->max_packages_month ?? 'Unlimited' }}</div>
                <div class="col-md-3"><div class="text-muted">Outlets</div>{{ $plan->max_outlets ?? 'Unlimited' }}</div>
                <div class="col-md-3"><div class="text-muted">Storage Days</div>{{ $plan->max_storage_days ?? 'Unlimited' }}</div>
                <div class="col-md-3"><div class="text-muted">Subscriptions</div>{{ $plan->tenant_subscriptions_count }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Enabled Modules</h2>
            @foreach($plan->enabledModules() as $module)
                <div class="border-bottom py-2"><i class="fas fa-check text-success me-2"></i>{{ \App\Models\SubscriptionPlan::MODULES[$module] ?? $module }}</div>
            @endforeach
            @if(empty($plan->enabledModules()))
                <p class="text-muted mb-0">No modules enabled.</p>
            @endif
        </div>
        <div class="panel p-4 mt-4">
            <h2 class="h5 mb-3">Feature Notes</h2>
            @foreach(($plan->features['features'] ?? []) as $feature)
                <div class="border-bottom py-2">{{ $feature }}</div>
            @endforeach
            @if(empty($plan->features['features']))
                <p class="text-muted mb-0">No feature list entered.</p>
            @endif
        </div>
    </div>
</div>
@endsection
