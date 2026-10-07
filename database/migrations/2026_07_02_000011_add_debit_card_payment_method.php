<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDebitCardPaymentMethod extends Migration
{
    public function up()
    {
        DB::table('payment_methods')->updateOrInsert(
            ['tenant_id' => null, 'code' => 'DEBIT_CARD'],
            [
                'name' => 'Debit Card',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down()
    {
        DB::table('payment_methods')
            ->whereNull('tenant_id')
            ->where('code', 'DEBIT_CARD')
            ->delete();
    }
}