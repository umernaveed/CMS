<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Invoice Number</label><input name="invoice_number" class="form-control" value="{{ old('invoice_number', optional($invoice)->invoice_number ?? 'INV-'.now()->format('Ymd-His')) }}" required></div>
    @php
        $selectedCustomerId = old('customer_id', optional($invoice)->customer_id ?? ($selectedCustomerId ?? null));
        $originalPackageIds = isset($invoice) && $invoice ? $invoice->items->pluck('package_id')->filter()->map(fn ($id) => (int) $id)->values()->all() : [];
        $selectedPackageIds = collect($selectedPackageIds ?? $originalPackageIds)->merge(old('package_ids', []))->merge($originalPackageIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $selectedPricingTierId = old('pricing_tier_id', $selectedPricingTierId ?? optional(optional($invoice)->customer)->pricing_tier_id);
        $invoiceItemsByPackage = isset($invoice) && $invoice ? $invoice->items->keyBy('package_id') : collect();
        $invoiceItemServiceSubtotal = $invoiceItemsByPackage->sum('service_fee_jmd');
        $invoiceItemCustomsSubtotal = $invoiceItemsByPackage->sum('customs_fee_jmd');
        $allowMultipleCustomers = empty($invoice);
    @endphp
    <input type="hidden" data-invoice-multi-customer value="{{ $allowMultipleCustomers ? '1' : '0' }}">
    @foreach($originalPackageIds as $originalPackageId)
        <input type="hidden" name="original_package_ids[]" value="{{ $originalPackageId }}">
    @endforeach
    <input form="courierInvoiceForm" type="hidden" name="discount_source" value="{{ old('discount_source', 'value') }}" data-discount-source>
    <input form="courierInvoiceForm" type="hidden" name="discount_type" value="{{ old('discount_type', optional($invoice)->discount_type ?? 'fixed') }}" data-discount-type-priority>
    <input form="courierInvoiceForm" type="hidden" name="discount_value" value="{{ old('discount_value', optional($invoice)->discount_value ?? optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-value-priority>
    <input form="courierInvoiceForm" type="hidden" name="discount_amount_jmd" value="{{ old('discount_amount_jmd', optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-amount-priority>
    <input form="courierInvoiceForm" type="hidden" name="discount" value="{{ old('discount', optional($invoice)->discount_value ?? optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-value-fallback>
    <input form="courierInvoiceForm" type="hidden" name="discount_amount" value="{{ old('discount_amount', optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-amount-fallback>
    <div data-selected-package-mirror></div>
    <div class="col-md-4"><label class="form-label">Customer</label><input type="search" class="form-control mb-2" data-search-select="customer_id" placeholder="Search mailbox, name, email, or phone"><select name="customer_id" class="form-select" @if(! $allowMultipleCustomers) required @endif><option value="">{{ $allowMultipleCustomers ? 'All selected customers' : 'Select customer' }}</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-search-text="{{ strtolower(trim($customer->mailbox.' '.optional($customer->user)->full_name.' '.optional($customer->user)->email.' '.optional($customer->user)->phone)) }}" @selected($selectedCustomerId == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional(optional($invoice)->due_date)->format('Y-m-d')) }}"></div>
    <div class="col-md-4">
        <label class="form-label">Rate Group</label>
        <select name="pricing_tier_id" class="form-select">
            <option value="">Customer/default rate group</option>
            @foreach(($pricingTiers ?? collect()) as $tier)
                <option value="{{ $tier->id }}" @selected((string) $selectedPricingTierId === (string) $tier->id)>{{ $tier->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Selected packages will be priced with this rate group when the invoice is saved.</div>
    </div>
    @if(!empty($preselectedPackage))
        <div class="col-12">
            <div class="alert alert-info mb-0">
                Creating invoice from package <strong>{{ $preselectedPackage->tracking_number }}</strong>. Customer, mailbox, and package total have been preselected.
            </div>
        </div>
    @endif
    <div class="col-12">
        <div class="panel p-3 shadow-none">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">Packages</h2>
                    <p class="text-muted mb-0">Select customer packages to create invoice items. Totals below will use selected package prices.</p>
                </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input type="search" class="form-control" data-invoice-package-search placeholder="Tracking, customer, mailbox, merchant"></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Branch</label><select class="form-select" data-invoice-package-branch><option value="">All branches</option>@foreach(($outlets ?? collect()) as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach</select></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Rate Group</label><select class="form-select" data-invoice-package-rate-group><option value="">All rate groups</option>@foreach(($pricingTiers ?? collect()) as $tier)<option value="{{ $tier->id }}">{{ $tier->name }}</option>@endforeach</select></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Shipping Mode</label><select class="form-select" data-invoice-package-freight><option value="">All modes</option>@foreach(($shippingModes ?? collect()) as $mode)<option value="{{ $mode->code }}">{{ $mode->name }}</option>@endforeach</select></div>
                <div class="col-lg-3 col-md-6"><label class="form-label">Customer</label><input type="search" class="form-control mb-2" data-search-select="invoice-package-customer" placeholder="Search mailbox, name, email, or phone"><select class="form-select" data-invoice-package-customer><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-search-text="{{ strtolower(trim($customer->mailbox.' '.optional($customer->user)->full_name.' '.optional($customer->user)->email.' '.optional($customer->user)->phone)) }}">{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>@endforeach</select></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Date From</label><input type="date" class="form-control" data-invoice-package-date-from></div>
                <div class="col-lg-2 col-md-6"><label class="form-label">Date To</label><input type="date" class="form-control" data-invoice-package-date-to></div>
                <div class="col-lg-8 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" data-apply-invoice-package-filters>Apply Filters</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-select-invoice-packages>Select All Visible</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-invoice-packages>Unselect All</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" data-reset-invoice-package-filters>Reset Filters</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th></th><th>Tracking</th><th>Customer</th><th>Measure</th><th>Package Charge</th><th>Storage</th><th>Processing</th><th>Unknown</th><th>Service</th><th>Customs</th><th class="text-end">Line Total</th></tr></thead>
                    <tbody>
                    @forelse($packages as $packageRow)
                        @php
                            $existingItem = $invoiceItemsByPackage->get($packageRow->id);
                            $packageCharge = old("package_charges.{$packageRow->id}.price_jmd", optional($existingItem)->package_price_jmd ?? $packageRow->price_jmd);
                            $storageCharge = old("package_charges.{$packageRow->id}.storage_fee_jmd", optional($existingItem)->storage_fee_jmd ?? $packageRow->storage_fee);
                            $processingCharge = old("package_charges.{$packageRow->id}.processing_fee_jmd", optional($existingItem)->processing_fee_jmd ?? $packageRow->processing_fee_jmd);
                            $unknownCharge = old("package_charges.{$packageRow->id}.unknown_fee_jmd", optional($existingItem)->unknown_fee_jmd ?? $packageRow->unknown_fee_jmd);
                            $serviceCharge = old("package_charges.{$packageRow->id}.service_fee_jmd", optional($existingItem)->service_fee_jmd ?? 0);
                            $customsCharge = old("package_charges.{$packageRow->id}.customs_fee_jmd", optional($existingItem)->customs_fee_jmd ?? 0);
                            $lineTotal = (float) $packageCharge + (float) $storageCharge + (float) $processingCharge + (float) $unknownCharge + (float) $serviceCharge + (float) $customsCharge;
                            $rateMatched = $packageRow->getAttribute('invoice_rate_matched');
                        @endphp
                        <tr data-package-row data-customer-id="{{ $packageRow->customer_id }}" data-branch="{{ $packageRow->outlet_id }}" data-rate-group="{{ optional($packageRow->customer)->pricing_tier_id }}" data-freight="{{ $packageRow->freight_type }}" data-date="{{ optional($packageRow->date_received)->format('Y-m-d') }}" data-search="{{ strtolower($packageRow->tracking_number.' '.optional(optional($packageRow->customer)->user)->full_name.' '.($packageRow->mailbox ?: '').' '.($packageRow->merchant ?: '').' '.($packageRow->courier ?: '')) }}">
                            <td><input class="form-check-input invoice-package-check" type="checkbox" name="package_ids[]" value="{{ $packageRow->id }}" data-customer-id="{{ $packageRow->customer_id }}" @checked(in_array((int) $packageRow->id, $selectedPackageIds, true))></td>
                            <td><strong>{{ $packageRow->tracking_number }}</strong><br><small class="text-muted">{{ $packageRow->merchant ?: $packageRow->courier }}</small></td>
                            <td>{{ optional(optional($packageRow->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $packageRow->mailbox }} / {{ optional($packageRow->outlet)->name ?: 'Main' }} / {{ optional(optional($packageRow->customer)->pricingTier)->name ?: 'Default' }}</small></td>
                            <td>{{ $packageRow->measureDisplay() }}<br><small class="text-muted">{{ $packageRow->labelType() }}</small></td>
                            <td>
                                <input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][price_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $packageCharge, 2, '.', '') }}">
                                @if($rateMatched === false)
                                    <small class="text-danger">No matching active rate found.</small>
                                @elseif($rateMatched)
                                    <small class="text-success">Rate Management</small>
                                @endif
                            </td>
                            <td><input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][storage_fee_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $storageCharge, 2, '.', '') }}"></td>
                            <td><input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][processing_fee_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $processingCharge, 2, '.', '') }}"></td>
                            <td><input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][unknown_fee_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $unknownCharge, 2, '.', '') }}"></td>
                            <td><input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][service_fee_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $serviceCharge, 2, '.', '') }}"></td>
                            <td><input type="number" step="0.01" min="0" name="package_charges[{{ $packageRow->id }}][customs_fee_jmd]" class="form-control form-control-sm invoice-line-charge" value="{{ number_format((float) $customsCharge, 2, '.', '') }}"></td>
                            <td class="text-end">JMD <span data-line-total>{{ number_format($lineTotal, 2) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">No uninvoiced packages available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-3"><label class="form-label">Subtotal JMD</label><input type="number" step="0.01" name="subtotal_jmd" class="form-control" value="{{ old('subtotal_jmd', optional($invoice)->subtotal_jmd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">Additional Service Fee</label><input type="number" step="0.01" name="service_fee_jmd" class="form-control" value="{{ old('service_fee_jmd', max((float) (optional($invoice)->service_fee_jmd ?? 0) - (float) $invoiceItemServiceSubtotal, 0)) }}"></div>
    <div class="col-md-3"><label class="form-label">Additional Customs Fee</label><input type="number" step="0.01" name="customs_fee_jmd" class="form-control" value="{{ old('customs_fee_jmd', max((float) (optional($invoice)->customs_fee_jmd ?? 0) - (float) $invoiceItemCustomsSubtotal, 0)) }}"></div>
    <div class="col-md-3"><label class="form-label">Storage Fee</label><input type="number" step="0.01" name="storage_fee_jmd" class="form-control" value="{{ old('storage_fee_jmd', optional($invoice)->storage_fee_jmd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">GCT</label><input type="number" step="0.01" name="gct_amount_jmd" class="form-control" value="{{ old('gct_amount_jmd', optional($invoice)->gct_amount_jmd ?? 0) }}"></div>
    <div class="col-md-3"><label class="form-label">Discount Type</label><select class="form-select" data-discount-type-input><option value="fixed" @selected(old('discount_type', optional($invoice)->discount_type ?? 'fixed') === 'fixed')>Fixed amount</option><option value="percent" @selected(old('discount_type', optional($invoice)->discount_type ?? 'fixed') === 'percent')>Percentage</option></select></div>
    <div class="col-md-3"><label class="form-label">Discount Value</label><input form="courierInvoiceForm" type="number" step="0.01" class="form-control" value="{{ old('discount_value', optional($invoice)->discount_value ?? optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-value-input><div class="form-text">Fixed: amount off. Percentage: percent off.</div></div>
    <div class="col-md-3"><label class="form-label">Discount Amount</label><input form="courierInvoiceForm" type="number" step="0.01" class="form-control" value="{{ old('discount_amount_jmd', optional($invoice)->discount_amount_jmd ?? 0) }}" data-discount-amount-input></div>
    <div class="col-md-3"><label class="form-label">Payment Status</label><select name="payment_status" class="form-select">@foreach(['unpaid','partial','paid','cancelled'] as $status)<option value="{{ $status }}" @selected(old('payment_status', optional($invoice)->payment_status ?? 'unpaid')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Invoice Status</label><select name="invoice_status" class="form-select">@foreach(['draft','issued','cancelled'] as $status)<option value="{{ $status }}" @selected(old('invoice_status', optional($invoice)->invoice_status ?? 'draft')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check pb-2">
            <input type="hidden" name="require_handoff_verification" value="0">
            <input class="form-check-input" type="checkbox" name="require_handoff_verification" value="1" id="require_handoff_verification" @checked(old('require_handoff_verification', optional($invoice)->require_handoff_verification ?? true))>
            <label class="form-check-label" for="require_handoff_verification">Require handoff scan</label>
        </div>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ old('notes', optional($invoice)->notes) }}</textarea></div>
    <div class="col-12 d-flex gap-2 pt-3"><button class="btn btn-primary">Save Invoice</button><a href="{{ route('courier.invoices.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const allowMultipleCustomers = document.querySelector('[data-invoice-multi-customer]')?.value === '1';
    const checks = document.querySelectorAll('.invoice-package-check');
    const rows = document.querySelectorAll('[data-package-row]');
    const selectedPackageMirror = document.querySelector('[data-selected-package-mirror]');
    const invoiceForm = document.getElementById('courierInvoiceForm') || (selectedPackageMirror ? selectedPackageMirror.closest('form') : null);
    const subtotal = document.querySelector('[name="subtotal_jmd"]');
    const customer = document.querySelector('[name="customer_id"]');
    const packageSearch = document.querySelector('[data-invoice-package-search]');
    const packageBranch = document.querySelector('[data-invoice-package-branch]');
    const packageRateGroup = document.querySelector('[data-invoice-package-rate-group]');
    const packageFreight = document.querySelector('[data-invoice-package-freight]');
    const packageCustomer = document.querySelector('[data-invoice-package-customer]');
    const packageDateFrom = document.querySelector('[data-invoice-package-date-from]');
    const packageDateTo = document.querySelector('[data-invoice-package-date-to]');
    const storage = document.querySelector('[name="storage_fee_jmd"]');
    const discountType = document.querySelector('[data-discount-type-input]');
    const discountValue = document.querySelector('[data-discount-value-input]');
    const discountAmount = document.querySelector('[data-discount-amount-input]');
    const discountSource = document.querySelector('[data-discount-source]');
    const discountTypePriority = document.querySelector('[data-discount-type-priority]');
    const discountValueFallback = document.querySelector('[data-discount-value-fallback]');
    const discountAmountFallback = document.querySelector('[data-discount-amount-fallback]');
    const discountValuePriority = document.querySelector('[data-discount-value-priority]');
    const discountAmountPriority = document.querySelector('[data-discount-amount-priority]');
    const totalFields = ['subtotal_jmd', 'service_fee_jmd', 'customs_fee_jmd', 'storage_fee_jmd', 'gct_amount_jmd'].map(name => document.querySelector('[name="' + name + '"]'));
    const searchableSelects = document.querySelectorAll('[data-search-select]');

    searchableSelects.forEach(input => {
        const key = input.dataset.searchSelect;
        const select = key === 'customer_id'
            ? document.querySelector('[name="customer_id"]')
            : document.querySelector('[data-' + key + ']');
        if (!select) return;
        input.addEventListener('input', function () {
            const term = input.value.trim().toLowerCase();
            Array.from(select.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                option.hidden = term !== '' && !(option.dataset.searchText || option.text).toLowerCase().includes(term);
            });
        });
    });

    function syncPackageVisibility() {
        const customerId = customer ? customer.value : '';
        rows.forEach(row => {
            const search = (packageSearch?.value || '').trim().toLowerCase();
            const branch = packageBranch?.value || '';
            const rateGroup = packageRateGroup?.value || '';
            const freight = packageFreight?.value || '';
            const pickerCustomer = packageCustomer?.value || '';
            const dateFrom = packageDateFrom?.value || '';
            const dateTo = packageDateTo?.value || '';
            const rowDate = row.dataset.date || '';
            const match = (!customerId || row.dataset.customerId === customerId)
                && (!pickerCustomer || row.dataset.customerId === pickerCustomer)
                && (!branch || row.dataset.branch === branch)
                && (!rateGroup || row.dataset.rateGroup === rateGroup)
                && (!freight || row.dataset.freight === freight)
                && (!search || (row.dataset.search || '').includes(search))
                && (!dateFrom || (rowDate && rowDate >= dateFrom))
                && (!dateTo || (rowDate && rowDate <= dateTo));
            row.style.display = match ? '' : 'none';
            const checkbox = row.querySelector('.invoice-package-check');
            if (checkbox) {
                checkbox.disabled = false;
            }
        });
    }

    function syncSelectedPackageMirror() {
        if (!selectedPackageMirror) return;
        selectedPackageMirror.innerHTML = '';
        Array.from(checks).filter(check => check.checked).forEach(check => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_package_ids[]';
            input.value = check.value;
            selectedPackageMirror.appendChild(input);
        });
    }

    function syncPackageSubtotal() {
        syncPackageVisibility();
        const selectedChecks = Array.from(checks).filter(check => check.checked);
        syncSelectedPackageMirror();
        let packageSubtotal = 0;
        let storageSubtotal = 0;

        rows.forEach(row => {
            const selected = row.querySelector('.invoice-package-check')?.checked;
            const fields = Array.from(row.querySelectorAll('.invoice-line-charge'));
            const values = fields.map(field => Number(field.value || 0));
            const lineTotal = values.reduce((sum, value) => sum + value, 0);
            const lineTotalEl = row.querySelector('[data-line-total]');
            if (lineTotalEl) lineTotalEl.textContent = lineTotal.toFixed(2);
            if (selected) {
                packageSubtotal += values[0] + values[2] + values[3];
                storageSubtotal += values[1];
            }
        });

        if (selectedChecks.length > 0 && subtotal) {
            subtotal.value = packageSubtotal.toFixed(2);
        }
        if (selectedChecks.length > 0 && storage) {
            storage.value = storageSubtotal.toFixed(2);
        }

        const currentDiscountBase = function () {
            return Number(subtotal?.value || 0)
                + Number(document.querySelector('[name="service_fee_jmd"]')?.value || 0)
                + Number(document.querySelector('[name="customs_fee_jmd"]')?.value || 0)
                + Number(storage?.value || 0)
                + Number(document.querySelector('[name="gct_amount_jmd"]')?.value || 0);
        };

        if (discountType && discountValue && discountAmount) {
            const base = currentDiscountBase();
            if (discountTypePriority) discountTypePriority.value = discountType.value || 'fixed';
            if (discountSource?.value === 'amount') {
                const amount = Math.min(Number(discountAmount.value || 0), base);
                discountAmount.value = amount.toFixed(2);
                discountValue.value = (discountType.value === 'percent' ? (base > 0 ? amount * 100 / base : 0) : amount).toFixed(2);
            } else {
                const value = Number(discountValue.value || 0);
                discountAmount.value = (discountType.value === 'percent' ? Math.min(base * value / 100, base) : Math.min(value, base)).toFixed(2);
            }
            if (discountValueFallback) discountValueFallback.value = discountValue.value || 0;
            if (discountAmountFallback) discountAmountFallback.value = discountAmount.value || 0;
            if (discountValuePriority) discountValuePriority.value = discountValue.value || 0;
            if (discountAmountPriority) discountAmountPriority.value = discountAmount.value || 0;
        }

        if (!allowMultipleCustomers && customer && selectedChecks.length === 1 && selectedChecks[0].dataset.customerId) {
            customer.value = selectedChecks[0].dataset.customerId;
            syncPackageVisibility();
        }
    }

    document.querySelector('[data-select-invoice-packages]')?.addEventListener('click', function () {
        syncPackageVisibility();
        const visibleChecks = Array.from(checks).filter(check => !check.disabled && check.closest('[data-package-row]')?.style.display !== 'none');
        if (!allowMultipleCustomers && customer && !customer.value && visibleChecks.length > 0) {
            customer.value = visibleChecks[0].dataset.customerId || '';
            syncPackageVisibility();
        }
        Array.from(checks).forEach(check => {
            if (!check.disabled && check.closest('[data-package-row]')?.style.display !== 'none') check.checked = true;
        });
        syncPackageSubtotal();
    });

    document.querySelector('[data-clear-invoice-packages]')?.addEventListener('click', function () {
        Array.from(checks).forEach(check => check.checked = false);
        syncPackageSubtotal();
    });

    if (customer) {
        customer.addEventListener('change', syncPackageSubtotal);
    }
    [packageSearch, packageBranch, packageRateGroup, packageFreight, packageCustomer, packageDateFrom, packageDateTo].forEach(field => {
        if (!field) return;
        field.addEventListener(field.tagName === 'INPUT' && field.type === 'search' ? 'input' : 'change', syncPackageSubtotal);
    });
    if (packageCustomer && customer && !allowMultipleCustomers) {
        packageCustomer.addEventListener('change', function () {
            if (packageCustomer.value) customer.value = packageCustomer.value;
            syncPackageSubtotal();
        });
    }
    document.querySelector('[data-apply-invoice-package-filters]')?.addEventListener('click', syncPackageSubtotal);
    document.querySelector('[data-reset-invoice-package-filters]')?.addEventListener('click', function () {
        [packageSearch, packageBranch, packageRateGroup, packageFreight, packageCustomer, packageDateFrom, packageDateTo].forEach(field => {
            if (field) field.value = '';
        });
        syncPackageSubtotal();
    });
    checks.forEach(check => check.addEventListener('change', syncPackageSubtotal));
    document.querySelectorAll('.invoice-line-charge').forEach(field => field.addEventListener('input', syncPackageSubtotal));
    if (discountType) discountType.addEventListener('change', syncPackageSubtotal);
    if (discountValue) {
        discountValue.addEventListener('input', function () {
            if (discountSource) discountSource.value = 'value';
            syncPackageSubtotal();
        });
    }
    if (discountAmount) {
        discountAmount.addEventListener('input', function () {
            if (discountSource) discountSource.value = 'amount';
            const base = Number(subtotal?.value || 0)
                + Number(document.querySelector('[name="service_fee_jmd"]')?.value || 0)
                + Number(document.querySelector('[name="customs_fee_jmd"]')?.value || 0)
                + Number(storage?.value || 0)
                + Number(document.querySelector('[name="gct_amount_jmd"]')?.value || 0);
            const amount = Number(discountAmount.value || 0);
            if (discountValue) {
                discountValue.value = discountType && discountType.value === 'percent'
                    ? (base > 0 ? Math.min(amount, base) * 100 / base : 0).toFixed(2)
                    : Math.min(amount, base).toFixed(2);
            }
            syncPackageSubtotal();
        });
    }
    totalFields.forEach(field => field && field.addEventListener('input', syncPackageSubtotal));
    function appendFinalDiscountFields() {
        if (!invoiceForm || !discountValue || !discountAmount) return;
        invoiceForm.querySelectorAll('[data-final-discount-field]').forEach(field => field.remove());
        [
            ['discount_value', discountValue.value || 0],
            ['discount_amount_jmd', discountAmount.value || 0],
            ['discount', discountValue.value || 0],
            ['discount_amount', discountAmount.value || 0],
            ['discount_type', discountType?.value || 'fixed'],
            ['discount_source', discountSource?.value || 'value']
        ].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.setAttribute('data-final-discount-field', '1');
            invoiceForm.appendChild(input);
        });
    }

    if (invoiceForm) invoiceForm.addEventListener('submit', function () {
        syncPackageSubtotal();
        syncSelectedPackageMirror();
        appendFinalDiscountFields();
    });
    syncPackageSubtotal();
});
</script>


