<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AgingPackagesReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $packages;
    public ?int $minimumAge;

    public function __construct(Collection $packages, ?int $minimumAge = null)
    {
        $this->packages = $packages;
        $this->minimumAge = $minimumAge;
    }

    public function build()
    {
        $first = $this->packages->first();

        return $this->subject('Package pickup reminder')
            ->view('emails.packages.aging-reminder', [
                'packages' => $this->packages,
                'customer' => optional($first)->customer,
                'tenantName' => optional(optional($first)->tenant)->company_name ?: config('app.name'),
                'tenantId' => optional($first)->tenant_id,
                'minimumAge' => $this->minimumAge,
            ]);
    }
}
