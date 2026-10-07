<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShippingMode extends Model
{
    use HasFactory;

    public const DEFAULTS = [
        [
            'name' => 'Air',
            'code' => 'air',
            'category' => 'air',
            'description' => 'By weight',
            'icon' => 'fas fa-plane',
            'sort_order' => 10,
        ],
        [
            'name' => 'Sea',
            'code' => 'sea',
            'category' => 'sea',
            'description' => 'By cubic feet',
            'icon' => 'fas fa-ship',
            'sort_order' => 20,
        ],
        [
            'name' => 'Local',
            'code' => 'local',
            'category' => 'local',
            'description' => 'Delivery route',
            'icon' => 'fas fa-truck',
            'sort_order' => 30,
        ],
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'category',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function normalizeCode(string $value): string
    {
        return Str::slug($value) ?: 'mode';
    }

    public static function ensureDefaultsForTenant(int $tenantId): void
    {
        if ($tenantId <= 0 || ! Schema::hasTable('shipping_modes')) {
            return;
        }

        if (self::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $mode) {
            self::create($mode + [
                'tenant_id' => $tenantId,
                'is_active' => true,
                'is_default' => true,
            ]);
        }
    }
    public function isSea(): bool
    {
        return $this->category === 'sea';
    }

    public function isLocal(): bool
    {
        return $this->category === 'local';
    }
}







