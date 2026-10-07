<?php

namespace App\Services;

use App\Models\ClientNotification;
use App\Models\User;

class CourierNotificationService
{
    public function notify(
        int $tenantId,
        ?int $outletId,
        string $type,
        string $title,
        string $message,
        string $url,
        array $data = []
    ): void {
        User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('user_type', ['tenant_admin', 'staff'])
            ->when($outletId, function ($query) use ($outletId) {
                $query->where(function ($recipientQuery) use ($outletId) {
                    $recipientQuery->where('user_type', 'tenant_admin')
                        ->orWhereNull('outlet_id')
                        ->orWhere('outlet_id', $outletId);
                });
            })
            ->pluck('id')
            ->each(function ($userId) use ($tenantId, $type, $title, $message, $url, $data) {
                ClientNotification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => array_merge($data, ['url' => $url]),
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            });
    }

    public function markRead(string $type, array $data = []): void
    {
        $query = ClientNotification::where('user_id', auth()->id())
            ->where('type', $type)
            ->where('is_read', false);

        foreach ($data as $key => $value) {
            $query->where('data->'.$key, $value);
        }

        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
