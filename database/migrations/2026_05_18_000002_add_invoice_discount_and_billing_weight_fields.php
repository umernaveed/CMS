<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percent'])->default('fixed')->after('discount_amount_jmd');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->string('billing_weight_mode', 20)->nullable()->after('discount_value');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('billable_weight', 10, 2)->default(0)->after('weight');
            $table->decimal('rate_jmd', 10, 2)->default(0)->after('package_price_jmd');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['billable_weight', 'rate_jmd']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'billing_weight_mode']);
        });
    }
};
