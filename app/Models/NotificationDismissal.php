<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDismissal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'system_notification_id',
        'user_id',
        'dismissed_at',
    ];
}
