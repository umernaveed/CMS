<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('tenant_settings')) {
            return;
        }

        $now = now();
        $loyaltyDefaults = [
            'loyalty_enabled' => '0',
            'loyalty_signup_points' => '0',
            'loyalty_package_points' => '0',
            'loyalty_referral_points' => '0',
            'loyalty_invoice_points_per_amount' => '0',
            'loyalty_point_value_jmd' => '0',
        ];

        DB::table('tenants')->orderBy('id')->chunkById(100, function ($tenants) use ($loyaltyDefaults, $now) {
            foreach ($tenants as $tenant) {
                foreach ($loyaltyDefaults as $key => $value) {
                    $exists = DB::table('tenant_settings')
                        ->where('tenant_id', $tenant->id)
                        ->where('key', $key)
                        ->exists();

                    if (! $exists) {
                        DB::table('tenant_settings')->insert([
                            'tenant_id' => $tenant->id,
                            'key' => $key,
                            'value' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });

        DB::table('tenant_settings')
            ->where('key', 'warehouse_addresses')
            ->orderBy('id')
            ->chunkById(100, function ($settings) use ($now) {
                foreach ($settings as $setting) {
                    $addresses = json_decode((string) $setting->value, true);
                    if (! is_array($addresses)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($addresses as &$address) {
                        if (! is_array($address) || filled($address['flag_icon'] ?? null)) {
                            continue;
                        }

                        $flag = $this->flagFromCountryCode($address['country_code'] ?? null);
                        if ($flag) {
                            $address['flag_icon'] = $flag;
                            $changed = true;
                        }
                    }
                    unset($address);

                    if ($changed) {
                        DB::table('tenant_settings')->where('id', $setting->id)->update([
                            'value' => json_encode($addresses, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Defaults should remain conservative; no destructive rollback.
    }

    private function flagFromCountryCode(?string $countryCode): ?string
    {
        $countryCode = strtoupper((string) $countryCode);
        if (strlen($countryCode) !== 2) {
            return null;
        }

        return mb_chr(127397 + ord($countryCode[0]), 'UTF-8').mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    }
};
