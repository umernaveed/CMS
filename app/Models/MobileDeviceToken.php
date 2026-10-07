<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileDeviceToken extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'device_id',
        'platform',
        'fcm_token',
        'app_version',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];
}
