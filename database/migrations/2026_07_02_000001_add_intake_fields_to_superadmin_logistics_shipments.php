<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_logistics_shipments')) {
            return;
        }

        Schema::table('superadmin_logistics_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_shipments', 'arrival_date')) {
                $table->date('arrival_date')->nullable()->after('departure_date');
            }

            if (! Schema::hasColumn('superadmin_logistics_shipments', 'pieces')) {
                $table->unsignedInteger('pieces')->default(0)->after('cargo_company');
            }

            if (! Schema::hasColumn('superadmin_logistics_shipments', 'additional_cost')) {
                $table->decimal('additional_cost', 12, 2)->default(0)->after('delivery_cost');
            }

            if (! Schema::hasColumn('superadmin_logistics_shipments', 'status')) {
                $table->string('status', 40)->default('in_progress')->after('additional_cost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('superadmin_logistics_shipments')) {
            return;
        }

        Schema::table('superadmin_logistics_shipments', function (Blueprint $table) {
            foreach (['arrival_date', 'pieces', 'additional_cost', 'status'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
