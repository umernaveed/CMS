<div class="alert alert-info">
    <strong>Please note:</strong> Pre-Alerts are not packages. They let your courier see expected incoming online orders and prepare accurate customs clearance information before the warehouse receives the shipment.
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Supplier Tracking Number</label>
        <input name="supplier_tracking" class="form-control" value="{{ old('supplier_tracking', optional($preAlert)->supplier_tracking) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Courier / Carrier</label>
        <select name="courier" class="form-select">
            <option value="">Select carrier</option>
            @foreach($carriers as $carrier)
                <option value="{{ $carrier }}" @selected(old('courier', optional($preAlert)->courier) === $carrier)>{{ $carrier }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Merchant</label>
        <input name="merchant" class="form-control" value="{{ old('merchant', optional($preAlert)->merchant) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Declared Value USD</label>
        <input type="number" step="0.01" min="0" name="price_usd" class="form-control" value="{{ old('price_usd', optional($preAlert)->price_usd ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Preferred Handling</label>
        <select name="delivery_type" class="form-select">
            <option value="pickup" @selected(old('delivery_type', optional($preAlert)->delivery_type ?? 'pickup') === 'pickup')>Pickup</option>
            <option value="delivery" @selected(old('delivery_type', optional($preAlert)->delivery_type) === 'delivery')>Delivery</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Purchase Receipts</label>
        <input type="file" name="purchase_receipts[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
        <small class="text-muted">Upload images or PDFs. Multiple files are allowed.</small>
    </div>
    @if(optional($preAlert)->receipts && $preAlert->receipts->isNotEmpty())
        <div class="col-12">
            <label class="form-label">Current Receipts</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach($preAlert->receipts as $receipt)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('customer.pre-alerts.receipt', $receipt) }}">
                        <i class="fas fa-download me-1"></i>{{ $receipt->original_name ?: 'Receipt '.$loop->iteration }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" required>{{ old('description', optional($preAlert)->description) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes', optional($preAlert)->notes) }}</textarea>
    </div>
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">{{ $button ?? 'Submit Pre-Alert' }}</button>
        <a href="{{ route('customer.pre-alerts.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
