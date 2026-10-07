<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'package_id',
        'tracking_number',
        'description',
        'freight_type',
        'weight',
        'billable_weight',
        'measure_unit',
        'rate_region',
        'package_price_usd',
        'package_price_jmd',
        'rate_jmd',
        'service_fee_jmd',
        'customs_fee_jmd',
        'storage_fee_jmd',
        'processing_fee_jmd',
        'unknown_fee_jmd',
        'unknown_fee_reason',
        'total_jmd',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'billable_weight' => 'decimal:2',
        'package_price_usd' => 'decimal:2',
        'package_price_jmd' => 'decimal:2',
        'rate_jmd' => 'decimal:2',
        'service_fee_jmd' => 'decimal:2',
        'customs_fee_jmd' => 'decimal:2',
        'storage_fee_jmd' => 'decimal:2',
        'processing_fee_jmd' => 'decimal:2',
        'unknown_fee_jmd' => 'decimal:2',
        'total_jmd' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
