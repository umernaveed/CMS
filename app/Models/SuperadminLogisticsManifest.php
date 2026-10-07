<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsManifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'tenant_id',
        'manifest_number',
        'warehouse_reference',
        'cargo_company',
        'received_at',
        'status',
        'require_checkout_scan',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'require_checkout_scan' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment()
    {
        return $this->belongsTo(SuperadminLogisticsShipment::class, 'shipment_id');
    }

    public function packages()
    {
        return $this->hasMany(SuperadminLogisticsPackage::class, 'manifest_id');
    }

    public function invoices()
    {
        return $this->hasMany(SuperadminLogisticsInvoice::class, 'manifest_id');
    }
}

