@extends('layouts.app')

@section('title', 'Add Pre-Alert')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Add Package Pre-Alert</h1><p class="text-muted mb-0">Tell the courier about an incoming package before it reaches the warehouse.</p></div>
<form method="POST" action="{{ route('customer.packages.store') }}" class="panel p-4" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Supplier Tracking</label><input name="supplier_tracking" class="form-control" value="{{ old('supplier_tracking') }}"></div>
        <div class="col-md-4"><label class="form-label">Courier</label><input name="courier" class="form-control" value="{{ old('courier') }}"></div>
        <div class="col-md-4"><label class="form-label">Merchant</label><input name="merchant" class="form-control" value="{{ old('merchant') }}"></div>
        <div class="col-md-4"><label class="form-label">Declared Value USD</label><input type="number" step="0.01" name="price_usd" class="form-control" value="{{ old('price_usd', 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Freight</label><select name="freight_type" class="form-select">@foreach(($shippingModes ?? collect()) as $mode)<option value="{{ $mode->code }}" @selected(old('freight_type', optional(($shippingModes ?? collect())->first())->code ?? 'air') === $mode->code)>{{ $mode->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Preferred Handling</label><select name="delivery_type" class="form-select"><option value="pickup">Pickup</option><option value="delivery">Delivery</option></select></div>
        <div class="col-md-4"><label class="form-label">Purchase Invoices</label><input type="file" name="purchase_receipts[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple><div class="form-text">Upload up to 5 files. Accepted formats: PDF, JPEG, JPG, PNG.</div></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" required>{{ old('description') }}</textarea></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
        <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Submit Pre-Alert</button><a href="{{ route('customer.packages') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
@endsection

