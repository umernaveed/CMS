<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="{{ old('full_name', optional($staff ?? null)->full_name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', optional($staff ?? null)->email) }}" required></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', optional($staff ?? null)->phone) }}"></div>
    <div class="col-md-4">
        <label class="form-label">User Type</label>
        <select name="user_type" class="form-select">
            <option value="staff" @selected(old('user_type', optional($staff ?? null)->user_type ?? 'staff') === 'staff')>Staff</option>
            <option value="tenant_admin" @selected(old('user_type', optional($staff ?? null)->user_type) === 'tenant_admin')>Courier Admin</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Outlet</label>
        <select name="outlet_id" class="form-select">
            <option value="">All outlets</option>
            @foreach($outlets as $outlet)
                <option value="{{ $outlet->id }}" @selected(old('outlet_id', optional($staff ?? null)->outlet_id) == $outlet->id)>{{ $outlet->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" {{ empty($staff) ? 'required' : '' }}><small class="text-muted">{{ empty($staff) ? 'Required' : 'Leave blank to keep current password' }}</small></div>
    <div class="col-12">
        <label class="form-label">Roles</label>
        <div class="row g-2">
            @php $selectedRoles = old('role_ids', !empty($staff) ? $staff->roles->pluck('id')->all() : []); @endphp
            @foreach($roles as $role)
                <div class="col-md-4">
                    <label class="form-check border rounded p-2 d-block">
                        <input class="form-check-input me-2" type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles))>
                        {{ $role->display_name ?? $role->name }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check pt-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', optional($staff ?? null)->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">Save Staff</button>
        <a href="{{ route('courier.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
