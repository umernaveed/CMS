<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HsCode extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'description',
        'duty_rate',
        'is_active',
    ];

    protected $casts = [
        'duty_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
