@extends('layouts.app')

@section('title', 'Rate Calculator')

@section('content')
@php
    $freightCharge = $result['base'] ?? 0;
    $processingCharge = $result['processing_fee'] ?? 0;
    $handlingCharge = $result['handling_fee'] ?? 0;
    $ourTotal = $freightCharge + $processingCharge + $handlingCharge;
    $icd = $result['duty'] ?? 0;
    $scf = $result['scf'] ?? 0;
    $env = $result['envl'] ?? 0;
    $caf = $result['caf'] ?? 0;
    $gct = $result['gct'] ?? 0;
    $fee = ($result['insurance'] ?? 0) + ($result['stamp_duty'] ?? 0);
    $govtTotal = $result['customs'] ?? 0;
    $estimatedTotal = $result['total'] ?? 0;
@endphp

<style>
    .calc-shell {
        max-width: 1360px;
        margin: 0 auto;
    }
    .calc-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(380px, 1fr);
        gap: 24px;
        align-items: stretch;
    }
    .calc-input-card,
    .calc-result-card {
        border-radius: 14px;
        min-height: 500px;
    }
    .calc-input-card {
        background: #fff;
        border: 1px solid #dbe3ef;
        box-shadow: 0 16px 36px rgba(15, 23, 42, .08);
        padding: 28px;
    }
    .calc-result-card {
        background: #111827;
        color: #fff;
        box-shadow: 0 20px 46px rgba(15, 23, 42, .22);
        padding: 28px;
        display: flex;
        flex-direction: column;
    }
    .calc-title {
        font-size: 20px;
        line-height: 1.15;
        font-weight: 900;
        color: #07132c;
        margin: 0 0 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid #edf1f6;
    }
    .calc-label {
        color: #12244a;
        font-size: 14px;
        font-weight: 750;
        line-height: 1.2;
        margin-bottom: 8px;
    }
    .calc-control {
        min-height: 42px;
        border-radius: 10px;
        border: 1px solid #dce4ef;
        background-color: #f8fafc;
        color: #1f2a44;
        font-size: 14px;
        padding: 10px 14px;
        box-shadow: none;
    }
    .calc-control:focus {
        border-color: #93a4c4;
        background: #fff;
        box-shadow: 0 0 0 .25rem rgba(79, 70, 229, .12);
    }
    .calc-small-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .calc-option-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .calc-mode-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .calc-mode-card {
        min-height: 84px;
        border: 1px solid #dce4ef;
        border-radius: 10px;
        background: #f8fafc;
        color: #1f2a44;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        cursor: pointer;
    }
    .calc-mode-card i {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: #e0ecff;
        color: #135fd2;
        flex: 0 0 auto;
    }
    .calc-mode-card span {
        display: block;
        font-size: 13px;
        font-weight: 850;
        line-height: 1.2;
    }
    .calc-mode-card small {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 7px;
        line-height: 1.2;
    }
    .calc-mode-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .calc-mode-card:has(input:checked) {
        border-color: #135fd2;
        background: #eff6ff;
        box-shadow: 0 0 0 .16rem rgba(19, 95, 210, .12);
    }
    .calc-sea-panel,
    .calc-local-panel {
        display: none;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        padding: 14px;
    }
    .calc-sea-panel.is-visible,
    .calc-local-panel.is-visible {
        display: block;
    }
    .calc-submit {
        min-height: 42px;
        border-radius: 10px;
        margin-top: 20px;
    }
    .breakdown-title {
        font-size: 20px;
        font-weight: 900;
        margin: 2px 0 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(148, 163, 184, .35);
    }
    .breakdown-section {
        color: #b8c7ff;
        font-size: 12px;
        letter-spacing: .1em;
        text-transform: uppercase;
        margin: 16px 0 10px;
    }
    .charge-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .charge-box {
        background: #1f2937;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 62px;
    }
    .charge-label {
        color: #a8b3c7;
        font-size: 13px;
        margin-bottom: 4px;
    }
    .charge-value {
        color: #fff;
        font-size: 16px;
        font-weight: 800;
    }
    .total-strip {
        min-height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 12px;
        margin-top: 12px;
        background: #25235f;
        color: #fff;
        font-size: 14px;
        font-weight: 900;
    }
    .total-strip.govt { background: #1f2937; }
    .grand-total {
        margin-top: auto;
        padding-top: 26px;
        border-top: 1px solid rgba(148, 163, 184, .35);
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 18px;
    }
    .grand-total-label {
        color: #d5dbea;
        font-size: 15px;
    }
    .grand-total-value {
        color: #fff;
        font-size: 30px;
        line-height: 1;
        font-weight: 950;
    }
    .grand-total-value small {
        color: #a8b3c7;
        font-size: 14px;
        font-weight: 700;
        margin-left: 8px;
    }
    .calc-muted-note {
        color: #94a3b8;
        font-size: 13px;
        margin-top: 10px;
    }
    @media (max-width: 1100px) {
        .calc-grid { grid-template-columns: 1fr; gap: 20px; }
        .calc-input-card, .calc-result-card { min-height: auto; }
    }
    @media (max-width: 650px) {
        .calc-input-card, .calc-result-card { padding: 22px; border-radius: 16px; }
        .calc-small-grid, .calc-option-grid, .calc-mode-grid, .charge-grid { grid-template-columns: 1fr; }
        .calc-title, .breakdown-title { font-size: 19px; }
        .calc-label { font-size: 14px; }
        .calc-control { min-height: 42px; font-size: 14px; }
        .grand-total { align-items: flex-start; flex-direction: column; }
        .grand-total-value { font-size: 28px; }
    }
</style>

<div class="calc-shell">
    <div class="calc-grid">
        <form method="GET" action="{{ route('calculator') }}" class="calc-input-card">
            <h1 class="calc-title">Shipment Details</h1>

            <div class="mb-4">
                <label class="calc-label">Customs HS-Code / Category</label>
                <input type="search" class="form-control calc-control mb-2" data-hs-code-search placeholder="Search item, category, or HS code">
                <select name="hs_code_id" class="form-select calc-control" data-hs-code-select>
                    <option value="">Default Duty Rate ({{ number_format($customsConfig['duty_rate'] * 100, 2) }}%)</option>
                    @foreach($hsCodes as $hsCode)
                        <option value="{{ $hsCode->id }}" @selected($selectedHsCodeId == $hsCode->id)>{{ $hsCode->code }} - {{ \Illuminate\Support\Str::limit($hsCode->description, 72) }} ({{ number_format($hsCode->duty_rate, 2) }}%)</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="calc-label">Shipping Mode</label>
                <div class="calc-mode-grid" data-freight-modes>
                    @foreach($shippingModes as $mode)
                        <label class="calc-mode-card">
                            <input type="radio" name="freight_type" value="{{ $mode->code }}" data-category="{{ $mode->category }}" @checked($freightType === $mode->code)>
                            <i class="{{ $mode->icon ?: 'fas fa-box' }}"></i>
                            <span>{{ $mode->name }}<small>{{ $mode->description ?: ucfirst($mode->category) }}</small></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="calc-small-grid mb-4">
                <div>
                    <label class="calc-label">USD Value ($)</label>
                    <input type="number" step="0.01" min="0" name="declared_value" class="form-control calc-control" value="{{ $declaredValue ?: '' }}" placeholder="0.00">
                </div>
                <div>
                    <label class="calc-label">Weight (lbs)</label>
                    <input type="number" step="0.01" min="0" name="weight" class="form-control calc-control" value="{{ $weight ?: '' }}" placeholder="0.00" data-weight-field>
                </div>
            </div>

            <div class="calc-sea-panel mb-4" data-sea-panel>
                <label class="calc-label">Sea Freight Volume</label>
                <div class="calc-small-grid mb-3">
                    <div><input type="number" step="0.01" min="0" name="sea_volume_cuft" class="form-control calc-control" value="{{ $seaVolume ?: '' }}" placeholder="Cubic feet"></div>
                    <div class="d-flex align-items-center"><div class="text-muted small">Enter cubic feet directly, or use dimensions below. CBM conversion is shown in the estimate when the rate uses CBM.</div></div>
                </div>
                <div class="calc-option-grid">
                    <input type="number" step="0.01" min="0" name="length_in" class="form-control calc-control" value="{{ $length ?: '' }}" placeholder="Length (in)">
                    <input type="number" step="0.01" min="0" name="width_in" class="form-control calc-control" value="{{ $width ?: '' }}" placeholder="Width (in)">
                    <input type="number" step="0.01" min="0" name="height_in" class="form-control calc-control" value="{{ $height ?: '' }}" placeholder="Height (in)">
                    <div class="d-flex align-items-center text-muted small">Cubic feet = L x W x H / 1728.</div>
                </div>
            </div>

            <div class="calc-local-panel mb-4" data-local-panel>
                <label class="calc-label">Local Delivery</label>
                <div class="calc-small-grid mb-3">
                    <div>
                        <input type="text" name="pickup_location" class="form-control calc-control" value="{{ $pickupLocation }}" placeholder="Pickup location">
                    </div>
                    <div>
                        <input type="text" name="delivery_location" class="form-control calc-control" value="{{ $deliveryLocation }}" placeholder="Delivery location">
                    </div>
                </div>
                <div class="calc-small-grid">
                    <div>
                        <select name="delivery_area_id" class="form-select calc-control">
                            <option value="">Select fixed rate / zone</option>
                            @foreach($deliveryAreas as $area)
                                <option value="{{ $area->id }}" @selected($deliveryAreaId === $area->id)>
                                    {{ $area->name }} - {{ ($area->pricing_mode ?? 'fixed') === 'distance' ? 'Base + distance' : 'Fixed' }} JMD {{ number_format((float) $area->base_cost, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <input type="number" step="0.01" min="0" name="local_distance_km" class="form-control calc-control" value="{{ $localDistanceKm ?: '' }}" placeholder="Distance km, if distance based">
                    </div>
                </div>
            </div>

            <div class="calc-option-grid">
                <div>
                    <label class="form-label">Branch</label>
                    <select name="outlet_id" class="form-select">
                        <option value="">Main / shared rates</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected($outletId == $outlet->id)>{{ $outlet->name }}{{ ($outlet->rate_group_mode ?? 'main') === 'custom' ? ' - own rates' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Service</label>
                    <select name="delivery_type" class="form-select">
                        <option value="pickup" @selected($deliveryType === 'pickup')>Pickup</option>
                        <option value="delivery" @selected($deliveryType === 'delivery')>Delivery / Express</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Account Type</label>
                    <select name="account_type" class="form-select">
                        <option value="personal" @selected($accountType === 'personal')>Personal GCT 15%</option>
                        <option value="company" @selected($accountType === 'company')>Company GCT 25%</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Customs CAF</label>
                    <select name="caf_type" class="form-select">
                        <option value="residential" @selected($cafType === 'residential')>Residential JMD 2,500</option>
                        <option value="commercial" @selected($cafType === 'commercial')>Commercial JMD 5,000</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary w-100 calc-submit">Calculate Estimate</button>
            <div class="calc-muted-note">Customs applies when USD value is above {{ number_format($customsConfig['de_minimis_usd'], 2) }}. Exchange rate: {{ number_format($customsConfig['exchange_rate'], 2) }} JMD.</div>
        </form>

        <section class="calc-result-card">
            <h2 class="breakdown-title">Estimated Breakdown</h2>

            @if($calculationError)
                <div class="alert alert-warning mb-4">
                    <strong>Rate unavailable.</strong><br>{{ $calculationError }}
                </div>
            @endif

            <div class="breakdown-section">Our Charges</div>
            <div class="charge-grid">
                <div class="charge-box"><div class="charge-label">Freight</div><div class="charge-value">JMD {{ number_format($freightCharge, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">Processing</div><div class="charge-value">JMD {{ number_format($processingCharge, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">Handling</div><div class="charge-value">JMD {{ number_format($handlingCharge, 2) }}</div></div>
            </div>
            <div class="total-strip"><span>Our Total</span><span>JMD {{ number_format($ourTotal, 2) }}</span></div>

            <div class="breakdown-section">Govt. Charges (Customs)</div>
            <div class="charge-grid">
                <div class="charge-box"><div class="charge-label">ICD</div><div class="charge-value">JMD {{ number_format($icd, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">SCF</div><div class="charge-value">JMD {{ number_format($scf, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">ENV</div><div class="charge-value">JMD {{ number_format($env, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">CAF</div><div class="charge-value">JMD {{ number_format($caf, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">GCT</div><div class="charge-value">JMD {{ number_format($gct, 2) }}</div></div>
                <div class="charge-box"><div class="charge-label">Fee</div><div class="charge-value">JMD {{ number_format($fee, 2) }}</div></div>
            </div>
            <div class="total-strip govt"><span>Govt. Total</span><span>JMD {{ number_format($govtTotal, 2) }}</span></div>
            
            @if($result)
                <div class="calc-muted-note">{{ $freightCategory === 'sea' ? 'Unit rate' : 'Range price' }}: JMD {{ number_format($result['range_price'] ?? 0, 2) }}. Billable: {{ number_format($result['billable_measure'] ?? 0, 2) }} {{ $result['rate_unit'] ?? 'lb' }}.</div>
                @if($freightCategory === 'sea')
                    <div class="calc-muted-note">Sea volume: {{ number_format($result['entered_cuft'] ?? 0, 2) }} cu.ft / {{ number_format($result['entered_cbm'] ?? 0, 3) }} CBM.</div>
                @elseif($freightCategory === 'local')
                    @if($result['delivery_area'] ?? null)
                        <div class="calc-muted-note">Delivery rate: {{ $result['delivery_area']->name }}. {{ ($result['delivery_area']->pricing_mode ?? 'fixed') === 'distance' ? 'Distance-based' : 'Fixed rate' }}.</div>
                    @endif
                @else
                    <div class="calc-muted-note">Weight billing: {{ match($result['billing_weight_mode'] ?? 'actual') { 'round_up' => 'Round up', 'round_down' => 'Round down', default => 'Bill as-is' } }}. Entered weight: {{ number_format($result['entered_measure'] ?? 0, 2) }} lb.</div>
                @endif
                <div class="calc-muted-note">Duty rate: {{ number_format(($result['duty_rate'] ?? $customsConfig['duty_rate']) * 100, 2) }}% 
                    @if($result['hs_code'] ?? null)
                        from HS {{ $result['hs_code']->code }}
                    @else
                        default
                    @endif
                .</div>
            @endif

            <div class="grand-total">
                <div class="grand-total-label">Estimated Total</div>
                <div class="grand-total-value">{{ number_format($estimatedTotal, 2) }} <small>JMD</small></div>
            </div>
        </section>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const seaPanel = document.querySelector('[data-sea-panel]');
    const localPanel = document.querySelector('[data-local-panel]');
    const weightField = document.querySelector('[data-weight-field]');
    const modeInputs = document.querySelectorAll('[name="freight_type"]');
    const hsSearch = document.querySelector('[data-hs-code-search]');
    const hsSelect = document.querySelector('[data-hs-code-select]');

    if (hsSearch && hsSelect) {
        hsSearch.addEventListener('input', function () {
            const term = hsSearch.value.trim().toLowerCase();
            Array.from(hsSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                option.hidden = term !== '' && !option.text.toLowerCase().includes(term);
            });
        });
    }

    function syncFreightFields() {
        const selected = document.querySelector('[name="freight_type"]:checked');
        const category = selected?.dataset.category || 'air';
        const sea = category === 'sea';
        const local = category === 'local';
        if (seaPanel) seaPanel.classList.toggle('is-visible', sea);
        if (localPanel) localPanel.classList.toggle('is-visible', local);
        if (weightField) {
            weightField.required = !sea && !local;
            weightField.closest('div').style.display = sea || local ? 'none' : '';
        }
    }

    modeInputs.forEach(input => input.addEventListener('change', syncFreightFields));
    syncFreightFields();
});
</script>
@endsection



