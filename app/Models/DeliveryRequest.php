<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'outlet_id',
        'area_id',
        'request_number',
        'mailbox',
        'package_ids',
        'package_count',
        'package_total',
        'pickup_location',
        'delivery_cost',
        'delivery_type',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'destination_lat',
        'destination_lng',
        'preferred_date',
        'preferred_time_slot_id',
        'special_instructions',
        'status',
        'is_paid',
        'assigned_to',
        'approved_by',
        'approved_at',
        'delivered_at',
    ];

    protected $casts = [
        'package_ids' => 'array',
        'preferred_date' => 'date',
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'is_paid' => 'boolean',
        'package_total' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',
    ];

    public function setMailboxAttribute($value): void
    {
        $this->attributes['mailbox'] = Customer::normalizeMailbox($value);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function area()
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }

    public function timeSlot()
    {
        return $this->belongsTo(DeliveryTimeSlot::class, 'preferred_time_slot_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
