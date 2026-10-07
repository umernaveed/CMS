<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'referrer_id',
        'referred_id',
        'points_earned',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'points_earned' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(Customer::class, 'referred_id');
    }
}
