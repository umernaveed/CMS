<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'transaction_type',
        'source_type',
        'reference_id',
        'amount',
        'converted_to_points',
        'points_awarded',
        'point_value_jmd',
        'balance_before',
        'balance_after',
        'description',
        'status',
        'payment_method',
        'reference_number',
        'receipt_path',
        'receipt_original_name',
        'approved_by',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'converted_to_points' => 'boolean',
        'points_awarded' => 'decimal:2',
        'point_value_jmd' => 'decimal:4',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
