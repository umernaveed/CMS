<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'min_packages_collected',
        'discount_percent',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
