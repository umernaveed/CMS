<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantCreditTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'amount_jmd',
        'balance_after_jmd',
        'reference_type',
        'reference_id',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_jmd' => 'decimal:2',
        'balance_after_jmd' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
