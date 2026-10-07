<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'wms_manifest_id',
        'wms_last_synced_at',
        'manifest_number',
        'file_path',
        'cargo_company',
        'customs_office_id',
        'date_of_departure',
        'shipping_agent_code',
        'exporter_name',
        'exporter_address',
        'loading_port_id',
        'master_air_bill',
        'voyage_number',
        'carrier_code',
        'status',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'date_of_departure' => 'date',
        'submitted_at' => 'datetime',
        'wms_last_synced_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customsOffice()
    {
        return $this->belongsTo(CustomsOffice::class);
    }

    public function loadingPort()
    {
        return $this->belongsTo(LoadingPort::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items()
    {
        return $this->hasMany(ManifestItem::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'manifest_items')->withTimestamps();
    }
}
