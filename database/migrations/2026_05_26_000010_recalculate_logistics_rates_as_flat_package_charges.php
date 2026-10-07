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

        DB::table('superadmin_logistics_packages')->update([
            'amount' => DB::raw('ROUND(rate, 2)'),
        ]);

        if (Schema::hasTable('superadmin_logistics_invoice_items')) {
            DB::table('superadmin_logistics_invoice_items')->update([
                'amount' => DB::raw('ROUND(rate, 2)'),
            ]);
        }

        $this->recalculateInvoices();
    }

    public function down(): void
    {
        if (! Schema::hasTable('superadmin_logistics_packages')) {
            return;
        }

        DB::table('superadmin_logistics_packages')->update([
            'amount' => DB::raw('ROUND(weight * rate, 2)'),
        ]);

        if (Schema::hasTable('superadmin_logistics_invoice_items')) {
            DB::table('superadmin_logistics_invoice_items')->update([
                'amount' => DB::raw('ROUND(weight * rate, 2)'),
            ]);
        }

        $this->recalculateInvoices();
    }

    private function recalculateInvoices(): void
    {
        if (! Schema::hasTable('superadmin_logistics_invoices') || ! Schema::hasTable('superadmin_logistics_invoice_items')) {
            return;
        }

        DB::table('superadmin_logistics_invoice_items')
            ->select('invoice_id', DB::raw('SUM(amount) as total'))
            ->groupBy('invoice_id')
            ->orderBy('invoice_id')
            ->get()
            ->each(function ($row) {
                DB::table('superadmin_logistics_invoices')
                    ->where('id', $row->invoice_id)
                    ->update([
                        'subtotal' => round((float) $row->total, 2),
                        'total' => round((float) $row->total, 2),
                    ]);
            });
    }
};
