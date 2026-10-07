<?php

namespace App\Mail;

use App\Models\Package;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PackageCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Package $package;

    public function __construct(Package $package)
    {
        $this->package = $package;
    }

    public function build()
    {
        $tenantName = optional($this->package->tenant)->company_name ?: config('app.name');

        return $this->subject('Package received: '.$this->package->tracking_number)
            ->view('emails.packages.created', [
                'package' => $this->package,
                'tenantName' => $tenantName,
            ]);
    }
}
