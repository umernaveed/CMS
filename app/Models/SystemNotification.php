<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SystemNotification extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'message',
        'attachments_json',
        'priority',
        'audience',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'attachments_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function dismissals()
    {
        return $this->hasMany(NotificationDismissal::class);
    }

    public static function sanitizeMessage(?string $message): string
    {
        $message = html_entity_decode((string) $message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $message);
        $message = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $message);
        $message = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '$1="#"', $message);

        return strip_tags($message, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><div><span><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><td><th><hr>');
    }

    public function messageHtml(): string
    {
        return self::sanitizeMessage($this->message);
    }

    public function messagePreview(int $limit = 110): string
    {
        $message = trim(preg_replace('/\s+/', ' ', strip_tags($this->messageHtml())));

        return Str::limit($message, $limit);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActiveNow($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeVisibleToUser($query, User $user)
    {
        if ($user->user_type === 'super_admin') {
            return $query
                ->whereNull('tenant_id')
                ->whereIn('audience', ['super_admin', 'all']);
        }

        if (in_array($user->user_type, ['tenant_admin', 'staff'], true)) {
            return $query->where(function ($query) use ($user) {
                $query
                    ->where(function ($global) {
                        $global->whereNull('tenant_id')
                            ->whereIn('audience', ['platform_users', 'courier']);
                    })
                    ->orWhere(function ($tenant) use ($user) {
                        $tenant->where('tenant_id', $user->tenant_id)
                            ->whereIn('audience', ['all', 'platform_users', 'courier']);
                    });
            });
        }

        if ($user->user_type === 'customer') {
            return $query->where(function ($query) use ($user) {
                $query
                    ->where(function ($global) {
                        $global->whereNull('tenant_id')
                            ->whereIn('audience', ['platform_users', 'customer']);
                    })
                    ->orWhere(function ($tenant) use ($user) {
                        $tenant->where('tenant_id', $user->tenant_id)
                            ->whereIn('audience', ['all', 'platform_users', 'customer']);
                    });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function canBeSeenBy(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->activeNow()
            ->visibleToUser($user)
            ->exists();
    }
}
