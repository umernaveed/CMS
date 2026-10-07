<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManifestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'manifest_id',
        'package_id',
    ];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
