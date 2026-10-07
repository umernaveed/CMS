<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = ['tenant_id', 'key', 'value'];

    public static function getValue(int $tenantId, string $key, $default = null)
    {
        return static::where('tenant_id', $tenantId)->where('key', $key)->value('value') ?? $default;
    }

    public static function putValue(int $tenantId, string $key, $value): void
    {
        static::updateOrCreate(['tenant_id' => $tenantId, 'key' => $key], ['value' => $value]);
    }
}
