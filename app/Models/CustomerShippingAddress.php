<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerShippingAddress extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'shipment_type',
        'display_order',
        'label',
        'country_code',
        'flag_icon',
        'flag_image',
        'name',
        'marks',
        'address_1',
        'address_2',
        'entrance_no',
        'country',
        'province',
        'city',
        'detailed_address',
        'state',
        'zip_code',
        'number',
        'note',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function getDisplayFlagAttribute(): string
    {
        $countryCode = strtoupper(trim((string) $this->country_code));

        if (preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return mb_chr(127397 + ord($countryCode[0]), 'UTF-8')
                .mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
        }

        $flagIcon = trim((string) $this->flag_icon);

        return mb_check_encoding($flagIcon, 'UTF-8') && ! str_contains($flagIcon, 'ðŸ')
            ? $flagIcon
            : '';
    }

    public function getDisplayFlagImageUrlAttribute(): ?string
    {
        $path = trim((string) $this->flag_image);
        if ($path === '') {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    }
}
