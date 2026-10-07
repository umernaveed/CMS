<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        if (Schema::hasColumn('superadmin_logistics_packages', 'amount_usd')) {
            DB::table('superadmin_logistics_packages')
                ->where(function ($query) {
                    $query->whereNull('amount_usd')->orWhere('amount_usd', 0);
                })
                ->update([
                    'warehouse_fee' => DB::raw('ROUND(COALESCE(NULLIF(warehouse_fee, 0), rate), 2)'),
                    'amount_usd' => DB::raw('ROUND(COALESCE(NULLIF(warehouse_fee, 0), rate) + COALESCE(service_fee, 0) + COALESCE(freight_charge, 0), 2)'),
                    'amount' => DB::raw('ROUND((COALESCE(NULLIF(warehouse_fee, 0), rate) + COALESCE(service_fee, 0) + COALESCE(freight_charge, 0)) * COALESCE(NULLIF(exchange_rate_jmd, 0), 1), 2)'),
                ]);
        }

        if (Schema::hasTable('superadmin_logistics_invoice_items') && Schema::hasColumn('superadmin_logistics_invoice_items', 'amount_usd')) {
            DB::table('superadmin_logistics_invoice_items')
                ->where(function ($query) {
                    $query->whereNull('amount_usd')->orWhere('amount_usd', 0);
                })
                ->update([
                    'warehouse_fee' => DB::raw('ROUND(COALESCE(NULLIF(warehouse_fee, 0), rate), 2)'),
                    'amount_usd' => DB::raw('ROUND(COALESCE(NULLIF(warehouse_fee, 0), rate) + COALESCE(service_fee, 0) + COALESCE(freight_charge, 0), 2)'),
                    'amount' => DB::raw('ROUND((COALESCE(NULLIF(warehouse_fee, 0), rate) + COALESCE(service_fee, 0) + COALESCE(freight_charge, 0)) * COALESCE(NULLIF(exchange_rate_jmd, 0), 1), 2)'),
                ]);

            DB::table('superadmin_logistics_invoice_items')
                ->select('invoice_id', DB::raw('SUM(amount) as total'))
                ->groupBy('invoice_id')
                ->get()
                ->each(function ($row) {
                    DB::table('superadmin_logistics_invoices')
                        ->where('id', $row->invoice_id)
                        ->update([
                            'subtotal' => round((float) $row->total, 2),
                            'total' => DB::raw('GREATEST(0, '.round((float) $row->total, 2).' - COALESCE(discount_jmd, 0))'),
                            'balance_jmd' => DB::raw('GREATEST(0, GREATEST(0, '.round((float) $row->total, 2).' - COALESCE(discount_jmd, 0)) - COALESCE(amount_paid_jmd, 0))'),
                        ]);
                });
        }
    }

    public function down(): void
    {
        //
    }
};
