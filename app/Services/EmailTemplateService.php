<?php

namespace App\Services;

use App\Models\DeliveryRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailTemplateService
{
    public function templatesForTenant(int $tenantId): array
    {
        $stored = TenantSetting::getValue($tenantId, 'email_templates', null);
        $decoded = $stored ? json_decode($stored, true) : [];

        return collect($this->defaultTemplates())
            ->mapWithKeys(function ($template, $key) use ($decoded) {
                return [$key => array_merge($template, is_array($decoded[$key] ?? null) ? $decoded[$key] : [])];
            })
            ->all();
    }

    public function saveTemplates(int $tenantId, array $templates): void
    {
        $clean = [];
        foreach ($this->defaultTemplates() as $key => $default) {
            $incoming = $templates[$key] ?? [];
            $clean[$key] = [
                'enabled' => ! empty($incoming['enabled']),
                'subject' => trim((string) ($incoming['subject'] ?? $default['subject'])),
                'headline' => trim((string) ($incoming['headline'] ?? $default['headline'])),
                'body' => trim((string) ($incoming['body'] ?? $default['body'])),
                'button_label' => trim((string) ($incoming['button_label'] ?? $default['button_label'] ?? '')),
                'button_url' => trim((string) ($incoming['button_url'] ?? $default['button_url'] ?? '')),
                'attachments' => collect($incoming['attachments'] ?? [])
                    ->filter(fn ($attachment) => filled($attachment['url'] ?? null) || filled($attachment['path'] ?? null))
                    ->map(fn ($attachment) => [
                        'name' => trim((string) ($attachment['name'] ?? 'Attachment')),
                        'url' => trim((string) ($attachment['url'] ?? '')),
                        'path' => trim((string) ($attachment['path'] ?? '')),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        TenantSetting::putValue($tenantId, 'email_templates', json_encode($clean, JSON_UNESCAPED_SLASHES));
    }

    public function compose(string $key, int $tenantId, array $tokens = [], array $details = []): ?array
    {
        $templates = $this->templatesForTenant($tenantId);
        $template = $templates[$key] ?? null;
        if (! $template || empty($template['enabled'])) {
            return null;
        }

        $tokens = array_merge($this->baseTokens($tenantId), $tokens);
        $subject = $this->render($template['subject'], $tokens);
        $headline = $this->render($template['headline'], $tokens);
        $body = $this->render($template['body'], $tokens);
        if ($this->isPackageEmailTemplate($key)) {
            $body = $this->removePackageCostLines($body);
            unset($details['Cost'], $details['Total'], $details['Total Due'], $details['Package Details']);
        }
        $buttonUrl = $this->render($template['button_url'] ?? '', $tokens);
        $attachments = collect($template['attachments'] ?? [])->map(function ($attachment) use ($tokens) {
            return [
                'name' => $this->render((string) ($attachment['name'] ?? 'Attachment'), $tokens),
                'url' => $this->render((string) ($attachment['url'] ?? ''), $tokens),
                'path' => $attachment['path'] ?? '',
            ];
        })->values()->all();

        $bodyHtml = $this->bodyHtml($body);
        if ($this->isPackageEmailTemplate($key)) {
            $bodyHtml = $this->removePackageCostHtml($bodyHtml);
        }

        return [
            'subject' => $subject,
            'attachments' => $attachments,
            'view' => [
                'tenant' => Tenant::find($tenantId),
                'subject' => $subject,
                'headline' => $headline,
                'bodyHtml' => $bodyHtml,
                'details' => $details,
                'buttonLabel' => $template['button_label'] ?? '',
                'buttonUrl' => $buttonUrl,
                'footerHtml' => TenantSetting::getValue($tenantId, 'email_footer_html', ''),
                'footerText' => TenantSetting::getValue($tenantId, 'email_footer_text', ''),
                'attachmentUrl' => TenantSetting::getValue($tenantId, 'email_attachment_url', ''),
                'attachments' => $attachments,
            ],
        ];
    }

    public function customerWelcome(User $user): ?array
    {
        $user->loadMissing(['customer.shippingAddresses']);
        $customer = $user->customer;
        $shippingAddresses = $customer
            ? $customer->shippingAddresses
                ->where('is_active', true)
                ->sortBy(fn ($address) => [(int) $address->display_order, (int) $address->id])
                ->values()
            : collect();
        $mailingAddresses = $shippingAddresses
                ->map(fn ($address) => $this->formatShippingAddress($address))
                ->filter()
                ->values();

        $template = $this->compose('customer_welcome', (int) $user->tenant_id, array_merge($this->addressTokens($mailingAddresses), [
            'customer_name' => $user->full_name ?: 'Customer',
            'customer_email' => $user->email,
            'mailbox' => $this->displayMailbox($user->mailbox ?: optional($customer)->mailbox),
            'mailing_addresses' => $mailingAddresses->isNotEmpty()
                ? 'See your mailing address table below.'
                : 'Your mailing addresses are being prepared by the courier team.',
        ]), [
            'Mailbox' => $this->displayMailbox($user->mailbox ?: optional($customer)->mailbox),
            'Email' => $user->email,
        ]);

        if ($template) {
            $template['view']['mailingAddresses'] = $shippingAddresses
                ->map(fn ($address) => $this->shippingAddressViewData($address))
                ->values()
                ->all();
        }

        return $template;
    }

    public function packageCreated(Package $package): ?array
    {
        return $this->compose('package_created', (int) $package->tenant_id, $this->packageTokens($package), [
            'HAWB' => $package->tracking_number,
            'Supplier Tracking' => $package->supplier_tracking ?: '-',
            'Mailbox' => $this->displayMailbox($package->mailbox),
            'Status' => optional($package->status)->name ?: 'Received',
            'Weight / Volume' => $this->packageMeasureDisplay($package),
        ]);
    }

    public function packageStatusUpdated(Package $package, PackageLog $log): ?array
    {
        $log->loadMissing('status');
        $tokens = array_merge($this->packageTokens($package), [
            'status' => optional($log->status)->name ?: optional($package->status)->name,
            'pickup_location' => $log->location ?: optional($package->outlet)->name ?: '-',
            'note' => $log->note ?: '-',
        ]);

        return $this->compose($this->packageStatusTemplateKey($package, $log), (int) $package->tenant_id, $tokens, [
            'HAWB' => $package->tracking_number,
            'Supplier Tracking' => $package->supplier_tracking ?: '-',
            'Status' => optional($log->status)->name ?: optional($package->status)->name,
            'Pickup Location' => $log->location ?: '-',
            'Merchant' => $package->merchant ?: '-',
            'Weight / Volume' => $this->packageMeasureDisplay($package),
            'Note' => $log->note ?: '-',
        ]);
    }

    public function invoiceCreated(Invoice $invoice): ?array
    {
        return $this->compose('invoice_created', (int) $invoice->tenant_id, [
            'customer_name' => optional(optional($invoice->customer)->user)->full_name ?: 'Customer',
            'mailbox' => $invoice->mailbox,
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => 'JMD '.number_format((float) $invoice->total_jmd, 2),
            'payment_status' => ucfirst($invoice->payment_status),
            'due_date' => optional($invoice->due_date)->format('M d, Y') ?: '-',
        ], [
            'Invoice' => $invoice->invoice_number,
            'Mailbox' => $invoice->mailbox,
            'Total' => 'JMD '.number_format((float) $invoice->total_jmd, 2),
            'Payment Status' => ucfirst($invoice->payment_status),
            'Due Date' => optional($invoice->due_date)->format('M d, Y') ?: '-',
        ]);
    }

    public function agingReminder(Collection $packages, ?int $minimumAge = null): ?array
    {
        $first = $packages->first();
        if (! $first) {
            return null;
        }

        $packages->each->loadMissing(['status']);
        $packageLines = $packages
            ->map(function (Package $package) {
                return collect([
                    $package->tracking_number,
                    optional($package->status)->name ?: 'Available',
                    optional($package->date_received)->format('M d, Y') ?: null,
                ])->filter()->implode(' - ');
            })
            ->filter()
            ->values();

        return $this->compose('aging_reminder', (int) $first->tenant_id, [
            'customer_name' => optional(optional($first->customer)->user)->full_name ?: 'Customer',
            'mailbox' => $first->mailbox,
            'package_count' => (string) $packages->count(),
            'tracking_number' => $packages->pluck('tracking_number')->filter()->implode(', '),
            'package_list' => $packageLines->implode("\n"),
        ], [
            'Mailbox' => $first->mailbox,
            'Packages Awaiting Pickup' => (string) $packages->count(),
            'Tracking Numbers' => $packages->pluck('tracking_number')->filter()->implode(', '),
            'Package Details' => $packageLines->implode("\n"),
        ]);
    }

    public function deliveryStatusUpdated(DeliveryRequest $deliveryRequest, string $previousStatus): ?array
    {
        return $this->compose('delivery_status_updated', (int) $deliveryRequest->tenant_id, [
            'customer_name' => optional(optional($deliveryRequest->customer)->user)->full_name ?: 'Customer',
            'mailbox' => $this->displayMailbox($deliveryRequest->mailbox),
            'request_number' => $deliveryRequest->request_number,
            'previous_status' => ucfirst(str_replace('_', ' ', $previousStatus)),
            'status' => ucfirst(str_replace('_', ' ', $deliveryRequest->status)),
        ], [
            'Request' => $deliveryRequest->request_number,
            'Mailbox' => $this->displayMailbox($deliveryRequest->mailbox),
            'Previous Status' => ucfirst(str_replace('_', ' ', $previousStatus)),
            'Current Status' => ucfirst(str_replace('_', ' ', $deliveryRequest->status)),
        ]);
    }

    public function passwordReset(User $user, string $resetUrl): ?array
    {
        $tenantName = optional($user->tenant)->company_name ?: config('app.name');

        return $this->compose('password_reset', (int) $user->tenant_id, [
            'customer_name' => $user->full_name ?: 'there',
            'customer_email' => $user->email,
            'reset_url' => $resetUrl,
            'expires_minutes' => (string) config('auth.passwords.users.expire', 60),
        ], [
            'Account' => $user->email,
            'Courier' => $tenantName,
            'Expires' => config('auth.passwords.users.expire', 60).' minutes',
        ]);
    }

    public function defaultTemplates(): array
    {
        return [
            'customer_welcome' => [
                'label' => 'Customer Welcome',
                'enabled' => true,
                'subject' => 'Welcome to {{ courier_name }} - Your mailbox is {{ mailbox }}',
                'headline' => 'Welcome to {{ courier_name }}',
                'body' => "Dear {{ customer_name }},\n\nWelcome to {{ courier_name }}. Your account has been created successfully.\n\nYour mailbox number is {{ mailbox }}.\n\nYour mailing addresses:\n{{ mailing_addresses }}\n\nPlease include your mailbox number on every package so we can identify your shipment quickly.\n\nYou can now receive packages, track shipments, view invoices, and request delivery from your customer portal.",
                'button_label' => 'Open Customer Portal',
                'button_url' => '',
            ],
            'package_created' => [
                'label' => 'Package Received',
                'enabled' => true,
                'subject' => 'Package received: {{ tracking_number }}',
                'headline' => 'Your package has been received',
                'body' => "Dear {{ customer_name }},\n\n{{ courier_name }} has received a package for mailbox {{ mailbox }}.\n\nHAWB: {{ tracking_number }}\nSupplier Tracking: {{ supplier_tracking }}\nStatus: {{ status }}\nWeight/Volume: {{ weight }}\n\nYou may log in to your dashboard to view the latest update.",
                'button_label' => '',
                'button_url' => '',
            ],
            'package_status_updated' => [
                'label' => 'Package Status Change',
                'enabled' => true,
                'subject' => 'Package status updated: {{ tracking_number }}',
                'headline' => 'Your package status changed',
                'body' => "Dear {{ customer_name }},\n\nPlease be advised that your package with HAWB {{ tracking_number }} has changed to {{ status }}.\n\nSupplier Tracking: {{ supplier_tracking }}\nPickup Location: {{ pickup_location }}\nNote: {{ note }}\n\nYou may log in to your dashboard to view the latest update.",
                'button_label' => '',
                'button_url' => '',
            ],
            'ready_for_pickup_delivery' => [
                'label' => 'Ready for Pickup/Delivery',
                'enabled' => true,
                'subject' => 'Package for {{ customer_name }}: Ready for Pickup/Delivery',
                'headline' => 'Your packages are processed and ready',
                'body' => "Dear {{ customer_name }},\n\nYour package is processed and ready. See details below.\n\nTracking Number: {{ tracking_number }}\nMerchant: {{ merchant }}\nWeight/Volume: {{ weight }}\n\nDelivery is available from your current pickup branch. Log in to your account to request delivery.",
                'button_label' => '',
                'button_url' => '',
            ],
            'package_picked_up' => [
                'label' => 'Package Picked Up',
                'enabled' => true,
                'subject' => 'Package Collected Successfully',
                'headline' => 'Your Package Has Been Picked Up',
                'body' => "Dear {{ customer_name }},\n\nYour package listed below has been picked up at {{ pickup_location }}. See the attached invoice for your record.\n\nTracking Number: {{ tracking_number }}\nMerchant: {{ merchant }}\nWeight/Volume: {{ weight }}\n\nThank you for shipping with {{ courier_name }}.",
                'button_label' => '',
                'button_url' => '',
            ],
            'package_event_issue' => [
                'label' => 'Package Event/Issue',
                'enabled' => true,
                'subject' => 'Important Information Regarding Package {{ tracking_number }}',
                'headline' => 'Your Package Is Delayed',
                'body' => "Dear {{ customer_name }},\n\nWe've received your package, but we need additional information before processing can continue.\n\nTracking Number: {{ tracking_number }}\nMerchant: {{ merchant }}\nIssue: {{ note }}\n\nPlease upload the required invoice or supporting document in your dashboard so we can move the package forward.",
                'button_label' => '',
                'button_url' => '',
            ],
            'invoice_created' => [
                'label' => 'Invoice Created',
                'enabled' => true,
                'subject' => 'Invoice created: {{ invoice_number }}',
                'headline' => 'A new invoice is available',
                'body' => "Hello {{ customer_name }},\n\n{{ courier_name }} has created invoice {{ invoice_number }} for {{ invoice_total }}. Payment status: {{ payment_status }}.",
                'button_label' => '',
                'button_url' => '',
            ],
            'aging_reminder' => [
                'label' => 'Package Pickup Reminder',
                'enabled' => true,
                'subject' => 'Package pickup reminder',
                'headline' => 'Packages are waiting for pickup',
                'body' => "Hello {{ customer_name }},\n\nYou have {{ package_count }} package(s) awaiting pickup or delivery at {{ courier_name }}.\n\nSelected package(s):\n{{ package_list }}\n\nPlease log in to your customer portal or contact us if you need help arranging pickup or delivery.",
                'button_label' => 'Open Customer Portal',
                'button_url' => '{{ portal_url }}',
            ],
            'delivery_status_updated' => [
                'label' => 'Delivery Status Change',
                'enabled' => true,
                'subject' => 'Delivery request updated: {{ request_number }}',
                'headline' => 'Your delivery request was updated',
                'body' => "Dear {{ customer_name }},\n\nDelivery request {{ request_number }} changed from {{ previous_status }} to {{ status }}.\n\nMailbox: {{ mailbox }}",
                'button_label' => '',
                'button_url' => '',
            ],
            'password_reset' => [
                'label' => 'Password Reset',
                'enabled' => true,
                'subject' => 'Reset your {{ courier_name }} password',
                'headline' => 'Reset your password',
                'body' => "Dear {{ customer_name }},\n\nWe received a request to reset the password for {{ customer_email }}.\n\nUse the button below to set a new password. This link expires in {{ expires_minutes }} minutes.\n\nIf you did not request this, you can ignore this email.",
                'button_label' => 'Reset Password',
                'button_url' => '{{ reset_url }}',
            ],
        ];
    }

    private function packageTokens(Package $package): array
    {
        return [
            'customer_name' => optional(optional($package->customer)->user)->full_name ?: 'Customer',
            'mailbox' => $this->displayMailbox($package->mailbox),
            'tracking_number' => $package->tracking_number,
            'hawb' => $package->tracking_number,
            'supplier_tracking' => $package->supplier_tracking ?: '-',
            'merchant' => $package->merchant ?: '-',
            'status' => optional($package->status)->name ?: 'Received',
            'weight' => $this->packageMeasureDisplay($package),
            'cost' => '',
            'invoice_total' => '',
            'pickup_location' => optional($package->outlet)->name ?: '-',
            'note' => '',
        ];
    }

    private function isPackageEmailTemplate(string $key): bool
    {
        return in_array($key, [
            'package_created',
            'package_status_updated',
            'ready_for_pickup_delivery',
            'package_picked_up',
            'package_event_issue',
            'aging_reminder',
        ], true);
    }

    private function packageMeasureDisplay(Package $package): string
    {
        if (method_exists($package, 'measureDisplay')) {
            return $package->measureDisplay();
        }

        if (strtolower((string) $package->freight_type) === 'sea') {
            return $package->seaVolumeDisplay();
        }

        return number_format((float) $package->weight, 2).' lb';
    }

    private function removePackageCostLines(string $body): string
    {
        $lines = preg_split('/\R/', $body) ?: [];

        return collect($lines)
            ->reject(fn ($line) => preg_match('/^\s*(cost|total|total due|estimated total|package details)\s*:/i', $line))
            ->implode("\n");
    }

    private function removePackageCostHtml(string $bodyHtml): string
    {
        $pattern = '#<(p|div|span|li|tr)\b[^>]*>\s*(?:<[^>]+>\s*)*(cost|total|total due|estimated total|package details)\s*:.*?</\1>#is';

        return preg_replace($pattern, '', $bodyHtml) ?? $bodyHtml;
    }

    private function baseTokens(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        return [
            'courier_name' => optional($tenant)->company_name ?: config('app.name'),
            'courier_email' => optional($tenant)->email,
            'courier_phone' => optional($tenant)->phone,
            'company_name' => optional($tenant)->company_name ?: config('app.name'),
            'portal_url' => optional($tenant)->portalUrl() ?: url('/'),
            'branch_name' => '',
            'branch_address' => '',
            'pickup_location' => '',
            'usa_air_address' => '',
            'usa_sea_address' => '',
            'china_air_address' => '',
            'china_sea_address' => '',
            'customer_email' => '',
            'supplier_tracking' => '',
            'merchant' => '',
            'hawb' => '',
            'weight' => '',
            'cost' => '',
            'invoice_total' => '',
            'package_list' => '',
            'note' => '',
            'reset_url' => '',
            'expires_minutes' => '',
        ];
    }

    private function packageStatusTemplateKey(Package $package, PackageLog $log): string
    {
        $status = strtolower((string) (optional($log->status)->name ?: optional($package->status)->name));
        $slug = strtolower((string) optional($log->status)->slug);
        $note = strtolower((string) $log->note);
        $text = trim($status.' '.$slug.' '.$note);

        if (Str::contains($text, ['invoice needed', 'missing invoice', 'invoice required', 'issue', 'problem', 'delay', 'delayed', 'hold'])) {
            return 'package_event_issue';
        }

        if (Str::contains($text, ['picked up', 'picked-up', 'picked_up', 'collected', 'delivered'])) {
            return 'package_picked_up';
        }

        if (Str::contains($text, ['ready', 'available', 'pickup', 'pick up'])) {
            return 'ready_for_pickup_delivery';
        }

        return 'package_status_updated';
    }

    private function addressTokens(Collection $addresses): array
    {
        $tokens = [
            'usa_air_address' => '',
            'usa_sea_address' => '',
            'china_air_address' => '',
            'china_sea_address' => '',
        ];

        foreach ($addresses as $address) {
            $lower = strtolower($address);
            $key = null;
            if (Str::contains($lower, ['usa air', 'us air'])) {
                $key = 'usa_air_address';
            } elseif (Str::contains($lower, ['usa sea', 'us sea'])) {
                $key = 'usa_sea_address';
            } elseif (Str::contains($lower, 'china air')) {
                $key = 'china_air_address';
            } elseif (Str::contains($lower, 'china sea')) {
                $key = 'china_sea_address';
            }

            if ($key) {
                $tokens[$key] = $address;
            }
        }

        return $tokens;
    }

    private function displayMailbox(?string $mailbox): string
    {
        $mailbox = strtoupper(trim((string) $mailbox));
        if ($mailbox === '') {
            return '';
        }
        if (Str::startsWith($mailbox, 'OL-')) {
            return $mailbox;
        }
        if (preg_match('/^([A-Z]+)(\d+)$/', $mailbox, $parts)) {
            return 'OL-'.$parts[1].str_pad($parts[2], max(5, strlen($parts[2])), '0', STR_PAD_LEFT);
        }

        return 'OL-'.$mailbox;
    }

    private function formatShippingAddress($address): string
    {
        $isChinaAddress = $this->isChinaShippingAddress($address);

        $lines = collect([
            trim((string) $address->label),
            trim((string) $address->name),
            filled($address->marks) ? 'Marks: '.$address->marks : null,
            trim((string) $address->address_1),
            $isChinaAddress ? null : trim((string) $address->address_2),
            filled($address->entrance_no) ? 'Entrance No.: '.$address->entrance_no : null,
            collect([$address->city, $address->state, $address->province, $address->zip_code])->filter()->implode(', '),
            trim((string) $address->country),
            trim((string) $address->detailed_address),
            trim((string) $address->number),
            trim((string) $address->note),
        ])->filter()->values();

        return $lines->implode("\n");
    }

    private function shippingAddressViewData($address): array
    {
        $isChinaAddress = $this->isChinaShippingAddress($address);

        $shipmentLabels = [
            'us_air' => 'USA Air Freight',
            'usa_air' => 'USA Air Freight',
            'us_sea' => 'USA Sea Freight',
            'usa_sea' => 'USA Sea Freight',
            'china_air' => 'China Air Freight',
            'china_sea' => 'China Sea Freight',
        ];

        return [
            'label' => $shipmentLabels[strtolower(trim((string) $address->shipment_type))]
                ?? (trim((string) $address->label) ?: 'Warehouse Address'),
            'country_code' => strtoupper(trim((string) $address->country_code)),
            'lines' => collect([
                filled($address->name) ? ['label' => 'Name', 'value' => trim((string) $address->name)] : null,
                filled($address->marks) ? ['label' => 'Marks', 'value' => trim((string) $address->marks)] : null,
                filled($address->address_1) ? ['label' => 'Address', 'value' => trim((string) $address->address_1)] : null,
                ! $isChinaAddress && filled($address->address_2) ? ['label' => 'Address 2', 'value' => trim((string) $address->address_2)] : null,
                filled($address->entrance_no) ? ['label' => 'Entrance No.', 'value' => trim((string) $address->entrance_no)] : null,
                filled($address->country) ? ['label' => 'Country', 'value' => trim((string) $address->country)] : null,
                filled($address->province) ? ['label' => 'Province', 'value' => trim((string) $address->province)] : null,
                filled($address->city) ? ['label' => 'City', 'value' => trim((string) $address->city)] : null,
                filled($address->state) ? ['label' => 'State', 'value' => trim((string) $address->state)] : null,
                filled($address->zip_code) ? ['label' => 'Zip Code', 'value' => trim((string) $address->zip_code)] : null,
                filled($address->detailed_address) ? ['label' => 'Detailed Address', 'value' => trim((string) $address->detailed_address)] : null,
                filled($address->number) ? ['label' => 'Number', 'value' => trim((string) $address->number)] : null,
                filled($address->note) ? ['label' => 'Note', 'value' => trim((string) $address->note)] : null,
            ])->filter()->values()->all(),
        ];
    }

    private function render(string $text, array $tokens): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalizedTokens = collect($tokens)
            ->mapWithKeys(fn ($value, $key) => [Str::lower((string) $key) => $value])
            ->all();

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function ($matches) use ($normalizedTokens) {
            $key = Str::lower($matches[1]);

            return array_key_exists($key, $normalizedTokens)
                ? (string) $normalizedTokens[$key]
                : $matches[0];
        }, $text) ?? $text;
    }

    private function bodyHtml(string $body): string
    {
        if (Str::contains($body, ['<p', '<div', '<br', '<table', '<ul', '<ol', '<strong', '<b', '<em', '<i'])) {
            return $this->sanitizeHtml($body);
        }

        return nl2br(e($body));
    }

    private function sanitizeHtml(string $body): string
    {
        $body = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $body);
        $body = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $body);
        $body = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '$1="#"', $body);

        return strip_tags($body, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><div><span><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><td><th><hr>');
    }

    private function isChinaShippingAddress($address): bool
    {
        return strtoupper(trim((string) $address->country_code)) === 'CN'
            || strcasecmp(trim((string) $address->country), 'China') === 0;
    }
}
