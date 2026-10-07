@extends('layouts.app')

@section('title', 'Subscription Plans')
@section('page_title', 'Subscription Plans')
@section('breadcrumb', 'Finances / Subscription Plans')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Subscription Plan Configuration</h1>
        <p class="text-muted mb-0">Manage courier billing plans, pricing, usage limits, and included platform features.</p>
    </div>
    <a href="{{ route('superadmin.plans.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Plan</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Plans</div><div class="h3 mb-0">{{ number_format($plans->total()) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Active</div><div class="h3 mb-0">{{ number_format($plans->where('is_active', true)->count()) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Highest Monthly</div><div class="h3 mb-0">JMD {{ number_format($plans->max('monthly_price') ?? 0, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Highest Yearly</div><div class="h3 mb-0">JMD {{ number_format($plans->max('yearly_price') ?? 0, 2) }}</div></div></div>
</div>

<div class="row g-4">
@forelse($plans as $plan)
    @php
        $features = data_get($plan->features, 'features', []);
        $featureList = is_array($features) ? $features : [];
        $enabledModules = $plan->enabledModules();
    @endphp
    <div class="col-xl-4 col-lg-6">
        <div class="panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $plan->name }}</h2>
                    <div class="text-muted small">Sort order {{ $plan->sort_order ?? 0 }}</div>
                </div>
                <span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->is_active ? 'Active' : 'Hidden' }}</span>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Monthly</div>
                        <div class="h4 mb-0">JMD {{ number_format($plan->monthly_price, 2) }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Yearly</div>
                        <div class="h4 mb-0">JMD {{ number_format($plan->yearly_price ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="border rounded p-3 mb-3 small">
                <div class="fw-bold mb-2">{{ ucfirst(str_replace('_', ' + ', $plan->billing_model ?? 'subscription')) }}</div>
                <div><span class="text-muted">Logged package fee:</span> JMD {{ number_format((float) $plan->per_package_base_fee_jmd, 2) }}</div>
                <div><span class="text-muted">Shipped package service charge:</span> JMD {{ number_format((float) $plan->package_service_fee_jmd, 2) }}</div>
            </div>

            <p class="text-muted mb-3">{{ $plan->description ?: 'No plan description added.' }}</p>

            <div class="row g-2 small mb-3">
                <div class="col-6"><span class="text-muted">Users:</span> {{ $plan->max_users ?: 'Unlimited' }}</div>
                <div class="col-6"><span class="text-muted">Customers:</span> {{ $plan->max_customers ?: 'Unlimited' }}</div>
                <div class="col-6"><span class="text-muted">Outlets:</span> {{ $plan->max_outlets ?: 'Unlimited' }}</div>
                <div class="col-6"><span class="text-muted">Packages:</span> {{ $plan->max_packages_month ?: 'Unlimited' }}</div>
                <div class="col-12"><span class="text-muted">Storage Days:</span> {{ $plan->max_storage_days ?: 'Unlimited' }}</div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach(array_slice($enabledModules, 0, 4) as $module)
                    <span class="badge bg-primary">{{ \App\Models\SubscriptionPlan::MODULES[$module] ?? $module }}</span>
                @endforeach
                @if(count($enabledModules) > 4)
                    <span class="badge bg-primary">+{{ count($enabledModules) - 4 }} modules</span>
                @endif
                @forelse(array_slice($featureList, 0, 5) as $feature)
                    <span class="badge bg-light text-dark border">{{ $feature }}</span>
                @empty
                    <span class="text-muted small">No features listed.</span>
                @endforelse
                @if(count($featureList) > 5)
                    <span class="badge bg-light text-dark border">+{{ count($featureList) - 5 }} more</span>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 mt-auto">
                <a href="{{ route('superadmin.plans.show', $plan) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i>View</a>
                <a href="{{ route('superadmin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen me-1"></i>Edit</a>
                <form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this subscription plan?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="col-12">
        <div class="panel p-5 text-center text-muted">No subscription plans found.</div>
    </div>
@endforelse
</div>

<div class="mt-4">{{ $plans->links('pagination::bootstrap-5') }}</div>
@endsection
