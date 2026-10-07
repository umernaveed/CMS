<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'day_of_week',
        'start_time',
        'end_time',
        'max_requests',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function area()
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }
}
