<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmEmailQueueItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'crm_email_draft_id',
        'customer_id',
        'user_id',
        'to_email',
        'status',
        'error_message',
        'queued_at',
        'processing_at',
        'sent_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function draft()
    {
        return $this->belongsTo(CrmEmailDraft::class, 'crm_email_draft_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
