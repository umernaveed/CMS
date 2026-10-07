<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTechAccessCode extends Model
{
    protected $fillable = [
        'created_by',
        'code_hash',
        'label',
        'starts_at',
        'ends_at',
        'revoked_at',
        'revoked_by',
        'last_used_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        $today = now()->toDateString();

        return ! $this->revoked_at
            && (! $this->starts_at || $this->starts_at->toDateString() <= $today)
            && (! $this->ends_at || $this->ends_at->toDateString() >= $today);
    }
}
