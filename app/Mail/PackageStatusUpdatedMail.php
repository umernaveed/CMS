<?php

namespace App\Mail;

use App\Models\Package;
use App\Models\PackageLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PackageStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Package $package;
    public PackageLog $log;

    public function __construct(Package $package, PackageLog $log)
    {
        $this->package = $package;
        $this->log = $log;
    }

    public function build()
    {
        return $this->subject('Package status updated: '.$this->package->tracking_number)
            ->view('emails.packages.status-updated', [
                'package' => $this->package,
                'log' => $this->log,
                'tenantName' => optional($this->package->tenant)->company_name ?: config('app.name'),
            ]);
    }
}
