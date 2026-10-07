<?php

namespace App\Support;

use App\Models\SuperadminLogisticsInvoice;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;

class LogisticsInvoicePdf
{
    private static function filename(SuperadminLogisticsInvoice $invoice): string
    {
        return preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoice->invoice_number).'-thermal.pdf';
    }

    public static function download(SuperadminLogisticsInvoice $invoice, Collection $platformSettings): Response
    {
        return SimplePdf::download(self::filename($invoice), self::lines($invoice, $platformSettings), [
            'width' => 226.77,
            'height' => 841.89,
            'margin' => 12,
            'font_size' => 7.5,
            'leading' => 10,
        ]);
    }

    public static function inline(SuperadminLogisticsInvoice $invoice, Collection $platformSettings): Response
    {
        return SimplePdf::inline(self::filename($invoice), self::lines($invoice, $platformSettings), [
            'width' => 226.77,
            'height' => 841.89,
            'margin' => 12,
            'font_size' => 7.5,
            'leading' => 10,
        ]);
    }

    public static function lines(SuperadminLogisticsInvoice $invoice, Collection $platformSettings): array
    {
        $companyName = $platformSettings['company_name'] ?? config('app.name', 'Courier SaaS');
        $companyAddress = $platformSettings['company_address'] ?? '';
        $companyEmail = $platformSettings['company_email'] ?? ($platformSettings['mail_from_address'] ?? config('mail.from.address'));
        $companyPhone = $platformSettings['company_phone'] ?? '';
        $banking = trim((string) ($platformSettings['courier_bill_payment_instructions'] ?? ''));
        $exchangeRate = (float) optional($invoice->items->first())->exchange_rate_jmd;
        $weightLb = $invoice->items->sum(fn ($item) => $item->weight_unit === 'lb' ? (float) $item->weight : (float) $item->weight * 2.20462262);
        $weightKg = $invoice->items->sum(fn ($item) => $item->weight_unit === 'kg' ? (float) $item->weight : (float) $item->weight * 0.45359237);
        $warehouseUsd = $invoice->items->sum(fn ($item) => (float) $item->warehouse_fee);
        $serviceUsd = $invoice->items->sum(fn ($item) => (float) $item->service_fee);
        $freightUsd = $invoice->items->sum(fn ($item) => (float) $item->freight_charge);
        $totalUsd = $invoice->items->sum(fn ($item) => (float) $item->amount_usd);

        $lines = [
            self::center($companyName),
            self::center($companyAddress ?: '-'),
            self::center($companyPhone ?: '-'),
            self::center($companyEmail ?: '-'),
            self::rule(),
            self::center('RECEIPT / INVOICE'),
            self::center(strtoupper(str_replace('_', ' ', $invoice->status))),
            self::rule(),
            'INV NO: '.$invoice->invoice_number,
            'DATE: '.optional($invoice->created_at)->format('M d, Y'),
            'DUE: '.optional($invoice->created_at)->copy()?->addDays(14)->format('M d, Y'),
            'AWB/BL: '.(optional($invoice->manifest)->warehouse_reference ?: optional($invoice->manifest)->manifest_number ?: '-'),
            self::rule(),
            'BILLED TO:',
            optional($invoice->tenant)->company_name ?: '-',
            optional($invoice->tenant)->address ?: '-',
            optional($invoice->tenant)->email ?: '-',
            optional($invoice->tenant)->phone ?: '-',
            self::rule(),
            'SHIPMENT SUMMARY',
            'Packages: '.$invoice->items->count(),
            'Weight: '.number_format($weightLb, 2).' lb / '.number_format($weightKg, 2).' kg',
            'Warehouse: US$'.number_format($warehouseUsd, 2),
            'Service: US$'.number_format($serviceUsd, 2),
            'Freight: US$'.number_format($freightUsd, 2),
            'Exchange: JMD '.number_format($exchangeRate, 4),
            self::rule(),
            'PACKAGES',
        ];

        foreach ($invoice->items as $item) {
            $lines[] = 'HAWB: '.($item->hawbNumber() ?: '-');
            $lines[] = 'TRK: '.$item->displayTrackingNumber();
            $lines[] = number_format((float) $item->weight, 2).' '.$item->weight_unit.'  US$'.number_format((float) $item->amount_usd, 2).'  JM$'.number_format((float) $item->amount, 2);
            $lines[] = '- - - - - - - - - - - - - -';
        }

        $lines = array_merge($lines, [
            self::rule(),
            'SUMMARY',
            self::amountLine('Warehouse Fee', 'US$', $warehouseUsd),
            self::amountLine('Service Fee', 'US$', $serviceUsd),
            self::amountLine('Freight', 'US$', $freightUsd),
            self::amountLine('Total USD', 'US$', $totalUsd),
            self::amountLine('Subtotal JMD', 'JM$', (float) $invoice->subtotal),
            self::amountLine('Discount/Credit', 'JM$', (float) $invoice->discount_jmd),
            self::amountLine('Paid', 'JM$', (float) $invoice->amount_paid_jmd),
            self::rule(),
            self::amountLine('TOTAL DUE', 'JM$', (float) $invoice->balance_jmd),
            self::rule(),
        ]);

        if ($banking !== '') {
            $lines[] = 'PAYMENT INSTRUCTIONS';
            foreach (preg_split('/\r\n|\r|\n/', $banking) as $line) {
                $lines[] = trim($line);
            }
            $lines[] = self::rule();
        }

        $lines[] = self::center('Thank you');
        $lines[] = self::center($companyName);

        return $lines;
    }

    private static function center(string $text): string
    {
        return str_pad(substr($text, 0, 32), 32, ' ', STR_PAD_BOTH);
    }

    private static function rule(): string
    {
        return '--------------------------------';
    }

    private static function amountLine(string $label, string $currency, float $amount): string
    {
        $left = substr($label, 0, 17);
        $right = $currency.number_format($amount, 2);

        return str_pad($left, 18).str_pad($right, 14, ' ', STR_PAD_LEFT);
    }
}
