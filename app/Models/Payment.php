<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'customer_id',
        'outlet_id',
        'payment_number',
        'amount',
        'amount_received',
        'change_due',
        'change_to_wallet',
        'points_redeemed',
        'points_value_jmd',
        'point_value_jmd',
        'payment_method_id',
        'reference_number',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime_type',
        'notes',
        'status',
        'received_by',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_due' => 'decimal:2',
        'change_to_wallet' => 'boolean',
        'points_redeemed' => 'decimal:2',
        'points_value_jmd' => 'decimal:2',
        'point_value_jmd' => 'decimal:4',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
