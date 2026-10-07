<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsPackage extends Model
{
    use HasFactory;

    private const CBM_TO_CUFT = 35.3147;

    protected $fillable = [
        'shipment_id',
        'manifest_id',
        'tenant_id',
        'courier_package_id',
        'tracking_number',
        'description',
        'weight',
        'weight_unit',
        'rate',
        'warehouse_fee',
        'service_fee_type',
        'service_fee_value',
        'service_fee',
        'freight_rate',
        'freight_weight',
        'freight_unit',
        'freight_charge',
        'amount_usd',
        'exchange_rate_jmd',
        'amount',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'checked_out_by',
        'location_status',
        'location_notes',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'rate' => 'decimal:2',
        'warehouse_fee' => 'decimal:2',
        'service_fee_value' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'freight_rate' => 'decimal:2',
        'freight_weight' => 'decimal:4',
        'freight_charge' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'exchange_rate_jmd' => 'decimal:4',
        'amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function manifest()
    {
        return $this->belongsTo(SuperadminLogisticsManifest::class, 'manifest_id');
    }

    public function shipment()
    {
        return $this->belongsTo(SuperadminLogisticsShipment::class, 'shipment_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courierPackage()
    {
        return $this->belongsTo(Package::class, 'courier_package_id');
    }

    public function invoiceItems()
    {
        return $this->hasMany(SuperadminLogisticsInvoiceItem::class, 'package_id');
    }

    public function isSeaFreight(): bool
    {
        $unit = strtolower((string) $this->weight_unit);

        return in_array($unit, ['cuft', 'cbm'], true)
            || strtolower((string) optional($this->shipment)->shipment_type) === 'sea'
            || strtolower((string) optional($this->courierPackage)->freight_type) === 'sea';
    }

    public function measureDisplay(): string
    {
        $measure = (float) $this->weight;
        $unit = strtolower((string) ($this->weight_unit ?: 'lb'));

        if ($this->isSeaFreight()) {
            $cuft = $unit === 'cbm' ? $measure * self::CBM_TO_CUFT : $measure;
            $cbm = $unit === 'cbm' ? $measure : ($cuft > 0 ? $cuft / self::CBM_TO_CUFT : 0);

            return number_format($cuft, 2).' cu.ft / '.number_format($cbm, 3).' CBM';
        }

        return number_format($measure, 2).' '.$unit;
    }
}

