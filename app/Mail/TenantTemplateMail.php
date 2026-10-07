<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $emailViewData;
    private string $emailSubject;
    private array $downloadAttachments;

    public function __construct(string $subject, array $viewData, array $attachments = [])
    {
        $this->emailSubject = $subject;
        $this->emailViewData = array_merge([
            'tenant' => null,
            'subject' => $subject,
            'headline' => $subject,
            'bodyHtml' => '',
            'details' => [],
            'mailingAddresses' => [],
            'buttonLabel' => '',
            'buttonUrl' => '',
            'footerHtml' => '',
            'footerText' => '',
            'attachmentUrl' => '',
            'attachments' => [],
        ], $viewData);
        $this->downloadAttachments = $attachments;
    }

    public function build()
    {
        $mail = $this->subject($this->emailSubject)
            ->view('emails.tenant-template', $this->emailViewData);

        foreach ($this->downloadAttachments as $attachment) {
            $path = $attachment['path'] ?? null;
            if ($path && is_file(public_path($path))) {
                $mail->attach(public_path($path), ['as' => $attachment['name'] ?? basename($path)]);
            }
        }

        return $mail;
    }
}
