<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Branch Code</label><input name="outlet_code" class="form-control" value="{{ old('outlet_code', optional($outlet)->outlet_code) }}" required></div>
    <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', optional($outlet)->name) }}" required></div>
    <div class="col-md-4"><label class="form-label">Short Name</label><input name="short_name" class="form-control" value="{{ old('short_name', optional($outlet)->short_name) }}"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', optional($outlet)->email) }}"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', optional($outlet)->phone) }}"></div>
    <div class="col-md-4"><label class="form-label">Manager</label><input name="manager_name" class="form-control" value="{{ old('manager_name', optional($outlet)->manager_name) }}"></div>
    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', optional($outlet)->city) }}"></div>
    <div class="col-md-4"><label class="form-label">Parish</label><input name="parish" class="form-control" value="{{ old('parish', optional($outlet)->parish) }}"></div>
    <div class="col-md-4">
        <label class="form-label">Rate Group</label>
        <select name="rate_group_mode" class="form-select">
            <option value="main" @selected(old('rate_group_mode', optional($outlet)->rate_group_mode ?? 'main') === 'main')>Use main branch rates</option>
            <option value="custom" @selected(old('rate_group_mode', optional($outlet)->rate_group_mode) === 'custom')>Own branch rates</option>
        </select>
        <small class="text-muted">Custom rates apply to air and sea when configured.</small>
    </div>
    <div class="col-md-4 d-flex align-items-end gap-4">
        <div class="form-check"><input type="hidden" name="is_main" value="0"><input class="form-check-input" type="checkbox" name="is_main" value="1" @checked(old('is_main', optional($outlet)->is_main))><label class="form-check-label">Main branch</label></div>
        <div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($outlet)->is_active ?? true))><label class="form-check-label">Active</label></div>
    </div>
    <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', optional($outlet)->address) }}"></div>
    @if(! $outlet)
        <div class="col-12"><hr><h2 class="h6">Optional Staff Login</h2></div>
        <div class="col-md-4"><label class="form-label">Staff Name</label><input name="staff_name" class="form-control" value="{{ old('staff_name') }}"></div>
        <div class="col-md-4"><label class="form-label">Staff Email</label><input type="email" name="staff_email" class="form-control" value="{{ old('staff_email') }}"></div>
        <div class="col-md-4"><label class="form-label">Staff Password</label><input type="password" name="staff_password" class="form-control"></div>
    @endif
    <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Save Branch</button><a href="{{ route('courier.outlets.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</div>
