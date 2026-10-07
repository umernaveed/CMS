<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'outlet_id',
        'package_id',
        'purchase_request_number',
        'supplier_tracking',
        'product_url',
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
        return $this->hasMany(PurchaseRequestReceipt::class);
    }

    public static function statuses(): array
    {
        return [
            'submitted' => 'Submitted',
            'in_process' => 'In Process',
            'ordered' => 'Ordered',
            'completed' => 'Completed',
            'payment_applied' => 'Payment Applied',
            'approved' => 'Approved',
        ];
    }

    public static function statusClasses(): array
    {
        return [
            'submitted' => 'bg-secondary',
            'in_process' => 'bg-warning text-dark',
            'ordered' => 'bg-primary',
            'completed' => 'bg-success',
            'payment_applied' => 'bg-info text-dark',
            'approved' => 'bg-success',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function statusBadgeClass(): string
    {
        return self::statusClasses()[$this->status] ?? 'bg-primary';
    }
}
