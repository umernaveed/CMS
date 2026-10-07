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
            return;
        }

        Schema::table('shipping_modes', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_modes', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });

        DB::table('shipping_modes')
            ->orderBy('tenant_id')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'code', 'category'])
            ->groupBy(fn ($mode) => $mode->tenant_id.'|'.$mode->category)
            ->each(function ($modes) {
                $preferred = $modes->first(fn ($mode) => $mode->code === $mode->category) ?: $modes->first();
                if ($preferred) {
                    DB::table('shipping_modes')->where('id', $preferred->id)->update(['is_default' => true]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_modes') || ! Schema::hasColumn('shipping_modes', 'is_default')) {
            return;
        }

        Schema::table('shipping_modes', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
