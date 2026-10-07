<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')
            ->whereNull('date_received')
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($packages) {
                foreach ($packages as $package) {
                    $exists = DB::table('pre_alerts')
                        ->where('tenant_id', $package->tenant_id)
                        ->where(function ($query) use ($package) {
                            $query->where('supplier_tracking', $package->supplier_tracking)
                                ->orWhere('pre_alert_number', $package->tracking_number);
                        })
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('pre_alerts')->insert([
                        'tenant_id' => $package->tenant_id,
                        'customer_id' => $package->customer_id,
                        'outlet_id' => $package->outlet_id,
                        'package_id' => null,
                        'pre_alert_number' => Str::startsWith((string) $package->tracking_number, 'PRE-')
                            ? $package->tracking_number
                            : $this->nextPreAlertNumber((int) $package->tenant_id),
                        'supplier_tracking' => $package->supplier_tracking,
                        'courier' => $package->courier,
                        'merchant' => $package->merchant,
                        'description' => $package->description ?: ($package->notes ?: 'Pre-Alert'),
                        'price_usd' => $package->price_usd ?: 0,
                        'delivery_type' => in_array($package->delivery_type, ['pickup', 'delivery'], true) ? $package->delivery_type : 'pickup',
                        'status' => 'submitted',
                        'notes' => $package->notes,
                        'created_at' => $package->created_at ?: now(),
                        'updated_at' => $package->updated_at ?: now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Backfilled records are left intact to avoid deleting customer-submitted data.
    }

    private function nextPreAlertNumber(int $tenantId): string
    {
        $prefix = 'PRE-'.$this->courierCode($tenantId);
        $sequence = DB::table('pre_alerts')
            ->where('tenant_id', $tenantId)
            ->where('pre_alert_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DB::table('pre_alerts')->where('tenant_id', $tenantId)->where('pre_alert_number', $number)->exists());

        return $number;
    }

    private function courierCode(int $tenantId): string
    {
        $configured = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->whereIn('key', ['hawb_prefix', 'courier_code', 'mailbox_prefix'])
            ->orderByRaw("case `key` when 'hawb_prefix' then 1 when 'courier_code' then 2 else 3 end")
            ->value('value');
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $configured));

        return $code !== '' ? $code : 'COU';
    }
};
