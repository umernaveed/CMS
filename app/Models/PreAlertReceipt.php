<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreAlertReceipt extends Model
{
    protected $fillable = [
        'pre_alert_id',
        'path',
        'original_name',
        'mime_type',
    ];

    public function preAlert()
    {
        return $this->belongsTo(PreAlert::class);
    }
}
