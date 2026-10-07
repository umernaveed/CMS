<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_number',
        'shipment_type',
        'region',
        'master_number',
        'cargo_company',
        'pieces',
        'departure_date',
        'arrival_date',
        'customs_office',
        'loading_port',
        'place_of_unloading',
        'shipping_agent_code',
        'asycuda_reference_number',
        'voyage_container_numbers',
        'exporter_name',
        'exporter_address',
        'documents',
        'awb_cost',
        'delivery_cost',
        'additional_cost',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'arrival_date' => 'date',
        'documents' => 'array',
        'awb_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'pieces' => 'integer',
    ];

    public function manifests()
    {
        return $this->hasMany(SuperadminLogisticsManifest::class, 'shipment_id');
    }

    public function packages()
    {
        return $this->hasMany(SuperadminLogisticsPackage::class, 'shipment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
