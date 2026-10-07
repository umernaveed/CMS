<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'processing_fee_jmd')) {
                $table->decimal('processing_fee_jmd', 10, 2)->default(0)->after('storage_fee_jmd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'processing_fee_jmd')) {
                $table->dropColumn('processing_fee_jmd');
            }
        });
    }
};
