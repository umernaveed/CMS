<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_type',
        'region',
        'min_weight',
        'max_weight',
        'minimum_billable_measure',
        'weight_unit',
        'rate_per_unit',
        'is_active',
    ];

    protected $casts = [
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'minimum_billable_measure' => 'decimal:2',
        'rate_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function minimumBillableMeasure(): float
    {
        $minimum = (float) ($this->minimum_billable_measure ?? 0);

        return $minimum > 0 ? $minimum : (float) $this->min_weight;
    }
}
