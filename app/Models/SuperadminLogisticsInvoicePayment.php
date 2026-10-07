<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperadminLogisticsInvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'payment_method',
        'reference_number',
        'amount_jmd',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_jmd' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(SuperadminLogisticsInvoice::class, 'invoice_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
