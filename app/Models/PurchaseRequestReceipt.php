<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestReceipt extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'path',
        'original_name',
        'mime_type',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
