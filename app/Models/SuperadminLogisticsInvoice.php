<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'manifest_id',
        'tenant_id',
        'invoice_number',
        'billing_scope',
        'subtotal',
        'discount_jmd',
        'total',
        'amount_paid_jmd',
        'balance_jmd',
        'status',
        'paid_at',
        'created_by',
        'paid_by',
        'receipt_path',
        'receipt_original_name',
        'supplier_invoice_path',
        'supplier_invoice_original_name',
        'voided_at',
        'voided_by',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_jmd' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid_jmd' => 'decimal:2',
        'balance_jmd' => 'decimal:2',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function manifest()
    {
        return $this->belongsTo(SuperadminLogisticsManifest::class, 'manifest_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items()
    {
        return $this->hasMany(SuperadminLogisticsInvoiceItem::class, 'invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(SuperadminLogisticsInvoicePayment::class, 'invoice_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'void' || $this->voided_at !== null;
    }
}
