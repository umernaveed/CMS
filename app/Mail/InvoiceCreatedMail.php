<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function build()
    {
        return $this->subject('Invoice created: '.$this->invoice->invoice_number)
            ->view('emails.invoices.created', [
                'invoice' => $this->invoice,
                'tenantName' => optional($this->invoice->tenant)->company_name ?: config('app.name'),
            ]);
    }
}
