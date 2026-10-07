<?php

namespace App\Services;

use App\Mail\TenantTemplateMail;
use App\Mail\AgingPackagesReminderMail;
use App\Models\ClientNotification;
use App\Models\DeliveryRequest;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerNotificationService
{
    public function customerWelcome(\App\Models\User $user): bool
    {
        $user->loadMissing(['tenant', 'customer']);
        $template = app(EmailTemplateService::class)->customerWelcome($user);

        if (! $template) {
            Log::warning('Customer welcome email template is disabled or missing; using default welcome email.', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
            ]);

            $tenantName = optional($user->tenant)->company_name ?: config('app.name');
            $mailbox = $user->mailbox ?: optional($user->customer)->mailbox ?: '-';
            $subject = 'Welcome to '.$tenantName.' - Your mailbox is '.$mailbox;
            $template = [
                'subject' => $subject,
                'attachments' => [],
                'view' => [
                    'tenant' => $user->tenant,
                    'subject' => $subject,
                    'headline' => 'Welcome to '.$tenantName,
                    'bodyHtml' => nl2br(e("Dear ".($user->full_name ?: 'Customer').",\n\nWelcome to ".$tenantName.". Your account has been created successfully.\n\nYour mailbox number is ".$mailbox.".\n\nPlease include your mailbox number on every package so we can identify your shipment quickly.")),
                    'details' => [
                        'Mailbox' => $mailbox,
                        'Email' => $user->email,
                    ],
                    'buttonLabel' => 'Open Customer Portal',
                    'buttonUrl' => optional($user->tenant)->portalUrl(),
                    'footerHtml' => '',
                    'footerText' => '',
                    'attachmentUrl' => '',
                    'attachments' => [],
                ],
            ];
        }

        return $this->send(
            $user->tenant_id,
            $user->email,
            $template['subject'],
            new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
        );
    }

    public function staffAccountCreated(User $user): bool
    {
        $user->loadMissing(['tenant', 'outlet']);
        $tenantName = optional($user->tenant)->company_name ?: config('app.name');
        $subject = 'Account created for '.$tenantName;
        $view = [
            'tenant' => $user->tenant,
            'subject' => $subject,
            'headline' => 'Your staff account has been created',
            'bodyHtml' => '<p>An account has been created for you in the courier portal. Use your email address to sign in.</p>',
            'details' => [
                'Name' => $user->full_name ?: '-',
                'Email' => $user->email,
                'Role' => ucfirst(str_replace('_', ' ', $user->user_type)),
                'Branch' => optional($user->outlet)->name ?: 'All branches',
            ],
            'buttonLabel' => 'Open Courier Portal',
            'buttonUrl' => optional($user->tenant)->portalUrl(),
            'footerHtml' => '',
            'footerText' => 'Contact your courier administrator if you did not expect this account.',
            'attachmentUrl' => '',
        ];

        return $this->send(
            $user->tenant_id,
            $user->email,
            $subject,
            new TenantTemplateMail($subject, $view)
        );
    }

    public function courierAccountCreated(User $user): bool
    {
        $user->loadMissing(['tenant']);
        $tenantName = optional($user->tenant)->company_name ?: config('app.name');
        $subject = $tenantName.' account created';
        $view = [
            'tenant' => $user->tenant,
            'subject' => $subject,
            'headline' => 'Your courier company account has been created',
            'bodyHtml' => '<p>Your courier company account has been successfully created. We have received your registration details and documents.</p><p>Please complete payment or wait for payment confirmation so your courier portal can be activated.</p>',
            'details' => [
                'Company' => $tenantName,
                'Admin' => $user->full_name ?: '-',
                'Email' => $user->email,
                'Status' => ucfirst(str_replace('_', ' ', optional($user->tenant)->status ?: 'pending')),
            ],
            'buttonLabel' => 'Open Courier Portal',
            'buttonUrl' => optional($user->tenant)->portalUrl(),
            'footerHtml' => '',
            'footerText' => 'Contact support if you did not create this account.',
            'attachmentUrl' => '',
        ];

        return $this->send(
            $user->tenant_id,
            $user->email,
            $subject,
            new TenantTemplateMail($subject, $view)
        );
    }

    public function passwordReset(User $user, string $token): bool
    {
        $user->loadMissing(['tenant']);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
        ]);
        $template = app(EmailTemplateService::class)->passwordReset($user, $resetUrl);

        if ($template) {
            return $this->send(
                $user->tenant_id,
                $user->email,
                $template['subject'],
                new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
            );
        }

        $tenantName = optional($user->tenant)->company_name ?: config('app.name');
        $subject = 'Reset your '.$tenantName.' password';
        $view = [
            'tenant' => $user->tenant,
            'subject' => $subject,
            'headline' => 'Reset your password',
            'bodyHtml' => nl2br(e("Hello ".($user->full_name ?: 'there').",\n\nWe received a request to reset your password. Use the button below to set a new password. If you did not request this, you can ignore this email.")),
            'details' => [
                'Account' => $user->email,
                'Courier' => $tenantName,
                'Expires' => config('auth.passwords.users.expire', 60).' minutes',
            ],
            'buttonLabel' => 'Reset Password',
            'buttonUrl' => $resetUrl,
            'footerHtml' => '',
            'footerText' => 'For your security, this reset link can only be used for a limited time.',
            'attachmentUrl' => '',
        ];

        return $this->send(
            $user->tenant_id,
            $user->email,
            $subject,
            new TenantTemplateMail($subject, $view)
        );
    }

    public function packageCreated(Package $package): void
    {
        $package->loadMissing(['tenant', 'customer.user', 'status']);
        $this->pushInAppPackageCreated($package);
        $template = app(EmailTemplateService::class)->packageCreated($package);
        if (! $template) {
            return;
        }

        $this->send(
            $package->tenant_id,
            optional(optional($package->customer)->user)->email,
            $template['subject'],
            new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
        );
    }

    public function packageStatusUpdated(Package $package, PackageLog $log): void
    {
        $package->loadMissing(['tenant', 'customer.user', 'status']);
        $log->loadMissing('status');
        $this->pushInAppPackageStatus($package, $log);
        $template = app(EmailTemplateService::class)->packageStatusUpdated($package, $log);
        if (! $template) {
            return;
        }

        $this->send(
            $package->tenant_id,
            optional(optional($package->customer)->user)->email,
            $template['subject'],
            new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
        );
    }

    public function invoiceCreated(Invoice $invoice): bool
    {
        $invoice->loadMissing(['tenant', 'customer.user', 'items.package']);
        $this->pushInAppInvoiceCreated($invoice);
        $template = app(EmailTemplateService::class)->invoiceCreated($invoice);
        if (! $template) {
            return false;
        }

        return $this->send(
            $invoice->tenant_id,
            optional(optional($invoice->customer)->user)->email,
            $template['subject'],
            new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
        );
    }

    public function agingPackagesReminder(Collection $packages, ?int $minimumAge = null): bool
    {
        $packages = $packages->filter();
        if ($packages->isEmpty()) {
            return false;
        }

        $first = $packages->first();
        $packages->each->loadMissing(['tenant', 'customer.user', 'status', 'invoiceItems.invoice']);
        $first = $packages->first();
        $email = optional(optional($first->customer)->user)->email;
        if (! $email) {
            return false;
        }

        $template = app(EmailTemplateService::class)->agingReminder($packages, $minimumAge);
        $subject = $template['subject'] ?? 'Package pickup reminder';
        $mailable = $template
            ? new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
            : new AgingPackagesReminderMail($packages, $minimumAge);

        $sent = $this->send(
            $first->tenant_id,
            $email,
            $subject,
            $mailable
        );

        if ($sent) {
            $this->pushInAppAgingReminder($packages, $minimumAge);
        }

        return $sent;
    }

    public function deliveryRequestStatusUpdated(DeliveryRequest $deliveryRequest, string $previousStatus): bool
    {
        $deliveryRequest->loadMissing(['tenant', 'customer.user', 'assignee']);
        $template = app(EmailTemplateService::class)->deliveryStatusUpdated($deliveryRequest, $previousStatus);
        if (! $template) {
            return false;
        }

        return $this->send(
            $deliveryRequest->tenant_id,
            optional(optional($deliveryRequest->customer)->user)->email,
            $template['subject'],
            new TenantTemplateMail($template['subject'], $template['view'], $template['attachments'] ?? [])
        );
    }

    private function send(?int $tenantId, ?string $email, string $subject, $mailable): bool
    {
        if (! $email) {
            return false;
        }

        $log = EmailLog::create([
            'tenant_id' => $tenantId,
            'to_email' => $email,
            'subject' => $subject,
            'body' => '',
            'status' => 'queued',
            'created_at' => now(),
        ]);

        Log::info('Email queued for sending', [
            'email_log_id' => $log->id,
            'tenant_id' => $tenantId,
            'to_email' => $email,
            'subject' => $subject,
        ]);

        try {
            app(TenantMailService::class)->send($tenantId, $email, $mailable);
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            Log::info('Email sent successfully', [
                'email_log_id' => $log->id,
                'tenant_id' => $tenantId,
                'to_email' => $email,
                'subject' => $subject,
            ]);
            return true;
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            Log::error('Email send failed and was recorded in email_logs', [
                'email_log_id' => $log->id,
                'tenant_id' => $tenantId,
                'to_email' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
                'previous_error' => optional($exception->getPrevious())->getMessage(),
            ]);
            return false;
        }
    }

    private function pushInAppPackageStatus(Package $package, PackageLog $log): void
    {
        $user = optional($package->customer)->user;
        if (! $user) {
            return;
        }

        ClientNotification::firstOrCreate([
            'tenant_id' => $package->tenant_id,
            'user_id' => $user->id,
            'type' => 'package_status',
            'title' => 'Package status updated: '.$package->tracking_number,
            'created_at' => $log->created_at ?: now(),
        ], [
            'message' => trim(implode("\n", [
                $log->note ?: 'Status updated to '.optional($package->status)->name,
                '',
                'HAWB: '.$package->tracking_number,
                'Supplier Tracking: '.($package->supplier_tracking ?: '-'),
                'Status: '.(optional($package->status)->name ?: 'Updated'),
                'Pickup Location: '.($log->location ?: '-'),
            ])),
            'data' => [
                'package_id' => $package->id,
                'package_log_id' => $log->id,
                'tracking_number' => $package->tracking_number,
                'supplier_tracking' => $package->supplier_tracking,
                'status_id' => $package->status_id,
                'pickup_location' => $log->location,
                'url' => route('customer.packages.show', $package),
            ],
            'is_read' => false,
        ]);
    }

    private function pushInAppPackageCreated(Package $package): void
    {
        $user = optional($package->customer)->user;
        if (! $user) {
            return;
        }

        ClientNotification::firstOrCreate([
            'tenant_id' => $package->tenant_id,
            'user_id' => $user->id,
            'type' => 'package_created',
            'title' => 'Package received: '.$package->tracking_number,
            'created_at' => $package->created_at ?: now(),
        ], [
            'message' => trim(implode("\n", [
                'A package has been received for your account.',
                '',
                'HAWB: '.$package->tracking_number,
                'Supplier Tracking: '.($package->supplier_tracking ?: '-'),
                'Status: '.(optional($package->status)->name ?: 'Received'),
            ])),
            'data' => [
                'package_id' => $package->id,
                'tracking_number' => $package->tracking_number,
                'supplier_tracking' => $package->supplier_tracking,
                'status_id' => $package->status_id,
                'url' => route('customer.packages.show', $package),
            ],
            'is_read' => false,
        ]);
    }

    private function pushInAppInvoiceCreated(Invoice $invoice): void
    {
        $user = optional($invoice->customer)->user;
        if (! $user) {
            return;
        }

        ClientNotification::firstOrCreate([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => $user->id,
            'type' => 'invoice_created',
            'title' => 'Invoice created: '.$invoice->invoice_number,
            'created_at' => $invoice->created_at ?: now(),
        ], [
            'message' => trim(implode("\n", [
                'A new invoice is available in your account.',
                '',
                'Invoice: '.$invoice->invoice_number,
                'Total: JMD '.number_format((float) $invoice->total_jmd, 2),
                'Payment Status: '.ucfirst($invoice->payment_status),
            ])),
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_jmd' => (float) $invoice->total_jmd,
                'payment_status' => $invoice->payment_status,
                'url' => route('customer.invoices.show', $invoice->id),
            ],
            'is_read' => false,
        ]);
    }

    private function pushInAppAgingReminder(Collection $packages, ?int $minimumAge = null): void
    {
        $first = $packages->first();
        if (! $first) {
            return;
        }

        $user = optional($first->customer)->user;
        if (! $user) {
            return;
        }

        $trackingNumbers = $packages->pluck('tracking_number')->filter()->values();
        ClientNotification::create([
            'tenant_id' => $first->tenant_id,
            'user_id' => $user->id,
            'type' => 'aging_package',
            'title' => 'Package pickup reminder',
            'message' => trim(implode("\n", [
                'You have '.$packages->count().' package'.($packages->count() === 1 ? '' : 's').' waiting for pickup or delivery.',
                '',
                'Tracking: '.$trackingNumbers->implode(', '),
            ])),
            'data' => [
                'package_ids' => $packages->pluck('id')->values()->all(),
                'tracking_numbers' => $trackingNumbers->all(),
                'package_count' => $packages->count(),
                'url' => route('customer.packages'),
            ],
            'is_read' => false,
            'created_at' => now(),
        ]);
    }
}
