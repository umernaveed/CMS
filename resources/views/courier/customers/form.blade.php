@php($user = optional($customer)->user)
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="{{ old('full_name', optional($user)->full_name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', optional($user)->email) }}" required></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', optional($user)->phone) }}"></div>
    <div class="col-md-4"><label class="form-label">Mailbox</label><input name="mailbox" class="form-control" value="{{ old('mailbox', optional($customer)->mailbox) }}" placeholder="Auto-generated if blank"></div>
    <div class="col-md-4"><label class="form-label">Outlet</label><select name="outlet_id" class="form-select"><option value="">No outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected(old('outlet_id', optional($customer)->outlet_id)==$outlet->id)>{{ $outlet->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">TRN</label><input name="trn" class="form-control" value="{{ old('trn', optional($user)->trn) }}"></div>
    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', optional($user)->city) }}"></div>
    <div class="col-md-4"><label class="form-label">Parish</label><input name="parish" class="form-control" value="{{ old('parish', optional($user)->parish) }}"></div>
    <div class="col-md-4"><label class="form-label">Marketing Group</label><input name="email_group" class="form-control" value="{{ old('email_group', optional($customer)->email_group) }}" placeholder="e.g. VIP, Promotions"></div>
    <div class="col-md-4"><label class="form-label">Account Type</label><select name="account_type" class="form-select" data-account-type><option value="personal" @selected(old('account_type', optional($customer)->account_type ?? 'personal') === 'personal')>Personal</option><option value="business" @selected(old('account_type', optional($customer)->account_type) === 'business')>Business</option></select></div>
    <div class="col-md-4"><label class="form-label">Pricing Tier</label><select name="pricing_tier_id" class="form-select"><option value="">Default tier</option>@foreach(($pricingTiers ?? collect()) as $tier)<option value="{{ $tier->id }}" @selected(old('pricing_tier_id', optional($customer)->pricing_tier_id) == $tier->id)>{{ $tier->name }}</option>@endforeach</select></div>
    <div class="col-md-4" data-business-field><label class="form-label">Company Name</label><input name="company_name" class="form-control" value="{{ old('company_name', optional($customer)->company_name) }}"></div>
    <div class="col-md-4" data-business-field><label class="form-label">Company Registration</label><input name="company_registration" class="form-control" value="{{ old('company_registration', optional($customer)->company_registration) }}"></div>
    <div class="col-md-4" data-business-field><label class="form-label">Representative Role</label><input name="representative_role" class="form-control" value="{{ old('representative_role', optional($customer)->representative_role) }}"></div>
    <div class="col-12"><label class="form-label">Address</label><input name="address_line1" class="form-control" value="{{ old('address_line1', optional($user)->address_line1) }}"></div>
    <div class="col-md-6"><label class="form-label">Password {{ $customer ? '(leave blank to keep)' : '' }}</label><input type="password" name="password" class="form-control" @if(! $customer) required @endif></div>
    <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($customer)->is_active ?? true))><label class="form-check-label">Active customer</label></div></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', optional($customer)->notes) }}</textarea></div>
    <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Save Customer</button><a href="{{ route('courier.customers.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accountType = document.querySelector('[data-account-type]');
    const businessFields = document.querySelectorAll('[data-business-field]');
    function syncBusinessFields() {
        const show = accountType && accountType.value === 'business';
        businessFields.forEach(field => field.style.display = show ? '' : 'none');
    }
    accountType && accountType.addEventListener('change', syncBusinessFields);
    syncBusinessFields();
});
</script>
