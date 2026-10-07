@extends('layouts.app')

@section('title', 'Weight Rate Details')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ ucfirst($rate->freight_type) }} Rate</h1>
        <p class="text-muted mb-0">{{ number_format($rate->min_weight, 2) }} - {{ $rate->max_weight !== null ? number_format($rate->max_weight, 2) : 'No maximum' }} {{ $rate->unitLabel() }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.weights.edit', $rate) }}" class="btn btn-primary">Edit</a>
        <form method="POST" action="{{ route('courier.weights.destroy', $rate) }}" onsubmit="return confirm('Delete this weight rate?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    </div>
</div>

<div class="panel p-4">
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted">Freight</div>{{ ucfirst($rate->freight_type) }}</div>
        <div class="col-md-3"><div class="text-muted">Region</div>{{ $rate->region ?: 'All regions' }}</div>
        <div class="col-md-3"><div class="text-muted">Branch Group</div>{{ optional($rate->outlet)->name ?: 'Main / shared' }}</div>
        <div class="col-md-3"><div class="text-muted">Tier</div>{{ optional($rate->pricingTier)->name ?: 'All tiers' }}</div>
        <div class="col-md-3"><div class="text-muted">Min Weight</div>{{ number_format($rate->min_weight, 2) }} {{ $rate->unitLabel() }}</div>
        <div class="col-md-3"><div class="text-muted">Max Weight</div>{{ $rate->max_weight !== null ? number_format($rate->max_weight, 2) : 'No maximum' }} {{ $rate->unitLabel() }}</div>
        <div class="col-md-3"><div class="text-muted">Minimum Billable</div>{{ number_format($rate->minimum_billable_measure ?? 0, 2) }} {{ $rate->unitLabel() }}</div>
        <div class="col-md-3"><div class="text-muted">Processing Fee</div>JMD {{ number_format($rate->processing_fee_jmd ?? 0, 2) }}</div>
        <div class="col-md-3"><div class="text-muted">Regular Price</div>JMD {{ number_format($rate->price_jmd, 2) }} / {{ $rate->unitLabel() }}</div>
        <div class="col-md-3"><div class="text-muted">Express Price</div>JMD {{ number_format($rate->express_price_jmd, 2) }} / {{ $rate->unitLabel() }}</div>
        <div class="col-md-3"><div class="text-muted">Status</div><span class="badge {{ $rate->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span></div>
    </div>
</div>
@endsection
