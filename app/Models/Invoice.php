<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'outlet_id',
        'invoice_number',
        'mailbox',
        'subtotal_usd',
        'subtotal_jmd',
        'service_fee_usd',
        'service_fee_jmd',
        'customs_fee_usd',
        'customs_fee_jmd',
        'storage_fee_jmd',
        'processing_fee_jmd',
        'gct_amount_jmd',
        'discount_amount_jmd',
        'discount_type',
        'discount_value',
        'billing_weight_mode',
        'require_handoff_verification',
        'total_jmd',
        'payment_status',
        'invoice_status',
        'pdf_path',
        'email_sent',
        'added_by',
        'updated_by',
        'issued_by',
        'cancelled_by',
        'voided_by',
        'paid_by',
        'paid_at',
        'due_date',
        'notes',
        'verified_package_ids',
        'verified_by',
        'verified_at',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'subtotal_usd' => 'decimal:2',
        'subtotal_jmd' => 'decimal:2',
        'service_fee_usd' => 'decimal:2',
        'service_fee_jmd' => 'decimal:2',
        'customs_fee_usd' => 'decimal:2',
        'customs_fee_jmd' => 'decimal:2',
        'storage_fee_jmd' => 'decimal:2',
        'processing_fee_jmd' => 'decimal:2',
        'gct_amount_jmd' => 'decimal:2',
        'discount_amount_jmd' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'total_jmd' => 'decimal:2',
        'email_sent' => 'boolean',
        'require_handoff_verification' => 'boolean',
        'paid_at' => 'datetime',
        'due_date' => 'date',
        'verified_package_ids' => 'array',
        'verified_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function setMailboxAttribute($value): void
    {
        $this->attributes['mailbox'] = Customer::normalizeMailbox($value);
    }

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

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestApprovedPayment()
    {
        return $this->hasOne(Payment::class)->where('status', 'approved')->latestOfMany();
    }

    public function isLocked(): bool
    {
        return $this->invoice_status === 'cancelled' || $this->voided_at !== null;
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
