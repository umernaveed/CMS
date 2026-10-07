<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User as UserModel;
use Illuminate\Support\Str;

class MailboxService
{
    public function nextForTenant(Tenant $tenant): string
    {
        $prefix = $this->prefix($tenant);
        [$number, $width] = $this->nextSequence($tenant, $prefix);

        do {
            $mailbox = $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
            $number++;
        } while ($this->mailboxExists($tenant->id, $mailbox));

        return $mailbox;
    }

    public function generate(Tenant $tenant): string
    {
        return $this->nextForTenant($tenant);
    }

    public function prefix(Tenant $tenant): string
    {
        $configured = TenantSetting::getValue($tenant->id, 'mailbox_prefix');
        if ($configured) {
            return Customer::normalizeMailbox($configured) ?: 'COU';
        }

        return strtoupper(substr(preg_replace('/[^A-Z]/i', '', $tenant->company_name), 0, 3)) ?: 'COU';
    }

    private function nextSequence(Tenant $tenant, string $prefix): array
    {
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/i';
        $highest = 0;
        $width = 4;

        Tenant::whereKey($tenant->id)->lockForUpdate()->first();

        collect()
            ->merge(Customer::where('tenant_id', $tenant->id)->where('mailbox', 'like', $prefix.'%')->pluck('mailbox'))
            ->merge(UserModel::where('tenant_id', $tenant->id)->where('mailbox', 'like', $prefix.'%')->pluck('mailbox'))
            ->each(function ($mailbox) use ($pattern, &$highest, &$width) {
                if (! preg_match($pattern, (string) $mailbox, $matches)) {
                    return;
                }

                $number = (int) $matches[1];
                if ($number > $highest) {
                    $highest = $number;
                    $width = max(1, strlen($matches[1]));
                } elseif ($number === $highest) {
                    $width = min($width, max(1, strlen($matches[1])));
                }
            });

        return [$highest + 1, $width];
    }

    private function mailboxExists(int $tenantId, string $mailbox): bool
    {
        return Customer::where('tenant_id', $tenantId)->whereNormalizedMailbox($mailbox)->exists()
            || UserModel::where('tenant_id', $tenantId)->where('mailbox', Customer::normalizeMailbox($mailbox))->exists();
    }
}

