<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeightRate extends Model
{
    use HasFactory;

    public const CBM_TO_CUFT = 35.3147;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'pricing_tier_id',
        'region',
        'volume_unit',
        'min_weight',
        'max_weight',
        'minimum_billable_measure',
        'processing_fee_jmd',
        'price_jmd',
        'express_price_jmd',
        'freight_type',
        'is_active',
    ];

    protected $casts = [
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'minimum_billable_measure' => 'decimal:2',
        'processing_fee_jmd' => 'decimal:2',
        'price_jmd' => 'decimal:2',
        'express_price_jmd' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function pricingTier()
    {
        return $this->belongsTo(PricingTier::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function freightCategory(): string
    {
        return ShippingMode::where('tenant_id', $this->tenant_id)
            ->where('code', $this->freight_type)
            ->value('category') ?: ($this->freight_type === 'sea' ? 'sea' : ($this->freight_type === 'local' ? 'local' : 'air'));
    }

    public function unitLabel(): string
    {
        if ($this->freightCategory() !== 'sea') {
            return 'lb';
        }

        return $this->volume_unit === 'cbm' ? 'CBM' : 'cu.ft';
    }

    public function priceFor(float $billableMeasure, string $deliveryType = 'pickup'): float
    {
        $rangePrice = $deliveryType === 'delivery'
            ? (float) ($this->express_price_jmd ?: $this->price_jmd)
            : (float) $this->price_jmd;
        $rangePrice = max($rangePrice, 0);
        $measure = $this->billableMeasure($billableMeasure);

        if ($this->freightCategory() === 'sea') {
            return round($rangePrice * $measure, 2);
        }

        if ($this->max_weight !== null && $measure > (float) $this->max_weight) {
            $rangeSize = max((float) $this->max_weight - (float) $this->min_weight, 1);
            $segments = 1 + (int) ceil(($measure - (float) $this->max_weight) / $rangeSize);

            return round($rangePrice * max($segments, 1), 2);
        }

        return round($rangePrice, 2);
    }

    public function billableMeasure(float $measure): float
    {
        if ($this->freightCategory() === 'sea' && $this->volume_unit === 'cbm') {
            $measure = $measure / self::CBM_TO_CUFT;
        }

        return max($measure, (float) $this->minimum_billable_measure);
    }

    public function unitRateFor(string $deliveryType = 'pickup'): float
    {
        return $deliveryType === 'delivery'
            ? (float) ($this->express_price_jmd ?: $this->price_jmd)
            : (float) $this->price_jmd;
    }
}

