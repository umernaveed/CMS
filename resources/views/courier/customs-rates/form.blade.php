<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">HS Code</label>
        <input name="code" class="form-control" value="{{ old('code', optional($rate)->code) }}" placeholder="8517.13" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Duty Rate (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="duty_rate" class="form-control" value="{{ old('duty_rate', optional($rate)->duty_rate ?? 20) }}" required>
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check pb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', optional($rate)->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" rows="4" class="form-control" required>{{ old('description', optional($rate)->description) }}</textarea>
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Save Customs Rate</button>
        <a href="{{ route('courier.customs-rates.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
