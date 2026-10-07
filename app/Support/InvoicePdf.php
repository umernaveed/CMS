<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\TenantSetting;
use App\Models\WeightRate;
use Illuminate\Http\Response;

class InvoicePdf
{
    public static function download(Invoice $invoice): Response
    {
        $documentType = $invoice->payment_status === 'paid' ? 'receipt' : 'invoice';
        $filename = preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoice->invoice_number).'-'.$documentType.'.pdf';

        return SimplePdf::download($filename, self::lines($invoice), [
            'margin' => 34,
            'font_size' => 10,
            'leading' => 14,
        ]);
    }

    public static function thermal(Invoice $invoice): Response
    {
        $documentType = $invoice->payment_status === 'paid' ? 'receipt' : 'invoice';
        $filename = preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoice->invoice_number).'-'.$documentType.'-thermal.pdf';

        return SimplePdf::inline($filename, self::lines($invoice), [
            'width' => 226.77,
            'height' => 841.89,
            'margin' => 12,
            'font_size' => 7.5,
            'leading' => 10,
        ]);
    }

    public static function lines(Invoice $invoice): array
    {
        $tenant = $invoice->tenant;
        $customer = $invoice->customer;
        $customerUser = optional($customer)->user;
        $documentType = strtoupper($invoice->payment_status === 'paid' ? 'RECEIPT' : 'INVOICE');
        $amountPaid = $invoice->payments->where('status', 'approved')->sum('amount');
        $balance = max(0, (float) $invoice->total_jmd - (float) $amountPaid);
        $bankingDetails = trim((string) TenantSetting::getValue((int) $invoice->tenant_id, 'banking_details', ''));

        $lines = [
            (optional($tenant)->company_name ?: config('app.name', 'Courier SaaS')).' '.$documentType,
            str_repeat('=', 60),
            'Invoice: '.$invoice->invoice_number,
            optional($tenant)->address ?: '',
            trim((optional($tenant)->phone ?: '').' '.(optional($tenant)->email ?: '')),
            optional($tenant)->logo ? 'Logo: '.optional($tenant)->logo : '',
            '',
            'BILL TO',
            optional($customerUser)->full_name ?: optional($customer)->mailbox ?: '-',
            'Mailbox: '.(optional($customer)->mailbox ?: $invoice->mailbox ?: '-'),
            optional($customerUser)->email ?: '',
            optional($customerUser)->phone ?: '',
            '',
            'Invoice Date: '.optional($invoice->created_at)->format('M d, Y'),
            'Due Date: '.optional($invoice->due_date)->format('M d, Y'),
            'Invoice Status: '.ucfirst((string) $invoice->invoice_status),
            'Payment Status: '.ucfirst((string) $invoice->payment_status),
            '',
            'ITEMS',
            str_repeat('-', 60),
        ];

        foreach ($invoice->items as $item) {
            $tracking = optional($item->package)->tracking_number ?: $item->tracking_number ?: '-';
            $billableWeight = (float) ($item->billable_weight ?: $item->weight);
            $lines[] = $tracking.' | '.($item->description ?: 'Package');
            $unit = $item->measure_unit ?: (($item->freight_type ?? optional($item->package)->freight_type) === 'sea' ? 'cu.ft' : 'lb');
            $conversion = $unit === 'CBM'
                ? number_format($billableWeight * WeightRate::CBM_TO_CUFT, 2).' cu.ft'
                : ($unit === 'cu.ft' ? number_format($billableWeight / WeightRate::CBM_TO_CUFT, 3).' CBM' : number_format($billableWeight * 0.45359237, 2).' kg');
            $lines[] = '  Billable '.number_format($billableWeight, 2).' '.$unit.' / '.$conversion.' | Rate JMD '.number_format((float) $item->rate_jmd, 2).'/'.$unit;
            $lines[] = '  Weight charge JMD '.number_format((float) $item->package_price_jmd, 2).' | Line total JMD '.number_format((float) $item->total_jmd, 2);
        }

        $lines = array_merge($lines, [
            str_repeat('-', 60),
            'Weight Charges / Subtotal: JMD '.number_format((float) $invoice->subtotal_jmd, 2),
            'Service Fee: JMD '.number_format((float) $invoice->service_fee_jmd, 2),
            'Customs Fee: JMD '.number_format((float) $invoice->customs_fee_jmd, 2),
            'Storage Fee: JMD '.number_format((float) $invoice->storage_fee_jmd, 2),
            'GCT: JMD '.number_format((float) $invoice->gct_amount_jmd, 2),
            'Discount: JMD '.number_format((float) $invoice->discount_amount_jmd, 2),
            'TOTAL: JMD '.number_format((float) $invoice->total_jmd, 2),
            'Paid: JMD '.number_format((float) $amountPaid, 2),
            'Balance: JMD '.number_format($balance, 2),
        ]);

        if ($bankingDetails !== '') {
            $lines[] = '';
            $lines[] = 'BANKING DETAILS';
            foreach (preg_split('/\r\n|\r|\n/', $bankingDetails) as $line) {
                $lines[] = trim($line);
            }
        }

        $lines[] = '';
        $lines[] = 'Generated '.now()->format('M d, Y h:i A');

        return $lines;
    }
}
