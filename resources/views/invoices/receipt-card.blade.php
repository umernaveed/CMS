@php
    $tenant = $invoice->tenant;
    $outlet = $invoice->outlet;
    $customer = $invoice->customer;
    $customerUser = optional($customer)->user;
    $customerName = optional($customerUser)->full_name ?: optional($customerUser)->email ?: 'Customer';
    $tenantName = optional($tenant)->company_name ?: ($platformName ?? config('app.name', 'Courier SaaS'));
    $logoUrl = function (?string $path) {
        if (! $path) return null;
        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://']) ? $path : asset(ltrim($path, '/'));
    };
    $invoiceLogo = optional($tenant)->id ? \App\Models\TenantSetting::getValue($tenant->id, 'invoice_logo') : null;
    $tenantLogo = $invoiceLogo ? $logoUrl($invoiceLogo) : (optional($tenant)->logo ? $logoUrl($tenant->logo) : ($platformLogo ?? null));
    $isPaid = $invoice->payment_status === 'paid';
    $statusClass = $invoice->payment_status === 'cancelled' ? 'cancelled' : ($isPaid ? 'paid' : 'due');
    $items = $invoice->items;
    $approvedPayments = $invoice->payments->where('status', 'approved');
    $approvedPayment = $invoice->latestApprovedPayment ?: $approvedPayments->sortByDesc('paid_at')->first();
    $amountPaid = (float) $approvedPayments->sum('amount');
    $balanceDue = max((float) $invoice->total_jmd - $amountPaid, 0);
    $packageSubtotal = (float) $items->sum('package_price_jmd');
    $processingSubtotal = (float) $items->sum('processing_fee_jmd');
    $itemStorageSubtotal = (float) $items->sum('storage_fee_jmd');
    $totalBillableWeight = (float) $items->sum(fn ($item) => (float) ($item->billable_weight ?: $item->weight));
    $allSea = $items->isNotEmpty() && $items->every(fn ($item) => ($item->freight_type ?? optional($item->package)->freight_type) === 'sea');
    $summaryUnit = $allSea ? ($items->pluck('measure_unit')->filter()->unique()->count() === 1 ? ($items->pluck('measure_unit')->filter()->first() ?: 'cu.ft') : 'mixed') : 'lb';
    $manifestNumbers = $items->map(fn ($item) => optional(optional($item->package)->manifest)->manifest_number)->filter()->unique()->values();
    $companyAddress = collect([
        optional($outlet)->address ?: optional($tenant)->address,
        optional($outlet)->city ?: optional($tenant)->city,
        optional($outlet)->parish ?: optional($tenant)->parish,
    ])->filter()->implode(', ');
    $companyEmail = optional($outlet)->email ?: optional($tenant)->email;
    $companyPhone = optional($outlet)->phone ?: optional($tenant)->phone;
    $customerAddress = collect([
        optional($customerUser)->address_line1,
        optional($customerUser)->address_line2,
        optional($customerUser)->city,
        optional($customerUser)->parish,
        optional($customerUser)->country,
    ])->filter()->implode(', ');
    $bankingDetails = $invoice->tenant_id ? \App\Models\TenantSetting::getValue($invoice->tenant_id, 'banking_details', '') : '';
@endphp

<section class="receipt-sheet">
    <div class="receipt-inner">
        <header class="receipt-header">
            <div class="receipt-header-column">
                <div class="receipt-brand">
                    @if($tenantLogo)
                        <div class="receipt-logo"><img src="{{ $tenantLogo }}" alt="{{ $tenantName }} logo"></div>
                    @endif
                    <div class="receipt-company">
                        <h2>{{ $tenantName }}</h2>
                        <div class="receipt-small receipt-muted">
                            @if($companyAddress){{ $companyAddress }}<br>@endif
                            @if($companyEmail){{ $companyEmail }}<br>@endif
                            @if($companyPhone){{ $companyPhone }}@endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="receipt-header-column">
                <div class="receipt-kicker">Billed To</div>
                <div class="receipt-billed-name">{{ $customerName }}</div>
                <div class="receipt-small">
                    @if($customerAddress){{ $customerAddress }}<br>@endif
                    @if(optional($customerUser)->email){{ optional($customerUser)->email }}<br>@endif
                    @if(optional($customerUser)->phone){{ optional($customerUser)->phone }}<br>@endif
                    @if($invoice->mailbox || optional($customer)->mailbox)Mailbox: {{ $invoice->mailbox ?: optional($customer)->mailbox }}@endif
                </div>
            </div>

            <div class="receipt-header-column">
                <div class="receipt-kicker">Invoice Details</div>
                <div class="receipt-invoice-meta">
                    <div>
                        <div class="receipt-meta-line"><span>Invoice No:</span><strong>{{ $invoice->invoice_number }}</strong></div>
                        <div class="receipt-meta-line"><span>Date:</span><strong>{{ optional($invoice->created_at)->format('M d, Y') }}</strong></div>
                        <div class="receipt-meta-line"><span>Due Date:</span><strong>{{ optional($invoice->due_date)->format('M d, Y') ?? '-' }}</strong></div>
                        <div class="receipt-meta-line"><span>AWB / BL:</span><strong>{{ $manifestNumbers->isNotEmpty() ? $manifestNumbers->implode(', ') : '-' }}</strong></div>
                        @if(optional($outlet)->name)
                            <div class="receipt-meta-line"><span>Outlet:</span><strong>{{ $outlet->name }}</strong></div>
                        @endif
                    </div>
                    <div>
                        <div class="receipt-barcode">{!! \App\Support\Code128Barcode::svg($invoice->invoice_number, 42) !!}</div>
                        <span class="receipt-status {{ $statusClass }}">{{ ucfirst($invoice->payment_status) }}</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="receipt-body">
            <section class="receipt-section">
                <h3 class="receipt-section-title">Shipment Details</h3>
                <div class="receipt-table-wrap">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Packages</th>
                                <th>{{ $allSea ? 'Billable Volume' : 'Billable Weight' }}</th>
                                <th>{{ $allSea ? 'Sea Charges' : 'Weight Charges' }}</th>
                                <th>Service / Processing</th>
                                <th class="text-end">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $items->count() }}</td>
                                <td>{{ $summaryUnit === 'mixed' ? 'See package lines' : number_format($totalBillableWeight, 2).' '.$summaryUnit }}</td>
                                <td>JMD {{ number_format($packageSubtotal, 2) }}</td>
                                <td>JMD {{ number_format((float) $invoice->service_fee_jmd + $processingSubtotal, 2) }}</td>
                                <td class="text-end"><strong>JMD {{ number_format($invoice->total_jmd, 2) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="receipt-section">
                <h3 class="receipt-section-title">Package Details</h3>
                <div class="receipt-table-wrap">
                    <table class="receipt-table receipt-package-table">
                        <thead>
                            <tr>
                                <th>HAWB / Tracking #</th>
                                <th>Description</th>
                                <th>Billable Weight / Volume</th>
                                <th>Rate</th>
                                <th>Weight Charge</th>
                                <th>Other Charges</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            @php
                                $otherCharges = (float) $item->storage_fee_jmd
                                    + (float) $item->processing_fee_jmd
                                    + (float) $item->service_fee_jmd
                                    + (float) $item->customs_fee_jmd;
                            @endphp
                                <tr>
                                    <td><strong>{{ $item->tracking_number ?: optional($item->package)->tracking_number ?: '-' }}</strong></td>
                                    <td class="receipt-description">{{ $item->description ?: '-' }}</td>
                                    <td>
                                        @php
                                            $measure = (float) ($item->billable_weight ?: $item->weight);
                                            $unit = $item->measure_unit ?: (($item->freight_type ?? optional($item->package)->freight_type) === 'sea' ? 'cu.ft' : 'lb');
                                            $conversion = $unit === 'CBM'
                                                ? number_format($measure * \App\Models\WeightRate::CBM_TO_CUFT, 2).' cu.ft'
                                                : ($unit === 'cu.ft' ? number_format($measure / \App\Models\WeightRate::CBM_TO_CUFT, 3).' CBM' : number_format($measure * 0.45359237, 2).' kg');
                                        @endphp
                                        {{ number_format($measure, 2) }} {{ $unit }}
                                        <div class="receipt-charge-breakdown">{{ $conversion }}@if($item->rate_region) · {{ $item->rate_region }}@endif</div>
                                    </td>
                                    <td>JMD {{ number_format($item->rate_jmd, 2) }} range</td>
                                    <td>JMD {{ number_format($item->package_price_jmd, 2) }}</td>
                                    <td>
                                        JMD {{ number_format($otherCharges, 2) }}
                                        @if($otherCharges > 0)
                                            <div class="receipt-charge-breakdown">
                                                Storage {{ number_format($item->storage_fee_jmd, 2) }};
                                                Processing {{ number_format($item->processing_fee_jmd, 2) }};
                                                Service {{ number_format($item->service_fee_jmd, 2) }};
                                                Customs {{ number_format($item->customs_fee_jmd, 2) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end"><strong>JMD {{ number_format($item->total_jmd, 2) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="receipt-empty">No package items recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="receipt-bottom-grid">
                <div>
                    <div class="receipt-note-block">
                        <h3>Payment Instructions</h3>
                        <p>{{ $bankingDetails ?: 'Please contact the courier office for payment instructions.' }}</p>
                    </div>
                    <div class="receipt-note-block">
                        <h3>Important Notes</h3>
                        <p>{{ $invoice->notes ?: 'Please include the invoice number on all payments. Retain this document for your records.' }}</p>
                    </div>
                </div>

                <div class="receipt-summary">
                    <h3 class="receipt-summary-title">Invoice Summary</h3>
                    <div class="receipt-summary-line"><span>Weight Charges</span><strong>JMD {{ number_format($packageSubtotal, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Processing</span><strong>JMD {{ number_format($processingSubtotal, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Storage</span><strong>JMD {{ number_format($invoice->storage_fee_jmd ?: $itemStorageSubtotal, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Service Fee</span><strong>JMD {{ number_format($invoice->service_fee_jmd, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Customs Fee</span><strong>JMD {{ number_format($invoice->customs_fee_jmd, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>GCT</span><strong>JMD {{ number_format($invoice->gct_amount_jmd, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Discount</span><strong>- JMD {{ number_format($invoice->discount_amount_jmd, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Paid</span><strong>JMD {{ number_format($amountPaid, 2) }}</strong></div>
                    <div class="receipt-summary-line"><span>Invoice Total</span><strong>JMD {{ number_format($invoice->total_jmd, 2) }}</strong></div>
                    <div class="receipt-summary-line receipt-grand"><span>Total Due (JMD)</span><strong>JMD {{ number_format($balanceDue, 2) }}</strong></div>
                </div>
            </div>
        </div>
    </div>
    <footer class="receipt-footer">
        Thank you for choosing {{ $tenantName }}. This document remains available in the portal for viewing, printing, and download.
    </footer>
</section>
