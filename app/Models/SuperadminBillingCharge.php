<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminBillingCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'amount_usd',
        'amount_jmd',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_jmd' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
