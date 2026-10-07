<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_categories', function (Blueprint $table) {
            $table->id();
            $table->string('portal', 30);
            $table->string('scope_key', 80);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['portal', 'scope_key', 'type', 'name'], 'ledger_categories_unique_scope');
            $table->index(['portal', 'tenant_id', 'type', 'is_active']);
        });

        $now = now();
        $superadminDefaults = [
            'income' => ['Subscription Revenue', 'SaaS Revenue - Service Charges', 'Setup Fee', 'Onboarding Fee', 'Courier Billing', 'Other Income'],
            'expense' => ['Hosting', 'Software', 'Payroll', 'Marketing', 'Bank Fees', 'Office Expense', 'Warehouse', 'Logistics', 'Professional Services', 'Other Expense'],
        ];
        foreach ($superadminDefaults as $type => $categories) {
            foreach ($categories as $name) {
                DB::table('ledger_categories')->insertOrIgnore([
                    'portal' => 'superadmin',
                    'scope_key' => 'superadmin',
                    'tenant_id' => null,
                    'type' => $type,
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $courierDefaults = [
            'income' => ['Package Revenue', 'Delivery Revenue', 'Storage Fees', 'Service Fees', 'Other Income'],
            'expense' => ['Payroll', 'Rent', 'Utilities', 'Fuel', 'Vehicle Maintenance', 'Warehouse', 'Supplies', 'Bank Fees', 'Other Expense'],
        ];
        $tenantIds = DB::table('tenants')->pluck('id');
        foreach ($tenantIds as $tenantId) {
            foreach ($courierDefaults as $type => $categories) {
                foreach ($categories as $name) {
                    DB::table('ledger_categories')->insertOrIgnore([
                        'portal' => 'courier',
                        'scope_key' => 'tenant:'.$tenantId,
                        'tenant_id' => $tenantId,
                        'type' => $type,
                        'name' => $name,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('superadmin_finance_entries')) {
            DB::table('superadmin_finance_entries')
                ->select('type', 'category')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->get()
                ->each(function ($entry) use ($now) {
                    DB::table('ledger_categories')->insertOrIgnore([
                        'portal' => 'superadmin',
                        'scope_key' => 'superadmin',
                        'tenant_id' => null,
                        'type' => $entry->type,
                        'name' => $entry->category,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }

        if (Schema::hasTable('finance_entries')) {
            DB::table('finance_entries')
                ->select('tenant_id', 'type', 'category')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->get()
                ->each(function ($entry) use ($now) {
                    DB::table('ledger_categories')->insertOrIgnore([
                        'portal' => 'courier',
                        'scope_key' => 'tenant:'.$entry->tenant_id,
                        'tenant_id' => $entry->tenant_id,
                        'type' => $entry->type,
                        'name' => $entry->category,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_categories');
    }
};
