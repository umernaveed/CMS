<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_device_tokens')) {
            Schema::create('mobile_device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('device_id', 150);
                $table->string('platform', 30)->nullable();
                $table->text('fcm_token');
                $table->string('app_version', 40)->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'device_id'], 'mobile_device_tokens_unique_device');
                $table->index(['tenant_id', 'user_id']);
            });

            return;
        }

        Schema::table('mobile_device_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_device_tokens', 'tenant_id')) {
                $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'user_id')) {
                $table->foreignId('user_id')->after('tenant_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'device_id')) {
                $table->string('device_id', 150)->after('user_id');
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'platform')) {
                $table->string('platform', 30)->nullable()->after('device_id');
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'fcm_token')) {
                $table->text('fcm_token')->after('platform');
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'app_version')) {
                $table->string('app_version', 40)->nullable()->after('fcm_token');
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('app_version');
            }
            if (! Schema::hasColumn('mobile_device_tokens', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_device_tokens');
    }
};
