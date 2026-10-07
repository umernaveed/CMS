<form action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    @php
        $deliveryType = old(
            'delivery_type',
            in_array(optional($purchaseRequest)->delivery_type, ['air', 'sea'], true)
                ? optional($purchaseRequest)->delivery_type
                : 'air'
        );
    @endphp

    <div class="row gy-3">
        <div class="col-md-6">
            <label class="form-label">Product URL</label>
            <input type="url" name="product_url" value="{{ old('product_url', optional($purchaseRequest)->product_url) }}" class="form-control @error('product_url') is-invalid @enderror">
            @error('product_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" min="0" name="price_usd" value="{{ old('price_usd', optional($purchaseRequest)->price_usd) }}" class="form-control @error('price_usd') is-invalid @enderror">
            @error('price_usd')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Delivery Type <span class="text-danger">*</span></label>
            <select name="delivery_type" class="form-select @error('delivery_type') is-invalid @enderror" required>
                <option value="air" @selected($deliveryType === 'air')>Air</option>
                <option value="sea" @selected($deliveryType === 'sea')>Sea</option>
            </select>
            @error('delivery_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label">Description <span class="text-danger">*</span></label>
            <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', optional($purchaseRequest)->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', optional($purchaseRequest)->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label">Upload File</label>
            <input type="file" name="purchase_receipts[]" multiple class="form-control @error('purchase_receipts') is-invalid @enderror">
            <small class="text-muted d-block">Upload up to 8 files (pdf, jpg, jpeg, png, webp).</small>
            @error('purchase_receipts')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('purchase_receipts.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        @if($purchaseRequest && $purchaseRequest->receipts->isNotEmpty())
        <div class="col-12">
            <label class="form-label">Existing Receipts</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach($purchaseRequest->receipts as $receipt)
                    <a href="{{ route('customer.purchase-requests.receipt', $receipt) }}" class="btn btn-sm btn-outline-secondary">{{ $receipt->original_name }}</a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="col-12 pt-3">
            <button class="btn btn-primary">{{ $method === 'POST' ? 'Submit Request' : 'Save Changes' }}</button>
        </div>
    </div>
</form>
