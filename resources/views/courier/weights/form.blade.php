<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Freight Type</label>
        <select name="freight_type" class="form-select" data-rate-freight-type>
            @foreach(($shippingModes ?? collect())->where('is_active', true) as $mode)
                <option value="{{ $mode->code }}" data-category="{{ $mode->category }}" @selected(old('freight_type', optional($rate)->freight_type ?? optional(($shippingModes ?? collect())->where('is_active', true)->first())->code ?? 'air') === $mode->code)>{{ $mode->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Pricing Tier</label>
        <select name="pricing_tier_id" class="form-select">
            <option value="">All / Default Rates</option>
            @foreach($tiers ?? [] as $tier)
                <option value="{{ $tier->id }}" @selected(old('pricing_tier_id', optional($rate)->pricing_tier_id)==$tier->id)>{{ $tier->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Branch Rate Group</label>
        <select name="outlet_id" class="form-select">
            <option value="">Main / shared rates</option>
            @foreach($outlets ?? [] as $outlet)
                <option value="{{ $outlet->id }}" @selected(old('outlet_id', optional($rate)->outlet_id)==$outlet->id)>{{ $outlet->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Region</label>
        @php($selectedRateRegion = old('region', optional($rate)->region))
        <select name="region" class="form-select">
            <option value="" @selected($selectedRateRegion === null || $selectedRateRegion === '')>All regions</option>
            <option value="USA" @selected(strtolower((string) $selectedRateRegion) === 'usa' || strtolower((string) $selectedRateRegion) === 'us')>USA</option>
            <option value="China" @selected(strtolower((string) $selectedRateRegion) === 'china' || strtolower((string) $selectedRateRegion) === 'cn')>China</option>
            @if(! in_array(strtolower((string) $selectedRateRegion), ['usa', 'us', 'china', 'cn', ''], true))
                <option value="{{ $selectedRateRegion }}" selected>{{ $selectedRateRegion }}</option>
            @endif
        </select>
    </div>
    <div class="col-md-3" data-volume-unit-field>
        <label class="form-label">Sea Billing Unit</label>
        <select name="volume_unit" class="form-select">
            <option value="cuft" @selected(old('volume_unit', optional($rate)->volume_unit ?? 'cuft') === 'cuft')>Cubic foot (cu.ft)</option>
            <option value="cbm" @selected(old('volume_unit', optional($rate)->volume_unit) === 'cbm')>Cubic meter (CBM)</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label" data-min-rate-label>Min Weight</label><input type="number" step="0.01" name="min_weight" class="form-control" value="{{ old('min_weight', optional($rate)->min_weight ?? 0) }}" required></div>
    <div class="col-md-3">
        <label class="form-label" data-max-rate-label>Max Weight</label>
        <input type="number" step="0.01" name="max_weight" class="form-control" value="{{ old('max_weight', optional($rate)->max_weight) }}" placeholder="Leave blank for no maximum">
        <small class="text-muted">Blank means this range continues indefinitely.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label" data-minimum-billable-label>Minimum Billable Weight</label>
        <input type="number" step="0.01" name="minimum_billable_measure" class="form-control" value="{{ old('minimum_billable_measure', optional($rate)->minimum_billable_measure ?? optional($rate)->min_weight ?? 0) }}">
    </div>
    <div class="col-md-3"><label class="form-label">Processing Fee JMD</label><input type="number" step="0.01" name="processing_fee_jmd" class="form-control" value="{{ old('processing_fee_jmd', optional($rate)->processing_fee_jmd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label" data-price-rate-label>Regular Range Price JMD</label><input type="number" step="0.01" name="price_jmd" class="form-control" value="{{ old('price_jmd', optional($rate)->price_jmd ?? 0) }}" required></div>
    <div class="col-md-3"><label class="form-label" data-express-rate-label>Express Range Price JMD</label><input type="number" step="0.01" name="express_price_jmd" class="form-control" value="{{ old('express_price_jmd', optional($rate)->express_price_jmd ?? 0) }}"></div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', optional($rate)->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">Save Rate</button>
        <a href="{{ route('courier.weights.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const freight = document.querySelector('[data-rate-freight-type]');
    if (!freight) return;
    const minLabel = document.querySelector('[data-min-rate-label]');
    const maxLabel = document.querySelector('[data-max-rate-label]');
    const priceLabel = document.querySelector('[data-price-rate-label]');
    const expressLabel = document.querySelector('[data-express-rate-label]');
    const unitSelect = document.querySelector('[name="volume_unit"]');

    function selectedCategory() {
        return freight.options[freight.selectedIndex]?.dataset.category || 'air';
    }

    function syncLabels() {
        const sea = selectedCategory() === 'sea';
        const seaUnit = unitSelect && unitSelect.value === 'cbm' ? 'CBM' : 'cu.ft';
        if (minLabel) minLabel.textContent = sea ? 'Min Volume (' + seaUnit + ')' : 'Min Weight';
        if (maxLabel) maxLabel.textContent = sea ? 'Max Volume (' + seaUnit + ')' : 'Max Weight';
        if (priceLabel) priceLabel.textContent = 'Regular Range Price JMD';
        if (expressLabel) expressLabel.textContent = 'Express Range Price JMD';
        document.querySelector('[data-minimum-billable-label]').textContent = sea ? 'Minimum Billable Volume (' + seaUnit + ')' : 'Minimum Billable Weight';
        const unitField = document.querySelector('[data-volume-unit-field]');
        if (unitField) unitField.style.display = sea ? '' : 'none';
    }

    freight.addEventListener('change', syncLabels);
    unitSelect && unitSelect.addEventListener('change', syncLabels);
    syncLabels();
});
</script>

