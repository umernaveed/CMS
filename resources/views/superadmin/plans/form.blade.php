@php
    $features = old('feature_lines', implode("\n", data_get($plan, 'features.features', [])));
    $moduleOptions = $modules ?? \App\Models\SubscriptionPlan::MODULES;
    $selectedModules = old('modules', $plan ? $plan->enabledModules() : array_keys($moduleOptions));
@endphp
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', optional($plan)->name) }}" required></div>
    <div class="col-md-3"><label class="form-label">Monthly JMD</label><input type="number" step="0.01" name="monthly_price" class="form-control" value="{{ old('monthly_price', optional($plan)->monthly_price ?? 0) }}" required></div>
    <div class="col-md-3"><label class="form-label">Yearly JMD</label><input type="number" step="0.01" name="yearly_price" class="form-control" value="{{ old('yearly_price', optional($plan)->yearly_price) }}"></div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description', optional($plan)->description) }}</textarea></div>
    <div class="col-md-4">
        <label class="form-label">Billing Model</label>
        <select name="billing_model" class="form-select" required>
            <option value="subscription" @selected(old('billing_model', optional($plan)->billing_model ?? 'subscription') === 'subscription')>Subscription</option>
            <option value="per_package" @selected(old('billing_model', optional($plan)->billing_model) === 'per_package')>Per Package Shipped</option>
            <option value="hybrid" @selected(old('billing_model', optional($plan)->billing_model) === 'hybrid')>Subscription + Per Package</option>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Base Fee / Logged Package JMD</label><input type="number" step="0.01" min="0" name="per_package_base_fee_jmd" class="form-control" value="{{ old('per_package_base_fee_jmd', optional($plan)->per_package_base_fee_jmd ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Service Charge / Shipped Package JMD</label><input type="number" step="0.01" min="0" name="package_service_fee_jmd" class="form-control" value="{{ old('package_service_fee_jmd', optional($plan)->package_service_fee_jmd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">Max Users</label><input type="number" name="max_users" class="form-control" value="{{ old('max_users', optional($plan)->max_users) }}"></div>
    <div class="col-md-3"><label class="form-label">Max Customers</label><input type="number" name="max_customers" class="form-control" value="{{ old('max_customers', optional($plan)->max_customers) }}"></div>
    <div class="col-md-3"><label class="form-label">Packages / Month</label><input type="number" name="max_packages_month" class="form-control" value="{{ old('max_packages_month', optional($plan)->max_packages_month) }}"></div>
    <div class="col-md-3"><label class="form-label">Max Outlets</label><input type="number" name="max_outlets" class="form-control" value="{{ old('max_outlets', optional($plan)->max_outlets) }}"></div>
    <div class="col-md-3"><label class="form-label">Storage Days</label><input type="number" name="max_storage_days" class="form-control" value="{{ old('max_storage_days', optional($plan)->max_storage_days ?? 30) }}"></div>
    <div class="col-md-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', optional($plan)->sort_order ?? 0) }}"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($plan)->is_active ?? true))><label class="form-check-label">Active</label></div></div>
    <div class="col-12">
        <label class="form-label">Enabled Modules</label>
        <div class="row g-2">
            @foreach($moduleOptions as $moduleKey => $moduleLabel)
                <div class="col-lg-4 col-md-6">
                    <label class="border rounded p-3 w-100 h-100">
                        <input type="checkbox" name="modules[]" value="{{ $moduleKey }}" class="form-check-input me-2" @checked(in_array($moduleKey, $selectedModules, true))>
                        <span class="fw-semibold">{{ $moduleLabel }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        <div class="form-text">Unchecked modules are hidden from the courier menu and blocked by route permissions for that plan.</div>
    </div>
    <div class="col-12"><label class="form-label">Feature Notes</label><textarea name="feature_lines" class="form-control" rows="5">{{ $features }}</textarea></div>
    <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Save Plan</button><a href="{{ route('superadmin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</div>
