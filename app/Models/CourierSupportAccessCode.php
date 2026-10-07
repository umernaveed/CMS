<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierSupportAccessCode extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'code_hash',
        'label',
        'permissions',
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by',
        'active_user_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at
            && ! $this->used_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
