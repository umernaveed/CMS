<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerShippingAddress;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerShippingAddressService
{
    public function syncDefaultAddresses(Customer $customer): void
    {
        $customer->loadMissing(['user', 'outlet']);
        $tenantId = $customer->tenant_id;
        $fullName = trim(optional($customer->user)->full_name ?: 'Customer');
        $mailbox = $this->addressMailbox($tenantId, (string) $customer->mailbox);
        $branch = trim((string) (optional($customer->outlet)->outlet_code ?: optional($customer->outlet)->short_name ?: optional($customer->outlet)->name ?: ''));
        $account = trim($mailbox.($branch !== '' ? ' '.$branch : ''));

        $templates = $this->warehouseTemplates($tenantId, $mailbox, $fullName, $account);

        $activeTypes = [];

        foreach ($templates as $index => $data) {
            if (isset($data['is_active']) && ! $data['is_active']) {
                continue;
            }

            $type = $data['shipment_type'] ?? $data['key'] ?? Str::slug(($data['label'] ?? 'warehouse').' '.$index, '_');
            $type = Str::limit($type, 50, '');
            $activeTypes[] = $type;

            $hasCustomerOverride = CustomerShippingAddress::where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->where('shipment_type', $type)
                ->where('is_system', false)
                ->exists();

            if ($hasCustomerOverride) {
                CustomerShippingAddress::where('tenant_id', $tenantId)
                    ->where('customer_id', $customer->id)
                    ->where('shipment_type', $type)
                    ->where('is_system', true)
                    ->update(['is_active' => false]);
                continue;
            }

            $values = [
                'display_order' => $data['display_order'] ?? $index,
                'label' => $data['label'] ?? 'Warehouse Address',
                'country_code' => $data['country_code'] ?? null,
                'flag_icon' => $data['flag_icon'] ?? $this->flagFromCountryCode($data['country_code'] ?? null),
                'flag_image' => $data['flag_image'] ?? null,
                'name' => $data['name'] ?? $fullName,
                'marks' => $data['marks'] ?? null,
                'address_1' => $data['address_1'] ?? null,
                'address_2' => $data['address_2'] ?? null,
                'entrance_no' => $data['entrance_no'] ?? null,
                'country' => $data['country'] ?? null,
                'province' => $data['province'] ?? null,
                'city' => $data['city'] ?? null,
                'detailed_address' => $data['detailed_address'] ?? null,
                'state' => $data['state'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'number' => $data['number'] ?? null,
                'note' => $data['note'] ?? null,
                'is_active' => true,
            ];

            if (! Schema::hasColumn('customer_shipping_addresses', 'flag_icon')) {
                unset($values['flag_icon']);
            }
            if (! Schema::hasColumn('customer_shipping_addresses', 'flag_image')) {
                unset($values['flag_image']);
            }
            if (! Schema::hasColumn('customer_shipping_addresses', 'display_order')) {
                unset($values['display_order']);
            }

            CustomerShippingAddress::updateOrCreate([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'shipment_type' => $type,
                'is_system' => true,
            ], $values);
        }

        CustomerShippingAddress::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where('is_system', true)
            ->when($activeTypes !== [], fn ($query) => $query->whereNotIn('shipment_type', $activeTypes))
            ->update(['is_active' => false]);
    }

    private function warehouseTemplates(int $tenantId, string $mailbox, string $fullName, string $account): array
    {
        $configured = TenantSetting::getValue($tenantId, 'warehouse_addresses');
        if ($configured) {
            $decoded = json_decode($configured, true);
            if (is_array($decoded) && $decoded !== []) {
                return collect($decoded)
                    ->map(fn ($row, $index) => $this->normalizeConfiguredTemplate($row, $index, $mailbox, $fullName, $account))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return [
            'us_air' => [
                'shipment_type' => 'us_air',
                'display_order' => 0,
                'label' => 'US Air',
                'country_code' => 'US',
                'flag_icon' => '🇺🇸',
                'name' => $fullName.' OLOG',
                'address_1' => TenantSetting::getValue($tenantId, 'us_warehouse_address_1', '1807 SW 31st Ave'),
                'address_2' => $mailbox,
                'city' => TenantSetting::getValue($tenantId, 'us_warehouse_city', 'Hallandale'),
                'state' => TenantSetting::getValue($tenantId, 'us_warehouse_state', 'Florida'),
                'zip_code' => TenantSetting::getValue($tenantId, 'us_warehouse_zip', '33009'),
                'country' => 'United States',
            ],
            'us_sea' => [
                'shipment_type' => 'us_sea',
                'display_order' => 1,
                'label' => 'US Sea',
                'country_code' => 'US',
                'flag_icon' => '🇺🇸',
                'name' => $fullName.' OLOG',
                'address_1' => TenantSetting::getValue($tenantId, 'us_warehouse_address_1', '1807 SW 31st Ave'),
                'address_2' => $mailbox.' SEA',
                'city' => TenantSetting::getValue($tenantId, 'us_warehouse_city', 'Hallandale'),
                'state' => TenantSetting::getValue($tenantId, 'us_warehouse_state', 'Florida'),
                'zip_code' => TenantSetting::getValue($tenantId, 'us_warehouse_zip', '33009'),
                'country' => 'United States',
            ],
            'china_air' => [
                'shipment_type' => 'china_air',
                'display_order' => 2,
                'label' => 'China Air',
                'country_code' => 'CN',
                'flag_icon' => '🇨🇳',
                'name' => $fullName,
                'marks' => $account.' AIR',
                'entrance_no' => TenantSetting::getValue($tenantId, 'china_entrance_no', 'Otisian'),
                'country' => 'China',
                'province' => TenantSetting::getValue($tenantId, 'china_province', ''),
                'city' => TenantSetting::getValue($tenantId, 'china_city', ''),
                'detailed_address' => TenantSetting::getValue($tenantId, 'china_detailed_address', ''),
                'number' => TenantSetting::getValue($tenantId, 'china_number', ''),
                'zip_code' => TenantSetting::getValue($tenantId, 'china_zip', ''),
            ],
            'china_sea' => [
                'shipment_type' => 'china_sea',
                'display_order' => 3,
                'label' => 'China Sea',
                'country_code' => 'CN',
                'flag_icon' => '🇨🇳',
                'name' => $fullName,
                'marks' => $account.' SEA',
                'entrance_no' => TenantSetting::getValue($tenantId, 'china_entrance_no', 'Otisian'),
                'country' => 'China',
                'province' => TenantSetting::getValue($tenantId, 'china_province', ''),
                'city' => TenantSetting::getValue($tenantId, 'china_city', ''),
                'detailed_address' => TenantSetting::getValue($tenantId, 'china_detailed_address', ''),
                'number' => TenantSetting::getValue($tenantId, 'china_number', ''),
                'zip_code' => TenantSetting::getValue($tenantId, 'china_zip', ''),
            ],
        ];
    }

    private function normalizeConfiguredTemplate(array $row, int $index, string $mailbox, string $fullName, string $account): ?array
    {
        if (isset($row['is_active']) && ! $row['is_active']) {
            return null;
        }

        $label = trim((string) ($row['label'] ?? 'Warehouse '.($index + 1)));
        $freight = strtolower(trim((string) ($row['freight_type'] ?? '')));
        $address2Suffix = trim((string) ($row['address_2_suffix'] ?? ''));
        $marksSuffix = trim((string) ($row['marks_suffix'] ?? ''));
        $nameSuffix = trim((string) ($row['name_suffix'] ?? ''));
        $countryCode = strtoupper(trim((string) ($row['country_code'] ?? '')));
        $shipmentType = $row['shipment_type'] ?? Str::slug(collect([$countryCode, $freight, $label, $index])->filter()->implode(' '), '_');

        return [
            'shipment_type' => $shipmentType,
            'display_order' => $index,
            'label' => $label,
            'country_code' => $countryCode,
            'flag_icon' => trim((string) ($row['flag_icon'] ?? '')) ?: $this->flagFromCountryCode($countryCode),
            'flag_image' => filled($row['flag_image'] ?? null) ? trim((string) $row['flag_image']) : null,
            'name' => trim($fullName.($nameSuffix !== '' ? ' '.$nameSuffix : '')),
            'marks' => $marksSuffix !== '' ? trim($account.' '.$marksSuffix) : null,
            'address_1' => filled($row['address_1'] ?? null) ? trim((string) $row['address_1']) : null,
            'address_2' => trim($mailbox.($address2Suffix ? ' '.$address2Suffix : '')) ?: null,
            'entrance_no' => filled($row['entrance_no'] ?? null) ? trim((string) $row['entrance_no']) : null,
            'country' => filled($row['country'] ?? null) ? trim((string) $row['country']) : null,
            'province' => filled($row['province'] ?? null) ? trim((string) $row['province']) : null,
            'city' => filled($row['city'] ?? null) ? trim((string) $row['city']) : null,
            'detailed_address' => filled($row['detailed_address'] ?? null) ? trim((string) $row['detailed_address']) : null,
            'state' => filled($row['state'] ?? null) ? trim((string) $row['state']) : null,
            'zip_code' => filled($row['zip_code'] ?? null) ? trim((string) $row['zip_code']) : null,
            'number' => filled($row['number'] ?? null) ? trim((string) $row['number']) : null,
            'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
            'is_active' => true,
        ];
    }

    private function flagFromCountryCode(?string $countryCode): ?string
    {
        $countryCode = strtoupper((string) $countryCode);
        if (strlen($countryCode) !== 2) {
            return null;
        }

        return mb_chr(127397 + ord($countryCode[0]), 'UTF-8').mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    }

    private function addressMailbox(int $tenantId, string $mailbox): string
    {
        $prefix = strtoupper(trim((string) TenantSetting::getValue($tenantId, 'mailbox_prefix', '')));
        $mailbox = trim($mailbox);

        if ($prefix === '' || Str::startsWith(strtoupper($mailbox), $prefix)) {
            return $mailbox;
        }

        if (preg_match('/(\d+)$/', $mailbox, $matches)) {
            return $prefix.str_pad($matches[1], max(5, strlen($matches[1])), '0', STR_PAD_LEFT);
        }

        return $prefix.$mailbox;
    }
}
