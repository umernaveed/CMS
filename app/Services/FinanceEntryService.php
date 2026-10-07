<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Models\Invoice;
use App\Models\LedgerCategory;
use App\Models\SuperadminBill;
use App\Models\SuperadminBillPayment;
use App\Models\SuperadminLogisticsInvoice;
use App\Models\SuperadminLogisticsInvoicePayment;

class FinanceEntryService
{
    public function recordCustomerInvoiceIncome(Invoice $invoice): FinanceEntry
    {
        $invoice->loadMissing(['customer.user']);
        $this->ensureCustomerInvoiceCategory((int) $invoice->tenant_id);

        return FinanceEntry::updateOrCreate([
            'tenant_id' => $invoice->tenant_id,
            'type' => 'income',
            'category' => 'Customer Invoice',
            'reference_number' => $invoice->invoice_number,
        ], [
            'outlet_id' => $invoice->outlet_id,
            'description' => 'Customer invoice '.$invoice->invoice_number,
            'amount' => (float) $invoice->total_jmd,
            'entry_date' => optional($invoice->paid_at)->toDateString() ?: now()->toDateString(),
            'notes' => trim('Automatically recorded from paid customer invoice. '.(optional(optional($invoice->customer)->user)->full_name ?: '')),
            'created_by' => auth()->id(),
        ]);
    }

    public function recordSystemAdminBillExpense(SuperadminBill $bill, SuperadminBillPayment $payment): FinanceEntry
    {
        $this->ensureCourierCategory((int) $bill->tenant_id, 'expense', 'System Admin Billing', 'Automatically recorded system admin bill payments.');

        return FinanceEntry::updateOrCreate([
            'tenant_id' => $bill->tenant_id,
            'type' => 'expense',
            'category' => 'System Admin Billing',
            'reference_number' => $bill->bill_number.'-'.$payment->id,
        ], [
            'description' => $bill->title,
            'amount' => (float) $payment->amount_jmd,
            'entry_date' => optional($payment->reviewed_at)->toDateString() ?: now()->toDateString(),
            'notes' => trim(($bill->category ?: '')."\n".($payment->admin_notes ?: '')),
            'created_by' => auth()->id(),
        ]);
    }

    public function recordLogisticsInvoiceExpense(SuperadminLogisticsInvoice $invoice, SuperadminLogisticsInvoicePayment $payment, ?float $amount = null): FinanceEntry
    {
        $invoice->loadMissing(['manifest.shipment']);
        $this->ensureCourierCategory((int) $invoice->tenant_id, 'expense', 'Shipment Invoice', 'Automatically recorded paid logistics shipment invoices.');

        $description = 'Shipment invoice '.$invoice->invoice_number;
        if ($invoice->manifest) {
            $description .= ' / '.($invoice->manifest->warehouse_reference ?: $invoice->manifest->manifest_number);
        }

        return FinanceEntry::updateOrCreate([
            'tenant_id' => $invoice->tenant_id,
            'type' => 'expense',
            'category' => 'Shipment Invoice',
            'reference_number' => $invoice->invoice_number.'-'.$payment->id,
        ], [
            'description' => $description,
            'amount' => (float) ($amount ?? $payment->amount_jmd),
            'entry_date' => optional($payment->created_at)->toDateString() ?: optional($invoice->paid_at)->toDateString() ?: now()->toDateString(),
            'notes' => trim(
                'Automatically recorded from paid shipment invoice.'."\n".
                'Payment method: '.$payment->payment_method."\n".
                ($payment->reference_number ? 'Reference: '.$payment->reference_number."\n" : '').
                ($payment->notes ?: '')
            ),
            'created_by' => $payment->created_by ?: auth()->id(),
        ]);
    }

    public function syncLogisticsInvoiceExpenses(SuperadminLogisticsInvoice $invoice): void
    {
        $invoice->loadMissing('payments');
        $total = max(0, (float) $invoice->total);
        $remaining = $total;
        $payments = $invoice->payments->sortBy('id');

        $this->clearLogisticsInvoiceExpenses($invoice);

        foreach ($payments as $payment) {
            $amount = min((float) $payment->amount_jmd, $remaining);
            if ($amount <= 0.01) {
                continue;
            }

            $this->recordLogisticsInvoiceExpense($invoice, $payment, $amount);
            $remaining = max(0, $remaining - $amount);
        }
    }

    public function clearLogisticsInvoiceExpenses(SuperadminLogisticsInvoice $invoice): void
    {
        FinanceEntry::where('tenant_id', $invoice->tenant_id)
            ->where('type', 'expense')
            ->where('category', 'Shipment Invoice')
            ->where('reference_number', 'like', $invoice->invoice_number.'-%')
            ->delete();
    }

    private function ensureCustomerInvoiceCategory(int $tenantId): void
    {
        $this->ensureCourierCategory($tenantId, 'income', 'Customer Invoice', 'Automatically recorded customer invoice payments.');
    }

    private function ensureCourierCategory(int $tenantId, string $type, string $name, string $description): void
    {
        LedgerCategory::firstOrCreate([
            'portal' => 'courier',
            'scope_key' => 'tenant:'.$tenantId,
            'type' => $type,
            'name' => $name,
        ], [
            'tenant_id' => $tenantId,
            'description' => $description,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
    }
}
