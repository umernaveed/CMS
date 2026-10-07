<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_invoice_payments')) {
            Schema::create('superadmin_logistics_invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('superadmin_logistics_invoices')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('payment_method', 60)->default('cash');
                $table->string('reference_number')->nullable();
                $table->decimal('amount_jmd', 12, 2);
                $table->string('source', 40)->default('manual');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['invoice_id', 'source'], 'salip_invoice_source_idx');
                $table->index(['tenant_id', 'payment_method'], 'salip_tenant_method_idx');
            });
        }

        $this->ensureIndex(
            'superadmin_logistics_invoice_payments',
            'salip_invoice_source_idx',
            ['invoice_id', 'source']
        );
        $this->ensureIndex(
            'superadmin_logistics_invoice_payments',
            'salip_tenant_method_idx',
            ['tenant_id', 'payment_method']
        );

        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_packages', 'location_status')) {
                $table->string('location_status', 80)->nullable()->after('checked_out_by');
            }
            if (! Schema::hasColumn('superadmin_logistics_packages', 'location_notes')) {
                $table->text('location_notes')->nullable()->after('location_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_logistics_packages', function (Blueprint $table) {
            foreach (['location_notes', 'location_status'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('superadmin_logistics_invoice_payments');
    }

    private function ensureIndex(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'select count(1) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
