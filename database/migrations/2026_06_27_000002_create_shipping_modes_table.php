<?php

use App\Models\ShippingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_modes')) {
            Schema::create('shipping_modes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('code', 80);
                $table->enum('category', ['air', 'sea', 'local'])->default('air');
                $table->string('description')->nullable();
                $table->string('icon', 80)->default('fas fa-box');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active', 'sort_order']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('weight_rates', 'freight_type')) {
            DB::statement("ALTER TABLE `weight_rates` MODIFY `freight_type` VARCHAR(80) NOT NULL DEFAULT 'air'");
        }

        if (Schema::hasColumn('packages', 'freight_type')) {
            DB::statement("ALTER TABLE `packages` MODIFY `freight_type` VARCHAR(80) NOT NULL DEFAULT 'air'");
        }

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) {
            foreach (ShippingMode::DEFAULTS as $mode) {
                DB::table('shipping_modes')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => $mode['code']],
                    $mode + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_modes');
    }
};


