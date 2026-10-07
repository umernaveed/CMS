<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmEmailDraft extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'target',
        'branch_id',
        'email_group',
        'target_customer_ids_json',
        'subject',
        'body',
        'attachments_json',
        'status',
        'sent_count',
        'failed_count',
        'notification_count',
        'sent_at',
    ];

    protected $casts = [
        'attachments_json' => 'array',
        'target_customer_ids_json' => 'array',
        'sent_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Outlet::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function queueItems()
    {
        return $this->hasMany(CrmEmailQueueItem::class);
    }
}
