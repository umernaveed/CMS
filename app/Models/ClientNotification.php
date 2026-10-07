<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientNotification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function messagePreview(int $limit = 110): string
    {
        $message = html_entity_decode((string) $this->message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = trim(preg_replace('/\s+/', ' ', strip_tags($message)));

        return Str::limit($message, $limit);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
