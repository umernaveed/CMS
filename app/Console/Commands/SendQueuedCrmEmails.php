<?php

namespace App\Console\Commands;

use App\Mail\ClientBroadcastMail;
use App\Models\ClientNotification;
use App\Models\CrmEmailDraft;
use App\Models\CrmEmailQueueItem;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\SystemSetting;
use App\Services\TenantMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendQueuedCrmEmails extends Command
{
    protected $signature = 'crm:send-bulk-emails {--limit=50 : Maximum emails to send in this run}';

    protected $description = 'Send queued CRM marketing emails in small cron-safe batches.';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $now = now();
        $sent = 0;
        $failed = 0;

        SystemSetting::updateOrCreate(
            ['key' => 'crm_bulk_email_cron_last_run_at'],
            ['value' => $now->toDateTimeString(), 'group' => 'crm']
        );

        CrmEmailQueueItem::where('status', 'processing')
            ->where('processing_at', '<', $now->copy()->subMinutes(15))
            ->update([
                'status' => 'failed',
                'error_message' => 'Marked failed because this queue item stayed processing too long. It was not retried automatically to prevent duplicate emails.',
                'processing_at' => null,
            ]);

        $itemIds = CrmEmailQueueItem::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($itemIds as $itemId) {
            $claimed = CrmEmailQueueItem::whereKey($itemId)
                ->where('status', 'pending')
                ->update(['status' => 'processing', 'processing_at' => now(), 'error_message' => null]);

            if (! $claimed) {
                continue;
            }

            $item = CrmEmailQueueItem::with(['draft', 'customer.user', 'customer.tenant', 'customer.outlet', 'user'])->find($itemId);

            if (! $item) {
                continue;
            }

            try {
                if (! $item->draft || ! $item->customer || ! $item->user || ! $item->to_email) {
                    throw new \RuntimeException('Recipient, customer, or draft is no longer available.');
                }

                $subject = $this->renderTokens((string) $item->draft->subject, $item->customer);
                $body = $this->renderTokens((string) $item->draft->body, $item->customer);

                app(TenantMailService::class)->send(
                    (int) $item->tenant_id,
                    $item->to_email,
                    new ClientBroadcastMail($subject, $body, $item->draft->attachments_json ?? [])
                );

                EmailLog::create([
                    'tenant_id' => $item->tenant_id,
                    'to_email' => $item->to_email,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_at' => now(),
                ]);

                if (! ClientNotification::where('tenant_id', $item->tenant_id)
                    ->where('user_id', $item->user_id)
                    ->where('type', 'crm_email')
                    ->where('data->crm_email_queue_item_id', $item->id)
                    ->exists()) {
                    ClientNotification::create([
                        'tenant_id' => $item->tenant_id,
                        'user_id' => $item->user_id,
                        'type' => 'crm_email',
                        'title' => $subject,
                        'message' => $body,
                        'data' => [
                            'crm_email_queue_item_id' => $item->id,
                            'crm_email_draft_id' => $item->crm_email_draft_id,
                            'customer_id' => $item->customer_id,
                            'email_status' => 'sent',
                        ],
                        'created_at' => now(),
                    ]);
                }

                $item->update(['status' => 'sent', 'sent_at' => now(), 'processing_at' => null]);
                $sent++;
            } catch (\Throwable $exception) {
                $message = mb_substr($exception->getMessage(), 0, 2000);

                EmailLog::create([
                    'tenant_id' => $item->tenant_id,
                    'to_email' => $item->to_email,
                    'subject' => optional($item->draft)->subject ?: 'CRM email',
                    'body' => optional($item->draft)->body,
                    'status' => 'failed',
                    'error_message' => $message,
                    'sent_at' => now(),
                    'created_at' => now(),
                ]);

                $item->update(['status' => 'failed', 'error_message' => $message, 'processing_at' => null]);
                $failed++;

                Log::warning('Queued CRM email failed.', [
                    'queue_item_id' => $item->id,
                    'draft_id' => $item->crm_email_draft_id,
                    'tenant_id' => $item->tenant_id,
                    'to' => $item->to_email,
                    'error' => $message,
                ]);
            }

            if ($item->draft) {
                $this->refreshDraftCounts($item->draft);
            }
        }

        $pending = CrmEmailQueueItem::whereIn('status', ['pending', 'processing'])->count();
        $result = 'Sent '.$sent.', failed '.$failed.', pending '.$pending.'.';

        SystemSetting::updateOrCreate(
            ['key' => 'crm_bulk_email_cron_last_result'],
            ['value' => $result, 'group' => 'crm']
        );

        $this->info($result);

        return self::SUCCESS;
    }

    private function refreshDraftCounts(CrmEmailDraft $draft): void
    {
        $counts = CrmEmailQueueItem::where('crm_email_draft_id', $draft->id)
            ->selectRaw("SUM(status = 'sent') as sent_count, SUM(status = 'failed') as failed_count, COUNT(*) as total_count, SUM(status in ('pending', 'processing')) as open_count")
            ->first();

        $sent = (int) ($counts->sent_count ?? 0);
        $failed = (int) ($counts->failed_count ?? 0);
        $open = (int) ($counts->open_count ?? 0);
        $total = (int) ($counts->total_count ?? 0);

        $draft->forceFill([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'notification_count' => $sent,
            'status' => $total > 0 && $open === 0 ? 'sent' : $draft->status,
            'sent_at' => $total > 0 && $open === 0 ? now() : $draft->sent_at,
        ])->save();
    }

    private function renderTokens(string $content, Customer $customer): string
    {
        $customer->loadMissing(['user', 'tenant', 'outlet']);
        $tokens = [
            'company_name' => optional($customer->tenant)->company_name,
            'courier_name' => optional($customer->tenant)->company_name,
            'courier_email' => optional($customer->tenant)->email,
            'courier_phone' => optional($customer->tenant)->phone,
            'customer_name' => optional($customer->user)->full_name,
            'first_name' => optional($customer->user)->first_name,
            'last_name' => optional($customer->user)->last_name,
            'customer_email' => optional($customer->user)->email,
            'email' => optional($customer->user)->email,
            'phone' => optional($customer->user)->phone,
            'address' => trim(collect([optional($customer->user)->address_line1, optional($customer->user)->address_line2])->filter()->implode(', ')),
            'customer_address' => trim(collect([optional($customer->user)->address_line1, optional($customer->user)->address_line2])->filter()->implode(', ')),
            'city' => optional($customer->user)->city,
            'parish' => optional($customer->user)->parish,
            'mailbox' => $customer->mailbox,
            'branch_name' => optional($customer->outlet)->name,
            'branch_address' => optional($customer->outlet)->address,
            'pickup_location' => optional($customer->outlet)->name,
        ];

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn ($matches) => (string) ($tokens[$matches[1]] ?? $matches[0]), $content) ?? $content;
    }
}
