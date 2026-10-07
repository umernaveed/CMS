<?php

use App\Models\CustomsOffice;
use App\Models\LoadingPort;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CustomsOffice::firstOrCreate(
            ['tenant_id' => null, 'code' => 'KIN'],
            ['name' => 'Kingston Customs Office', 'address' => 'Kingston, Jamaica']
        );

        CustomsOffice::firstOrCreate(
            ['tenant_id' => null, 'code' => 'MBJ'],
            ['name' => 'Montego Bay Customs Office', 'address' => 'Montego Bay, Jamaica']
        );

        LoadingPort::firstOrCreate(
            ['tenant_id' => null, 'code' => 'MIA'],
            ['name' => 'Miami Loading Port', 'city' => 'Miami', 'country' => 'United States']
        );

        LoadingPort::firstOrCreate(
            ['tenant_id' => null, 'code' => 'GZC'],
            ['name' => 'Guangzhou Loading Port', 'city' => 'Guangzhou', 'country' => 'China']
        );
    }

    public function down(): void
    {
        CustomsOffice::whereNull('tenant_id')->whereIn('code', ['KIN', 'MBJ'])->delete();
        LoadingPort::whereNull('tenant_id')->whereIn('code', ['MIA', 'GZC'])->delete();
    }
};
