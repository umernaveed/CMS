@extends('layouts.app')

@section('title', 'Manage Weight')
@section('page_title', 'Manage Weight')
@section('breadcrumb', 'Manage Weight')

@section('content')
@php
    $shippingModes = $shippingModes ?? collect();
    $modeLabels = $shippingModes->pluck('name', 'code');
    $activeModes = $shippingModes->where('is_active', true)->count();
    $inactiveModes = $shippingModes->count() - $activeModes;
    $activeRates = $rates->getCollection()->where('is_active', true)->count();
@endphp
<style>
    .rates-workspace { display: grid; gap: 16px; }
    .rates-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .rates-title { margin: 0; font-size: 30px; line-height: 1.1; font-weight: 850; color: #111827; }
    .rates-subtitle { margin: 6px 0 0; color: #64748b; font-size: 15px; }
    .rates-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .rates-metric { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 14px 16px; min-height: 82px; }
    .rates-metric span { display: block; color: #64748b; font-size: 12px; font-weight: 750; text-transform: uppercase; letter-spacing: .03em; }
    .rates-metric strong { display: block; margin-top: 6px; color: #0f172a; font-size: 26px; line-height: 1; font-weight: 900; }
    .rates-tabs-shell { border: 1px solid #dbe3ef; border-radius: 8px; background: #fff; overflow: hidden; box-shadow: 0 14px 32px rgba(15, 23, 42, .06); }
    .rates-tabs-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #e5e7eb; background: #f8fafc; flex-wrap: wrap; }
    .rates-tabs { gap: 8px; }
    .rates-tabs .nav-link { border: 1px solid transparent; border-radius: 8px; color: #475569; font-weight: 800; padding: 9px 12px; }
    .rates-tabs .nav-link i { margin-right: 7px; }
    .rates-tabs .nav-link.active { background: var(--brand); border-color: var(--brand); color: var(--sidebar-active-text, #fff); }
    .rates-tab-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .rates-tab-body { padding: 14px; }
    .rates-compact-form { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; margin-bottom: 14px; }
    .rates-table-wrap { max-height: min(62vh, 680px); overflow: auto; border-radius: 8px; border: 1px solid #e5e7eb; }
    .rates-table-wrap table { margin-bottom: 0; }
    .rates-table-wrap thead th { position: sticky; top: 0; z-index: 1; background: #f8fafc; color: #334155; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .rates-table-wrap tbody td { vertical-align: middle; }
    .rates-code { color: #db2777; font-weight: 800; background: #fdf2f8; border-radius: 6px; padding: 3px 7px; }
    .rates-empty { padding: 52px 20px; text-align: center; color: #64748b; }
    .rates-pagination { border-top: 1px solid #e5e7eb; background: #fff; }
    @media (max-width: 900px) {
        .rates-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rates-title { font-size: 24px; }
    }
    @media (max-width: 560px) {
        .rates-metrics { grid-template-columns: 1fr; }
        .rates-tab-actions, .rates-tab-actions .btn { width: 100%; }
    }
</style>

<div class="rates-workspace">
    <div class="rates-hero">
        <div>
            <h1 class="rates-title">Rates Management</h1>
            <p class="rates-subtitle">Shipping modes and rate ranges for weight, volume, branch, and tier pricing.</p>
        </div>
        <a href="{{ route('courier.weights.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Rate</a>
    </div>

    <div class="rates-metrics">
        <div class="rates-metric"><span>Shipping Modes</span><strong>{{ number_format($shippingModes->count()) }}</strong></div>
        <div class="rates-metric"><span>Active Modes</span><strong>{{ number_format($activeModes) }}</strong></div>
        <div class="rates-metric"><span>Rate Ranges</span><strong>{{ number_format($rates->total()) }}</strong></div>
        <div class="rates-metric"><span>Active On Page</span><strong>{{ number_format($activeRates) }}</strong></div>
    </div>

    <div class="rates-tabs-shell">
        <div class="rates-tabs-head">
            <ul class="nav nav-pills rates-tabs" id="ratesTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="rates-tab" data-bs-toggle="tab" data-bs-target="#rates-panel" type="button" role="tab" aria-controls="rates-panel" aria-selected="true"><i class="fas fa-scale-balanced"></i>Rate Ranges</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="modes-tab" data-bs-toggle="tab" data-bs-target="#modes-panel" type="button" role="tab" aria-controls="modes-panel" aria-selected="false"><i class="fas fa-route"></i>Shipping Modes</button>
                </li>
            </ul>
            <div class="rates-tab-actions">
                <a class="btn btn-outline-primary" href="{{ route('courier.weights.shipping-modes.create') }}"><i class="fas fa-route me-1"></i>New Mode</a>
                <a href="{{ route('courier.weights.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Rate</a>
            </div>
        </div>

        <div class="tab-content rates-tab-body" id="ratesTabsContent">
            <div class="tab-pane fade show active" id="rates-panel" role="tabpanel" aria-labelledby="rates-tab" tabindex="0">
                <div class="rates-table-wrap" data-no-datatable>
                    <table class="table table-hover align-middle no-datatable" data-no-datatable>
                        <thead><tr><th>Freight</th><th>Region</th><th>Branch Group</th><th>Range</th><th>Minimum Billable</th><th>Processing Fee</th><th>Regular Price</th><th>Express Price</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse($rates as $rate)
                            <tr>
                                <td><strong>{{ $modeLabels[$rate->freight_type] ?? ucfirst(str_replace('-', ' ', $rate->freight_type)) }}</strong><br><small class="text-muted">{{ optional($rate->pricingTier)->name ?: 'All tiers' }}</small></td>
                                <td>{{ $rate->region ?: 'All regions' }}</td>
                                <td>{{ optional($rate->outlet)->name ?: 'Main / shared' }}</td>
                                <td>{{ number_format($rate->min_weight, 2) }} - {{ $rate->max_weight !== null ? number_format($rate->max_weight, 2) : 'No max' }} {{ $rate->unitLabel() }}</td>
                                <td>{{ number_format($rate->minimum_billable_measure ?? 0, 2) }} {{ $rate->unitLabel() }}</td>
                                <td>JMD {{ number_format($rate->processing_fee_jmd ?? 0, 2) }}</td>
                                <td>JMD {{ number_format($rate->price_jmd, 2) }}</td>
                                <td>JMD {{ number_format($rate->express_price_jmd, 2) }}</td>
                                <td><span class="badge {{ $rate->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a href="{{ route('courier.weights.show', $rate) }}" class="dropdown-item"><i class="fas fa-eye me-2"></i>View</a></li>
                                            <li><a href="{{ route('courier.weights.edit', $rate) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><form method="POST" action="{{ route('courier.weights.destroy', $rate) }}" data-confirm="Delete this weight rate?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><div class="rates-empty">No weight rates yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 rates-pagination">{{ $rates->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            </div>

            <div class="tab-pane fade" id="modes-panel" role="tabpanel" aria-labelledby="modes-tab" tabindex="0">
                <div class="collapse" id="newShippingModeForm">
                    <form method="POST" action="{{ route('courier.weights.shipping-modes.store') }}" class="rates-compact-form row g-3 align-items-end">
                        @csrf
                        <div class="col-xl-2 col-md-6"><label class="form-label">Mode Name</label><input name="name" class="form-control" placeholder="US to JA" required></div>
                        <div class="col-xl-2 col-md-6"><label class="form-label">Code</label><input name="code" class="form-control" placeholder="us-ja"></div>
                        <div class="col-xl-2 col-md-6"><label class="form-label">Category</label><select name="category" class="form-select"><option value="air">Air / weight</option><option value="sea">Sea / volume</option><option value="local">Local / delivery</option></select></div>
                        <div class="col-xl-2 col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" placeholder="Standard route"></div>
                        <div class="col-xl-1 col-md-4"><label class="form-label">Order</label><input type="number" name="sort_order" class="form-control" value="100" min="0"></div>
                        <div class="col-xl-1 col-md-4"><input type="hidden" name="is_active" value="0"><div class="form-check pb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="newModeActive" checked><label class="form-check-label" for="newModeActive">Active</label></div></div>
                        <div class="col-xl-1 col-md-4"><input type="hidden" name="is_default" value="0"><div class="form-check pb-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="newModeDefault"><label class="form-check-label" for="newModeDefault">Default</label></div></div>
                        <div class="col-xl-1 col-md-4"><button class="btn btn-primary w-100">Add</button></div>
                    </form>
                </div>

                <div class="rates-table-wrap" data-no-datatable>
                    <table class="table table-hover align-middle no-datatable" data-no-datatable>
                        <thead><tr><th>Name</th><th>Code</th><th>Category</th><th>Description</th><th>Order</th><th>Status</th><th>Default</th><th></th></tr></thead>
                        <tbody>
                        @forelse($shippingModes as $mode)
                            @php($formId = 'modeUpdate'.$mode->id)
                            <tr>
                                <td class="fw-semibold">
                                    <form id="{{ $formId }}" method="POST" action="{{ route('courier.weights.shipping-modes.update', $mode) }}">@csrf @method('PUT')</form>
                                    <input form="{{ $formId }}" name="name" class="form-control form-control-sm" value="{{ $mode->name }}" required>
                                </td>
                                <td><input form="{{ $formId }}" name="code" class="form-control form-control-sm" value="{{ $mode->code }}" required></td>
                                <td><select form="{{ $formId }}" name="category" class="form-select form-select-sm"><option value="air" @selected($mode->category === 'air')>Air</option><option value="sea" @selected($mode->category === 'sea')>Sea</option><option value="local" @selected($mode->category === 'local')>Local</option></select></td>
                                <td><input form="{{ $formId }}" name="description" class="form-control form-control-sm" value="{{ $mode->description }}" maxlength="160"></td>
                                <td style="width:92px"><input form="{{ $formId }}" type="number" name="sort_order" class="form-control form-control-sm" value="{{ $mode->sort_order }}" min="0" max="9999"></td>
                                <td style="width:110px"><input form="{{ $formId }}" type="hidden" name="is_active" value="0"><div class="form-check mb-0"><input form="{{ $formId }}" class="form-check-input" type="checkbox" name="is_active" value="1" id="modeActive{{ $mode->id }}" @checked($mode->is_active)><label class="form-check-label" for="modeActive{{ $mode->id }}">Active</label></div></td>
                                <td style="width:120px"><input form="{{ $formId }}" type="hidden" name="is_default" value="0"><div class="form-check mb-0"><input form="{{ $formId }}" class="form-check-input" type="checkbox" name="is_default" value="1" id="modeDefault{{ $mode->id }}" @checked($mode->is_default)><label class="form-check-label" for="modeDefault{{ $mode->id }}">Default</label></div></td>
                                <td class="text-end" style="min-width:150px">
                                    <button form="{{ $formId }}" class="btn btn-sm btn-primary">Save</button>
                                    <form method="POST" action="{{ route('courier.weights.shipping-modes.destroy', $mode) }}" class="d-inline" data-confirm="Delete this shipping mode? Existing records will move to another mode in the same category when possible.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="rates-empty">No shipping modes yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('#ratesTabs [data-bs-toggle="tab"]');
    const hash = window.location.hash;
    if (hash) {
        const target = document.querySelector('[data-bs-target="' + hash + '"]');
        if (target && window.bootstrap) {
            window.bootstrap.Tab.getOrCreateInstance(target).show();
        }
    }
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', event => {
            const target = event.target.getAttribute('data-bs-target');
            if (target) history.replaceState(null, '', target);
        });
    });
});
</script>
@endsection




