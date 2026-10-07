<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'package_id',
        'tracking_number',
        'weight',
        'weight_unit',
        'rate',
        'warehouse_fee',
        'service_fee',
        'freight_rate',
        'freight_weight',
        'freight_unit',
        'freight_charge',
        'amount_usd',
        'exchange_rate_jmd',
        'amount',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'rate' => 'decimal:2',
        'warehouse_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'freight_rate' => 'decimal:2',
        'freight_weight' => 'decimal:4',
        'freight_charge' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'exchange_rate_jmd' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(SuperadminLogisticsInvoice::class, 'invoice_id');
    }

    public function package()
    {
        return $this->belongsTo(SuperadminLogisticsPackage::class, 'package_id');
    }

    public function displayTrackingNumber(): ?string
    {
        return $this->firstFilled([
            $this->package?->courierPackage?->supplier_tracking,
            $this->package?->tracking_number,
            $this->tracking_number,
        ]);
    }

    public function hawbNumber(): ?string
    {
        $hawb = $this->package?->courierPackage?->tracking_number;
        $normalizedHawb = $this->normalizeTrackingForDisplay($hawb);

        if ($normalizedHawb === '') {
            return null;
        }

        $relatedTracking = $this->firstFilled([
            $this->package?->courierPackage?->supplier_tracking,
            $this->package?->tracking_number,
            $this->tracking_number,
        ]);

        if ($normalizedHawb === $this->normalizeTrackingForDisplay($relatedTracking)) {
            return null;
        }

        return $hawb;
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeTrackingForDisplay($value): string
    {
        return strtoupper(trim((string) $value));
    }
}
