<div class="row g-3">
    @if(! empty($logisticsPackage))
        <input type="hidden" name="logistics_package_id" value="{{ $logisticsPackage->id }}">
    @endif
    <div class="col-md-4">
        <label class="form-label">In-house HAWB</label>
        <input name="tracking_number" class="form-control text-uppercase" value="{{ old('tracking_number', optional($package)->tracking_number) }}" placeholder="Auto-generated">
        <small class="text-muted">Leave blank to generate from the courier code, for example NCLS000001.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Supplier Tracking</label>
        <input
            name="supplier_tracking"
            class="form-control"
            value="{{ old('supplier_tracking', optional($package)->supplier_tracking ?? optional($preAlert ?? null)->supplier_tracking ?? optional($logisticsPackage ?? null)->tracking_number) }}"
        >
    </div>
    <div class="col-md-4">
        <label class="form-label">Customer</label>
        <input type="search" class="form-control mb-2" data-search-select="customer_id" placeholder="Search mailbox, name, email, or phone">
        <select name="customer_id" class="form-select" id="customer_id">
            <option value="" data-mailbox="" data-outlet="">Unassigned</option>
            @foreach($customers as $customer)
                <option
                    value="{{ $customer->id }}"
                    data-mailbox="{{ $customer->mailbox }}"
                    data-outlet="{{ $customer->outlet_id }}"
                    data-search-text="{{ strtolower(trim($customer->mailbox.' '.optional($customer->user)->full_name.' '.optional($customer->user)->email.' '.optional($customer->user)->phone)) }}"
                    @selected(old('customer_id', optional($package)->customer_id ?? optional($preAlert ?? null)->customer_id) == $customer->id)
                >
                    {{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Status</label><select name="status_id" class="form-select">@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(old('status_id', optional($package)->status_id)==$status->id)>{{ $status->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Package Type</label><select name="package_type_id" class="form-select"><option value="">General</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('package_type_id', optional($package)->package_type_id)==$type->id)>{{ $type->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Mailbox</label><input name="mailbox" id="mailbox" class="form-control" value="{{ old('mailbox', optional($package)->mailbox) }}"></div>
    <div class="col-md-4"><label class="form-label">Courier</label><input name="courier" class="form-control" value="{{ old('courier', optional($package)->courier ?? optional($preAlert ?? null)->courier ?? optional(optional($logisticsPackage ?? null)->manifest)->cargo_company) }}"></div>
    <div class="col-md-4"><label class="form-label">Merchant</label><input name="merchant" class="form-control" value="{{ old('merchant', optional($package)->merchant ?? optional($preAlert ?? null)->merchant ?? (! empty($logisticsPackage) ? 'Warehouse' : null)) }}"></div>
    <div class="col-md-4"><label class="form-label">Received Date</label><input type="date" name="date_received" class="form-control" value="{{ old('date_received', optional(optional($package)->date_received)->format('Y-m-d') ?? optional(optional($logisticsPackage ?? null)->checked_in_at)->format('Y-m-d') ?? now()->toDateString()) }}"></div>
    <div class="col-md-3"><label class="form-label" id="package-measure-label">Weight (lb)</label><input type="number" step="0.01" name="weight" id="weight" class="form-control" value="{{ old('weight', optional($package)->weight ?? optional($logisticsPackage ?? null)->weight ?? 0) }}"><small class="text-muted" id="package-measure-conversion"></small></div>
    @php
        $selectedRegion = old('region', optional($package)->region ?? 'USA');
        $selectedRegionKey = strtolower((string) $selectedRegion);
    @endphp
    <div class="col-md-3">
        <label class="form-label">Region</label>
        <select name="region" id="region" class="form-select">
            <option value="USA" @selected(in_array($selectedRegionKey, ['usa', 'us', ''], true))>USA</option>
            <option value="China" @selected(in_array($selectedRegionKey, ['china', 'cn'], true))>China</option>
            @if(! in_array($selectedRegionKey, ['usa', 'us', 'china', 'cn', ''], true))
                <option value="{{ $selectedRegion }}" selected>{{ $selectedRegion }}</option>
            @endif
        </select>
        <small class="text-muted" id="label_type_hint"></small>
    </div>
    <div class="col-md-3 sea-volume-field d-none"><label class="form-label">Cubic Feet</label><input type="number" step="0.0001" min="0" name="volume_cuft" id="volume_cuft" class="form-control" value="{{ old('volume_cuft', optional($package)->volume_cuft) }}"></div>
    <div class="col-md-3 sea-volume-field d-none"><label class="form-label">Cubic Meter</label><input type="number" step="0.0001" min="0" name="volume_cbm" id="volume_cbm" class="form-control" value="{{ old('volume_cbm', optional($package)->volume_cbm) }}"></div>
    <div class="col-md-3"><label class="form-label">USD Value</label><input type="number" step="0.01" name="price_usd" class="form-control" value="{{ old('price_usd', optional($package)->price_usd ?? optional($preAlert ?? null)->price_usd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">JMD Price</label><input type="number" step="0.01" name="price_jmd" id="price_jmd" class="form-control" value="{{ old('price_jmd', optional($package)->price_jmd ?? 0) }}"><small class="text-muted" id="rate_hint">Auto-picked from Manage Weight when available.</small></div>
    <div class="col-md-3"><label class="form-label">Storage Fee</label><input type="number" step="0.01" name="storage_fee" class="form-control" value="{{ old('storage_fee', optional($package)->storage_fee ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">Freight</label><select name="freight_type" id="freight_type" class="form-select">@foreach(($shippingModes ?? collect())->where('is_active', true) as $mode)<option value="{{ $mode->code }}" data-category="{{ $mode->category }}" @selected(old('freight_type', optional($package)->freight_type ?? optional(($shippingModes ?? collect())->where('is_active', true)->first())->code ?? 'air')===$mode->code)>{{ $mode->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Delivery</label><select name="delivery_type" id="delivery_type" class="form-select"><option value="pickup" @selected(old('delivery_type', optional($package)->delivery_type ?? optional($preAlert ?? null)->delivery_type ?? 'pickup')==='pickup')>Pickup</option><option value="delivery" @selected(old('delivery_type', optional($package)->delivery_type ?? optional($preAlert ?? null)->delivery_type)==='delivery')>Delivery</option></select></div>
    @if($package)<div class="col-md-3"><label class="form-label">Storage Days</label><input type="number" name="storage_days" class="form-control" value="{{ old('storage_days', $package->storage_days) }}"></div><div class="col-md-3"><label class="form-label">Delivered At</label><input type="date" name="date_delivered" class="form-control" value="{{ old('date_delivered', optional($package->date_delivered)->format('Y-m-d')) }}"></div>@endif
    <div class="col-md-6">
        <label class="form-label">Purchase Receipt</label>
        <input type="file" name="purchase_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
        @if(optional($package)->invoice_file)
            <small><a href="{{ route('courier.packages.receipt', $package) }}">Download current receipt</a></small>
        @endif
    </div>
    <div class="col-md-3"><label class="form-label">Unknown Fee JMD</label><input type="number" step="0.01" min="0" name="unknown_fee_jmd" class="form-control" value="{{ old('unknown_fee_jmd', optional($package)->unknown_fee_jmd ?? 0) }}"></div>
    <div class="col-md-9"><label class="form-label">Unknown Fee Reason</label><input name="unknown_fee_reason" class="form-control" value="{{ old('unknown_fee_reason', optional($package)->unknown_fee_reason) }}"></div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', optional($package)->description ?? optional($preAlert ?? null)->description ?? optional($logisticsPackage ?? null)->description) }}</textarea></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', optional($package)->notes ?? optional($preAlert ?? null)->notes) }}</textarea></div>
    <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Save Package</button><a href="{{ route('courier.packages.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</div>
@php
    $rateOptions = ($rates ?? collect())->map(function ($rate) {
        return [
            'freight_type' => $rate->freight_type,
            'freight_category' => $rate->freightCategory(),
            'region' => $rate->region,
            'volume_unit' => $rate->volume_unit ?? 'cuft',
            'min_weight' => (float) $rate->min_weight,
            'max_weight' => $rate->max_weight !== null ? (float) $rate->max_weight : null,
            'price_jmd' => (float) $rate->price_jmd,
            'express_price_jmd' => (float) $rate->express_price_jmd,
            'minimum_billable_measure' => (float) ($rate->minimum_billable_measure ?? 0),
            'outlet_id' => $rate->outlet_id,
        ];
    })->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rates = @json($rateOptions);
    const billingWeightMode = @json($billingWeightMode ?? 'actual');
    const customer = document.getElementById('customer_id');
    const mailbox = document.getElementById('mailbox');
    const weight = document.getElementById('weight');
    const region = document.getElementById('region');
    const volumeCuft = document.getElementById('volume_cuft');
    const volumeCbm = document.getElementById('volume_cbm');
    const freight = document.getElementById('freight_type');
    const delivery = document.getElementById('delivery_type');
    const price = document.getElementById('price_jmd');
    const hint = document.getElementById('rate_hint');
    const measureLabel = document.getElementById('package-measure-label');
    const measureConversion = document.getElementById('package-measure-conversion');
    const labelTypeHint = document.getElementById('label_type_hint');
    const seaVolumeFields = document.querySelectorAll('.sea-volume-field');

    function syncMailbox() {
        const selected = customer.options[customer.selectedIndex];
        if (selected && selected.dataset.mailbox) {
            mailbox.value = selected.dataset.mailbox;
        }
    }

    function normalizedRegion(value) {
        const key = String(value || 'USA').trim().toLowerCase();
        if (key === 'cn' || key === 'china') return 'china';
        if (key === 'us' || key === 'usa' || key === '') return 'usa';
        return key;
    }

    function currentFreightCategory() {
        return freight.options[freight.selectedIndex]?.dataset.category || 'air';
    }

    function updateLabelTypeHint() {
        if (!labelTypeHint) return;
        const regionCode = normalizedRegion(region?.value) === 'china' ? 'CN' : (normalizedRegion(region?.value) === 'usa' ? 'US' : String(region?.value || '').trim().toUpperCase());
        labelTypeHint.textContent = regionCode + ' ' + (currentFreightCategory() === 'sea' ? 'Sea' : 'Air');
    }

    function syncSeaVolume(source) {
        const isSea = currentFreightCategory() === 'sea';
        seaVolumeFields.forEach(field => field.classList.toggle('d-none', !isSea));
        if (!isSea) {
            updateLabelTypeHint();
            return;
        }

        let cuft = Number(volumeCuft?.value || 0);
        let cbm = Number(volumeCbm?.value || 0);
        if (source === 'cbm') {
            cuft = cbm > 0 ? cbm * 35.3147 : 0;
            if (volumeCuft) volumeCuft.value = cuft > 0 ? cuft.toFixed(4) : '';
            if (weight) weight.value = cuft > 0 ? cuft.toFixed(2) : '';
        } else {
            if (source === 'weight') {
                cuft = Number(weight?.value || 0);
                if (volumeCuft) volumeCuft.value = cuft > 0 ? cuft.toFixed(4) : '';
            } else if (source === 'cuft' || cuft > 0) {
                if (weight && cuft > 0) weight.value = cuft.toFixed(2);
            } else if (Number(weight?.value || 0) > 0) {
                cuft = Number(weight.value || 0);
                if (volumeCuft) volumeCuft.value = cuft.toFixed(4);
            }
            cbm = cuft > 0 ? cuft / 35.3147 : 0;
            if (volumeCbm) volumeCbm.value = cbm > 0 ? cbm.toFixed(4) : '';
        }
        updateLabelTypeHint();
    }

    function syncRate(force = false, source = null) {
        const currentPrice = Number(price.value || 0);
        if (!force && currentPrice > 0) {
            syncSeaVolume(source);
            return;
        }

        syncSeaVolume(source);
        const actualWeight = Number(weight.value || 0);
        const selectedFreightCategory = currentFreightCategory();
        if (measureLabel) measureLabel.textContent = selectedFreightCategory === 'sea' ? 'Sea Volume (cu.ft)' : 'Weight (lb)';
        if (measureConversion) measureConversion.textContent = selectedFreightCategory === 'sea'
            ? actualWeight.toFixed(2) + ' cu.ft = ' + (actualWeight / 35.3147).toFixed(3) + ' CBM'
            : actualWeight.toFixed(2) + ' lb = ' + (actualWeight * 0.45359237).toFixed(2) + ' kg';
        const packageWeight = actualWeight > 0
            ? (billingWeightMode === 'round_up'
                ? Math.ceil(actualWeight)
                : (billingWeightMode === 'round_down' ? Math.max(Math.floor(actualWeight), 1) : actualWeight))
            : 0;
        const selected = customer.options[customer.selectedIndex];
        const outletId = selected ? Number(selected.dataset.outlet || 0) : 0;
        const matchingRates = rates.filter(item => {
            if (item.freight_type !== freight.value) return false;
            const rateRegion = item.region ? normalizedRegion(item.region) : '';
            const packageRegion = normalizedRegion(region?.value || 'USA');
            if (rateRegion && rateRegion !== packageRegion) return false;
            const itemCategory = item.freight_category || (item.freight_type === 'sea' ? 'sea' : 'air');
            const comparableMeasure = itemCategory === 'sea' && item.volume_unit === 'cbm' ? packageWeight / 35.3147 : packageWeight;
            return comparableMeasure >= item.min_weight && (item.max_weight === null || comparableMeasure <= item.max_weight);
        });
        matchingRates.sort((a, b) => {
            const packageRegion = normalizedRegion(region?.value || 'USA');
            const aExact = a.region && normalizedRegion(a.region) === packageRegion ? 0 : 1;
            const bExact = b.region && normalizedRegion(b.region) === packageRegion ? 0 : 1;
            return aExact - bExact;
        });
        const rate = matchingRates.find(item => Number(item.outlet_id || 0) === outletId) || matchingRates.find(item => !item.outlet_id);

        if (!rate) {
            if (hint) hint.textContent = 'No matching Manage Weight rate found.';
            return;
        }

        const unitRate = delivery.value === 'delivery'
            ? (rate.express_price_jmd || rate.price_jmd)
            : rate.price_jmd;
        const rateCategory = rate.freight_category || (rate.freight_type === 'sea' ? 'sea' : 'air');
        const convertedMeasure = rateCategory === 'sea' && rate.volume_unit === 'cbm' ? packageWeight / 35.3147 : packageWeight;
        const billableMeasure = Math.max(convertedMeasure, Number(rate.minimum_billable_measure || 0));
        const amount = rateCategory === 'sea' ? unitRate * billableMeasure : unitRate;
        price.value = Number(amount).toFixed(2);
        const unit = rateCategory === 'sea' ? (rate.volume_unit === 'cbm' ? 'CBM' : 'cu.ft') : 'lb';
        const modeLabel = billingWeightMode === 'round_up' ? 'round up' : (billingWeightMode === 'round_down' ? 'round down' : 'bill as-is');
        if (hint) hint.textContent = 'Auto-picked from Manage Weight: ' + rate.min_weight + '-' + (rate.max_weight ?? 'no max') + ' ' + unit + ' range = JMD ' + Number(unitRate).toFixed(2) + '. Actual: ' + actualWeight.toFixed(2) + ', billable: ' + billableMeasure.toFixed(2) + ' ' + unit + ' (' + modeLabel + ').';
    }

    customer && customer.addEventListener('change', syncMailbox);
    [freight, delivery, region].forEach(field => field && field.addEventListener('change', () => syncRate(true)));
    weight && weight.addEventListener('input', () => syncRate(true, 'weight'));
    volumeCuft && volumeCuft.addEventListener('input', () => syncRate(true, 'cuft'));
    volumeCbm && volumeCbm.addEventListener('input', () => syncRate(true, 'cbm'));

    syncMailbox();
    syncRate(false);
});
</script>


