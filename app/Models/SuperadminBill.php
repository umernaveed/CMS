<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'bill_number',
        'title',
        'category',
        'source_type',
        'description',
        'issue_date',
        'due_date',
        'currency',
        'subtotal_jmd',
        'tax_jmd',
        'total_jmd',
        'amount_paid_jmd',
        'balance_jmd',
        'status',
        'notes',
        'admin_notes',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'sent_by',
        'sent_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal_jmd' => 'decimal:2',
        'tax_jmd' => 'decimal:2',
        'total_jmd' => 'decimal:2',
        'amount_paid_jmd' => 'decimal:2',
        'balance_jmd' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'sent_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments()
    {
        return $this->hasMany(SuperadminBillPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === 'cancelled' || $this->voided_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->isVoided();
    }

    public function getPublicDescriptionAttribute(): ?string
    {
        $description = trim((string) $this->description);
        if ($description === '') {
            return null;
        }

        if (in_array($this->source_type, ['package_usage', 'shipped_package'], true)
            && preg_match('/Billing cycle:|Packages (logged|shipped):|Logged package sample:|Shipped packages:/i', $description)) {
            return null;
        }

        return $description;
    }
}
