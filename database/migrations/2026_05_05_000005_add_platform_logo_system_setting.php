<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::firstOrCreate(
            ['key' => 'app_logo'],
            ['value' => null, 'group' => 'general']
        );
    }

    public function down(): void
    {
        SystemSetting::where('key', 'app_logo')->delete();
    }
};
