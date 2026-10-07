<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Company Name</label><input name="company_name" class="form-control" value="{{ old('company_name', optional($tenant)->company_name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Company Email</label><input type="email" name="email" class="form-control" value="{{ old('email', optional($tenant)->email) }}" required></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', optional($tenant)->phone) }}"></div>
    <div class="col-md-4"><label class="form-label">TRN / SSN</label><input name="tax_identifier" class="form-control" value="{{ old('tax_identifier', optional($tenant)->tax_identifier) }}"></div>
    <div class="col-md-4"><label class="form-label">Package Service Fee JMD</label><input type="number" step="0.01" min="0" name="package_service_fee_jmd" class="form-control" value="{{ old('package_service_fee_jmd', optional($tenant)->package_service_fee_jmd ?? 0) }}"></div>
    <div class="col-md-4">
        <label class="form-label">Billing Model</label>
        <select name="billing_model" class="form-select" required>
            <option value="subscription" @selected(old('billing_model', optional($tenant)->billing_model ?? 'subscription') === 'subscription')>Subscription</option>
            <option value="per_package" @selected(old('billing_model', optional($tenant)->billing_model) === 'per_package')>Per Package</option>
            <option value="hybrid" @selected(old('billing_model', optional($tenant)->billing_model) === 'hybrid')>Subscription + Per Package</option>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Expected Monthly Packages</label><input type="number" step="1" min="0" name="expected_monthly_packages" class="form-control" value="{{ old('expected_monthly_packages', optional($tenant)->expected_monthly_packages) }}"></div>
    <div class="col-md-4"><label class="form-label">Base Fee / Logged Package JMD</label><input type="number" step="0.01" min="0" name="per_package_base_fee_jmd" class="form-control" value="{{ old('per_package_base_fee_jmd', optional($tenant)->per_package_base_fee_jmd ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Subdomain</label><input name="subdomain" class="form-control" value="{{ old('subdomain', optional($tenant)->subdomain) }}"></div>
    <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['trial','active','suspended','expired','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', optional($tenant)->status ?? 'trial')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', optional($tenant)->city) }}"></div>
    <div class="col-md-4"><label class="form-label">Parish</label><input name="parish" class="form-control" value="{{ old('parish', optional($tenant)->parish) }}"></div>
    <div class="col-md-4"><label class="form-label">Plan</label><select name="plan_id" class="form-select" required data-plan-select>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-billing-model="{{ $plan->billing_model ?? 'subscription' }}" data-logged-package-fee="{{ $plan->per_package_base_fee_jmd ?? 0 }}" data-shipped-package-fee="{{ $plan->package_service_fee_jmd ?? 0 }}" @selected(old('plan_id', optional(optional($tenant)->subscription)->plan_id)==$plan->id)>{{ $plan->name }} - JMD {{ number_format($plan->monthly_price, 2) }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Billing</label><select name="billing_cycle" class="form-select"><option value="monthly" @selected(old('billing_cycle', optional(optional($tenant)->subscription)->billing_cycle)==='monthly')>Monthly</option><option value="yearly" @selected(old('billing_cycle', optional(optional($tenant)->subscription)->billing_cycle)==='yearly')>Yearly</option></select></div>
    <div class="col-md-8"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', optional($tenant)->address) }}"></div>
    @if(! $tenant)
        <div class="col-md-6"><label class="form-label">Admin Name</label><input name="admin_name" class="form-control" value="{{ old('admin_name') }}" required></div>
        <div class="col-md-6"><label class="form-label">Admin Password</label><input type="password" name="admin_password" class="form-control" required></div>
    @endif
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">Save Courier</button>
        <a href="{{ route('superadmin.tenants') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const plan = document.querySelector('[data-plan-select]');
    const billingModel = document.querySelector('[name="billing_model"]');
    const loggedFee = document.querySelector('[name="per_package_base_fee_jmd"]');
    const shippedFee = document.querySelector('[name="package_service_fee_jmd"]');
    if (!plan || !billingModel || !loggedFee || !shippedFee) return;
    plan.addEventListener('change', function () {
        const selected = plan.options[plan.selectedIndex];
        billingModel.value = selected.dataset.billingModel || 'subscription';
        loggedFee.value = selected.dataset.loggedPackageFee || 0;
        shippedFee.value = selected.dataset.shippedPackageFee || 0;
    });
});
</script>
