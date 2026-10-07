<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'base_cost',
        'per_km_cost',
        'pricing_mode',
        'geofence_type',
        'center_lat',
        'center_lng',
        'radius_km',
        'geofence_coordinates',
        'is_active',
    ];

    protected $casts = [
        'base_cost' => 'decimal:2',
        'per_km_cost' => 'decimal:2',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'radius_km' => 'decimal:2',
        'geofence_coordinates' => 'array',
        'is_active' => 'boolean',
    ];

    public function timeSlots()
    {
        return $this->hasMany(DeliveryTimeSlot::class, 'area_id');
    }
}
