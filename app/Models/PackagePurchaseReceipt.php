<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagePurchaseReceipt extends Model
{
    protected $fillable = [
        'package_id',
        'path',
        'original_name',
        'mime_type',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
