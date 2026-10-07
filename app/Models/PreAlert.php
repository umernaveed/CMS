<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreAlert extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'outlet_id',
        'package_id',
        'pre_alert_number',
        'supplier_tracking',
        'courier',
        'merchant',
        'description',
        'price_usd',
        'delivery_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function receipts()
    {
        return $this->hasMany(PreAlertReceipt::class);
    }
}
