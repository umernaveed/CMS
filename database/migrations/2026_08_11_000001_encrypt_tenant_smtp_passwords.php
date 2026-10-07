<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenant_settings')
            ->where('key', 'smtp_password')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($settings) {
                foreach ($settings as $setting) {
                    $value = (string) $setting->value;
                    if (str_starts_with($value, 'encrypted:')) {
                        continue;
                    }

                    DB::table('tenant_settings')
                        ->where('id', $setting->id)
                        ->update(['value' => 'encrypted:'.encrypt($value)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('tenant_settings')
            ->where('key', 'smtp_password')
            ->whereNotNull('value')
            ->where('value', 'like', 'encrypted:%')
            ->orderBy('id')
            ->chunkById(100, function ($settings) {
                foreach ($settings as $setting) {
                    try {
                        $value = decrypt(substr((string) $setting->value, strlen('encrypted:')));
                    } catch (Throwable $exception) {
                        continue;
                    }

                    DB::table('tenant_settings')
                        ->where('id', $setting->id)
                        ->update(['value' => $value]);
                }
            });
    }
};
