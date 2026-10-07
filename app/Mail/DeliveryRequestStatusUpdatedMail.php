<?php

namespace App\Mail;

use App\Models\DeliveryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryRequestStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public DeliveryRequest $deliveryRequest;
    public string $previousStatus;

    public function __construct(DeliveryRequest $deliveryRequest, string $previousStatus)
    {
        $this->deliveryRequest = $deliveryRequest;
        $this->previousStatus = $previousStatus;
    }

    public function build()
    {
        return $this->subject('Delivery request updated: '.$this->deliveryRequest->request_number)
            ->view('emails.delivery.status-updated', [
                'deliveryRequest' => $this->deliveryRequest,
                'previousStatus' => $this->previousStatus,
                'tenantName' => optional($this->deliveryRequest->tenant)->company_name ?: config('app.name'),
            ]);
    }
}
