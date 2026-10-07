@once
<style>
    .invoice-checkout-modal { z-index: 2070; }
    .invoice-checkout-modal + .modal-backdrop,
    .modal-backdrop.show { z-index: 2060; }
    .invoice-checkout-modal .modal-content {
        border: 0;
        border-radius: 10px;
        overflow: hidden;
    }
    .invoice-checkout-modal .modal-header,
    .invoice-checkout-modal .modal-footer {
        flex: 0 0 auto;
        background: #fff;
        z-index: 2;
    }
    .invoice-checkout-modal .modal-body {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    @media (max-width: 768px) {
        .invoice-checkout-modal {
            padding: 0 !important;
        }
        .invoice-checkout-modal .modal-dialog {
            width: 100%;
            max-width: none;
            height: 100dvh;
            min-height: 100dvh;
            margin: 0;
            display: flex;
            align-items: stretch;
        }
        .invoice-checkout-modal .modal-content {
            width: 100%;
            height: 100dvh;
            max-height: 100dvh;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }
        .invoice-checkout-modal .modal-header {
            position: sticky;
            top: 0;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .invoice-checkout-modal .modal-title {
            font-size: 1rem;
            line-height: 1.25;
        }
        .invoice-checkout-modal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            padding: 14px;
            padding-bottom: 96px;
        }
        .invoice-checkout-modal .modal-footer {
            position: sticky;
            bottom: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px 14px max(16px, calc(env(safe-area-inset-bottom) + 12px));
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -10px 24px rgba(15, 23, 42, .12);
        }
        .invoice-checkout-modal .modal-footer .btn {
            width: 100%;
            min-height: 46px;
            white-space: normal;
        }
        .invoice-checkout-modal textarea.form-control {
            min-height: 128px;
        }
        body.modal-open .sidebar-mobile-toggle {
            display: none !important;
        }
    }
</style>
@endonce
<div class="modal fade text-start invoice-checkout-modal" id="payInvoice{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable invoice-checkout-dialog">
        <form method="POST" action="{{ route('courier.invoices.pay', $invoice) }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Close Invoice {{ $invoice->invoice_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method_id" class="form-select" required data-payment-method-select>
                        <option value="">Select method</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->id }}" data-code="{{ strtoupper($method->code) }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3 d-none" data-points-panel>
                    @php
                        $pointValue = (float) \App\Models\TenantSetting::getValue($invoice->tenant_id, 'loyalty_point_value_jmd', 1);
                        $approvedPaid = $invoice->payments->where('status', 'approved')->sum('amount');
                        $remainingBalance = max(0, (float) $invoice->total_jmd - (float) $approvedPaid);
                        $customerPoints = $invoice->customer ? app(\App\Services\LoyaltyService::class)->pointsBalance($invoice->customer) : 0;
                        $customerWallet = $invoice->customer ? app(\App\Services\LoyaltyService::class)->cashBalance($invoice->customer) : 0;
                        $referralPoints = (float) optional($invoice->customer)->total_referral_points;
                    @endphp
                    <label class="form-label">Points to Apply</label>
                    <input type="number" name="points_redeemed" class="form-control" min="0.01" step="0.01" placeholder="Enter points" data-points-input data-point-value="{{ $pointValue }}" data-remaining="{{ $remainingBalance }}">
                    <div class="form-text">
                        Remaining balance: JMD {{ number_format($remainingBalance, 2) }}.
                        Point value: JMD {{ number_format($pointValue, 2) }} each.
                        Reward balance: {{ number_format($customerPoints, 2) }}.
                        Referral balance: {{ number_format($referralPoints, 2) }}.
                    </div>
                    <div class="small fw-semibold mt-1" data-points-estimate></div>
                </div>
                <div class="alert alert-light border small d-none" data-wallet-panel>
                    Customer cash wallet: <strong>JMD {{ number_format($customerWallet, 2) }}</strong>.
                    This invoice requires <strong>JMD {{ number_format($remainingBalance, 2) }}</strong>.
                </div>
                <div class="border rounded p-3 mb-3 d-none" data-cash-panel data-remaining="{{ $remainingBalance }}">
                    <label class="form-label">Cash Received (JMD)</label>
                    <input type="number" name="amount_received" class="form-control" min="{{ number_format($remainingBalance, 2, '.', '') }}" step="0.01" data-cash-received>
                    <div class="alert alert-info mt-2 mb-2 py-2">
                        Customer should receive: <strong data-change-due>JMD 0.00</strong>
                    </div>
                    @if($invoice->customer)
                        <label class="form-check mb-0">
                            <input type="checkbox" name="credit_change_to_wallet" value="1" class="form-check-input" data-change-wallet>
                            <span class="form-check-label">Credit the change to the customer cash wallet</span>
                        </label>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Staff</label>
                    <input class="form-control" value="{{ auth()->user()->full_name ?: trim(auth()->user()->first_name.' '.auth()->user()->last_name) }}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference</label>
                    <input name="reference_number" class="form-control" value="{{ $invoice->invoice_number }}" placeholder="Receipt, auth, or transaction reference">
                </div>
                <div class="mb-3">
                    <label class="form-label">Proof of Payment Receipt</label>
                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <div class="form-text">Attach a bank slip, card receipt, transfer confirmation, or cash receipt image/PDF.</div>
                </div>
                <div class="form-check mb-3">
                    <input type="hidden" name="require_handoff_verification" value="0">
                    <input class="form-check-input" type="checkbox" name="require_handoff_verification" value="1" id="requireHandoff{{ $invoice->id }}" data-handoff-toggle @checked($invoice->require_handoff_verification ?? true)>
                    <label class="form-check-label" for="requireHandoff{{ $invoice->id }}">Scan or enter tracking / HAWB before handoff</label>
                </div>
                <div class="mb-3" data-handoff-panel>
                    <label class="form-label">Tracking / HAWB Numbers</label>
                    <textarea name="tracking_numbers" class="form-control" rows="4" placeholder="Scan or enter one tracking number per line" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false"></textarea>
                    <div class="form-text">This field is never prefilled. All invoice packages must be scanned and matched before the invoice can be closed.</div>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success">Record Payment</button>
            </div>
        </form>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-payment-method-select]').forEach(function (select) {
        const form = select.closest('form');
        const panel = form ? form.querySelector('[data-points-panel]') : null;
        const walletPanel = form ? form.querySelector('[data-wallet-panel]') : null;
        const cashPanel = form ? form.querySelector('[data-cash-panel]') : null;
        const cashReceived = form ? form.querySelector('[data-cash-received]') : null;
        const changeDue = form ? form.querySelector('[data-change-due]') : null;
        const changeWallet = form ? form.querySelector('[data-change-wallet]') : null;
        const input = form ? form.querySelector('[data-points-input]') : null;
        const estimate = form ? form.querySelector('[data-points-estimate]') : null;

        function updatePointPanel() {
            const option = select.options[select.selectedIndex];
            const code = option ? option.dataset.code : '';
            const isPoints = code === 'REWARDS' || code === 'REFERRAL';
            if (panel) panel.classList.toggle('d-none', !isPoints);
            if (walletPanel) walletPanel.classList.toggle('d-none', code !== 'WALLET');
            if (cashPanel) cashPanel.classList.toggle('d-none', code !== 'CASH');
            if (input) input.required = isPoints;
            if (cashReceived) cashReceived.required = code === 'CASH';
            if (code === 'CASH' && cashReceived && !cashReceived.value) {
                cashReceived.value = cashPanel.dataset.remaining || '';
            }
            updateEstimate();
            updateChange();
        }

        function updateEstimate() {
            if (!input || !estimate) return;
            const points = parseFloat(input.value || '0');
            const pointValue = parseFloat(input.dataset.pointValue || '0');
            const remaining = parseFloat(input.dataset.remaining || '0');
            if (!points || !pointValue) {
                estimate.textContent = '';
                return;
            }
            const value = points * pointValue;
            estimate.textContent = 'Applied value: JMD ' + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (value + 0.01 >= remaining ? ' (covers the remaining balance)' : ' (partial payment)');
        }

        function updateChange() {
            if (!cashPanel || !cashReceived || !changeDue) return;
            const received = parseFloat(cashReceived.value || '0');
            const remaining = parseFloat(cashPanel.dataset.remaining || '0');
            const change = Math.max(0, received - remaining);
            changeDue.textContent = 'JMD ' + change.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (changeWallet) {
                changeWallet.disabled = change <= 0;
                if (change <= 0) changeWallet.checked = false;
            }
        }

        select.addEventListener('change', updatePointPanel);
        if (input) input.addEventListener('input', updateEstimate);
        if (cashReceived) cashReceived.addEventListener('input', updateChange);
        updatePointPanel();
    });
});
</script>
@endonce

