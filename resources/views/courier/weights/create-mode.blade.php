@extends('layouts.app')

@section('title', 'Create Shipping Mode')
@section('page_title', 'Create Shipping Mode')
@section('breadcrumb', 'Create Shipping Mode')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Create Shipping Mode</h1>
        <p class="text-muted mb-0">Add a new route or freight mode before creating its rate ranges.</p>
    </div>
    <a href="{{ route('courier.weights.index') }}#modes-panel" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Rates</a>
</div>

<form method="POST" action="{{ route('courier.weights.shipping-modes.store') }}" class="panel p-4">
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Mode Name</label>
            <input name="name" class="form-control" value="{{ old('name') }}" placeholder="China Air to Jamaica" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Code</label>
            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="china-air-ja">
            <div class="form-text">Leave blank to generate from the name.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
                <option value="air" @selected(old('category', 'air') === 'air')>Air / weight</option>
                <option value="sea" @selected(old('category') === 'sea')>Sea / volume</option>
                <option value="local" @selected(old('category') === 'local')>Local / delivery</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 100) }}" min="0" max="9999">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check pb-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check pb-2">
                <input type="hidden" name="is_default" value="0">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default', false))>
                <label class="form-check-label" for="is_default">Default for category</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <input name="description" class="form-control" value="{{ old('description') }}" maxlength="160" placeholder="By weight, cargo route">
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
            <button class="btn btn-primary"><i class="fas fa-check me-1"></i>Create Mode</button>
            <a href="{{ route('courier.weights.index') }}#modes-panel" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
@endsection

