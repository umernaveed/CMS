<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminBillPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'superadmin_bill_id',
        'tenant_id',
        'amount_jmd',
        'payment_method',
        'reference_number',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime_type',
        'status',
        'notes',
        'admin_notes',
        'submitted_by',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'amount_jmd' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function bill()
    {
        return $this->belongsTo(SuperadminBill::class, 'superadmin_bill_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
