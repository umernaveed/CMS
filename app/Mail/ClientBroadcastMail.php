<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;
    public $plainContent;
    public array $downloadAttachments;

    public function __construct(string $subject, string $content, array $attachments = [])
    {
        $this->subject = $subject;
        $this->subject($subject);
        $this->content = $content;
        $this->plainContent = $this->convertHtmlToText($content);
        $this->downloadAttachments = $attachments;
    }

    public function build()
    {
        $mail = $this->view('emails.crm.broadcast')
            ->text('emails.crm.broadcast_plain')
            ->with(['attachments' => $this->downloadAttachments]);

        foreach ($this->downloadAttachments as $attachment) {
            $path = $attachment['path'] ?? null;
            if ($path && is_file(public_path($path))) {
                $mail->attach(public_path($path), ['as' => $attachment['name'] ?? basename($path)]);
            }
        }

        return $mail;
    }

    protected function convertHtmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</(p|div|h[1-6]|li)>#i', "\n", $text);
        $text = preg_replace('#<(ul|ol)[^>]*>#i', "\n", $text);
        $text = preg_replace('#<li[^>]*>#i', " - ", $text);
        $text = preg_replace('#<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#i', '$2 ($1)', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{2,}/', "\n\n", $text);
        return trim($text);
    }
}
