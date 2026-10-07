<style>
    .role-editor { max-width: 760px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .role-editor-title { font-size: 16px; font-weight: 800; color: #111827; margin: 0 0 16px; }
    .role-editor .form-label { color: #374151; font-size: 12px; font-weight: 800; margin-bottom: 5px; }
    .role-editor .form-control, .role-editor .form-select { border-radius: 4px; font-size: 13px; min-height: 34px; }
    .role-editor textarea.form-control { min-height: 78px; resize: vertical; }
    .permission-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) 190px; gap: 10px; align-items: center; margin-bottom: 10px; }
    .permission-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
    .permission-actions .btn { font-size: 12px; padding: 5px 9px; border-radius: 4px; }
    .permission-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 28px; }
    .permission-module-card { border-top: 1px solid #eef2f7; padding-top: 12px; margin-top: 12px; }
    .permission-module-title { display: flex; justify-content: space-between; align-items: center; gap: 12px; color: #111827; font-size: 12px; font-weight: 900; margin-bottom: 8px; }
    .permission-module-title label { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
    .permission-module-state { color: #64748b; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .permission-check { min-height: 22px; margin: 0; color: #374151; font-size: 12px; }
    .permission-check .form-check-input { margin-top: 2px; }
    .role-editor-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #eef2f7; }
    .role-editor-status { max-width: 220px; }
    @media (max-width: 767px) {
        .permission-toolbar { grid-template-columns: 1fr; }
        .permission-grid { grid-template-columns: 1fr; }
        .role-editor-footer { justify-content: flex-start; flex-wrap: wrap; }
    }
</style>

<div class="role-editor">
    <h2 class="role-editor-title">{{ !empty($role) ? 'Edit Role' : 'Add Role' }}</h2>

    <div class="mb-3">
        <label class="form-label">Role Name</label>
        <input name="display_name" class="form-control" value="{{ old('display_name', optional($role ?? null)->display_name) }}" required @if(!empty($role) && $role->is_system) readonly @endif>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" @if(!empty($role) && $role->is_system) readonly @endif>{{ old('description', optional($role ?? null)->description) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Permissions</label>
        @php $selectedPermissions = old('permission_ids', !empty($role) ? $role->permissions->pluck('id')->all() : []); @endphp
        <div class="permission-toolbar">
            <input type="search" class="form-control" placeholder="Search permissions..." data-permission-search>
            <select class="form-select" data-permission-module>
                <option value="">All Modules</option>
                @foreach($permissions->keys() as $moduleName)
                    <option value="{{ Str::slug($moduleName ?: 'general') }}">{{ $moduleName ?: 'General' }}</option>
                @endforeach
            </select>
        </div>
        <div class="permission-actions">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-select-visible-permissions>Select all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-visible-permissions>Clear selected</button>
            </div>
            <span class="text-muted small" data-selected-permission-count>{{ count($selectedPermissions) }} selected</span>
        </div>

        @foreach($permissions as $module => $items)
            <div class="permission-module-card" data-permission-module-card="{{ Str::slug($module ?: 'general') }}">
                <div class="permission-module-title">
                    <label>
                        <input class="form-check-input" type="checkbox" data-module-check>
                        <span>{{ $module ?: 'General' }}</span>
                    </label>
                    <span class="permission-module-state" data-module-state>Restricted</span>
                </div>
                <div class="permission-grid">
                    @foreach($items as $permission)
                        <label class="form-check permission-check" data-permission-item data-permission-text="{{ strtolower(($permission->display_name ?? $permission->name).' '.$permission->name.' '.$module) }}">
                            <input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissions))>
                            <span class="form-check-label">{{ $permission->display_name ?? $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-3 role-editor-status">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select">
            <option value="1" @selected(old('is_active', optional($role ?? null)->is_active ?? true))>Active</option>
            <option value="0" @selected(! old('is_active', optional($role ?? null)->is_active ?? true))>Disabled</option>
        </select>
    </div>

    <div class="role-editor-footer">
        <a href="{{ route('courier.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary">Save Role</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.querySelector('[data-permission-search]');
        const moduleSelect = document.querySelector('[data-permission-module]');
        const countLabel = document.querySelector('[data-selected-permission-count]');
        const cards = Array.from(document.querySelectorAll('[data-permission-module-card]'));

        function visibleItems() {
            return Array.from(document.querySelectorAll('[data-permission-item]')).filter(item => item.offsetParent !== null);
        }

        function updateCount() {
            const checked = document.querySelectorAll('input[name="permission_ids[]"]:checked').length;
            if (countLabel) countLabel.textContent = checked + ' selected';
            cards.forEach(updateModuleState);
        }

        function updateModuleState(card) {
            const checkboxes = Array.from(card.querySelectorAll('input[name="permission_ids[]"]'));
            const moduleCheck = card.querySelector('[data-module-check]');
            const state = card.querySelector('[data-module-state]');
            const checked = checkboxes.filter(checkbox => checkbox.checked).length;

            if (moduleCheck) {
                moduleCheck.checked = checkboxes.length > 0 && checked === checkboxes.length;
                moduleCheck.indeterminate = checked > 0 && checked < checkboxes.length;
            }

            if (state) {
                state.textContent = checked === 0 ? 'Restricted' : (checked === checkboxes.length ? 'Checked' : checked + ' of ' + checkboxes.length);
            }
        }

        function filterPermissions() {
            const term = (search?.value || '').trim().toLowerCase();
            const moduleValue = moduleSelect?.value || '';

            cards.forEach(card => {
                const moduleMatches = !moduleValue || card.dataset.permissionModuleCard === moduleValue;
                let visibleCount = 0;

                card.querySelectorAll('[data-permission-item]').forEach(item => {
                    const textMatches = !term || item.dataset.permissionText.includes(term);
                    const show = moduleMatches && textMatches;
                    item.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                card.style.display = visibleCount > 0 ? '' : 'none';
            });
        }

        search?.addEventListener('input', filterPermissions);
        moduleSelect?.addEventListener('change', filterPermissions);
        document.querySelector('[data-select-visible-permissions]')?.addEventListener('click', function () {
            visibleItems().forEach(item => item.querySelector('input[type="checkbox"]').checked = true);
            updateCount();
        });
        document.querySelector('[data-clear-visible-permissions]')?.addEventListener('click', function () {
            visibleItems().forEach(item => item.querySelector('input[type="checkbox"]').checked = false);
            updateCount();
        });
        document.addEventListener('change', function (event) {
            if (event.target.matches('input[name="permission_ids[]"]')) updateCount();
            if (event.target.matches('[data-module-check]')) {
                const card = event.target.closest('[data-permission-module-card]');
                card?.querySelectorAll('input[name="permission_ids[]"]').forEach(checkbox => checkbox.checked = event.target.checked);
                updateCount();
            }
        });

        filterPermissions();
        updateCount();
    });
</script>

