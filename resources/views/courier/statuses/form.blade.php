<div class="row g-3">
    <div class="col-md-5"><label class="form-label">Status Name</label><input name="name" class="form-control" value="{{ old('name', optional($status)->name) }}" required></div>
    <div class="col-md-3"><label class="form-label">Color</label><input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', optional($status)->color ?: '#64748b') }}" required></div>
    <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', optional($status)->sort_order ?? 0) }}"></div>
    <div class="col-md-2"><label class="form-label">Icon Class</label><input name="icon" class="form-control" value="{{ old('icon', optional($status)->icon) }}" placeholder="fas fa-box"></div>
    <div class="col-md-3">
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default', optional($status)->is_default))><label class="form-check-label" for="is_default">Default status</label></div>
    </div>
    <div class="col-md-3">
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', optional($status)->is_active ?? true))><label class="form-check-label" for="is_active">Active</label></div>
    </div>
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">Save Status</button>
        <a href="{{ route('courier.statuses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
