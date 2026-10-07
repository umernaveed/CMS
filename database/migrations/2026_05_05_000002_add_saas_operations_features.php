<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaasOperationsFeatures extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pricing_tiers')) {
            Schema::create('pricing_tiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 100);
                $table->string('slug', 120);
                $table->integer('min_packages_collected')->default(0);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'pricing_tier_id')) {
                $table->unsignedBigInteger('pricing_tier_id')->nullable()->after('account_manager_id');
                $table->foreign('pricing_tier_id')->references('id')->on('pricing_tiers')->onDelete('set null');
            }
        });

        Schema::table('weight_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('weight_rates', 'pricing_tier_id')) {
                $table->unsignedBigInteger('pricing_tier_id')->nullable()->after('tenant_id');
                $table->foreign('pricing_tier_id')->references('id')->on('pricing_tiers')->onDelete('cascade');
            }
        });

        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'processing_fee_jmd')) {
                $table->decimal('processing_fee_jmd', 10, 2)->default(0)->after('storage_fee');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'processing_fee_jmd')) {
                $table->decimal('processing_fee_jmd', 10, 2)->default(0)->after('storage_fee_jmd');
            }
        });

        if (! Schema::hasTable('customer_shipping_addresses')) {
            Schema::create('customer_shipping_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('customer_id');
                $table->string('shipment_type', 50);
                $table->string('label', 100);
                $table->string('country_code', 10)->nullable();
                $table->string('name', 255);
                $table->string('marks', 255)->nullable();
                $table->string('address_1', 255)->nullable();
                $table->string('address_2', 255)->nullable();
                $table->string('entrance_no', 255)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('province', 150)->nullable();
                $table->string('city', 150)->nullable();
                $table->text('detailed_address')->nullable();
                $table->string('state', 150)->nullable();
                $table->string('zip_code', 50)->nullable();
                $table->string('number', 100)->nullable();
                $table->text('note')->nullable();
                $table->boolean('is_system')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('title', 255);
                $table->text('message');
                $table->enum('priority', ['low', 'medium', 'high'])->default('low');
                $table->enum('audience', ['all', 'courier', 'customer', 'super_admin'])->default('all');
                $table->datetime('starts_at')->nullable();
                $table->datetime('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('notification_dismissals')) {
            Schema::create('notification_dismissals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('system_notification_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('dismissed_at');

                $table->unique(['system_notification_id', 'user_id'], 'notification_user_unique');
                $table->foreign('system_notification_id')->references('id')->on('system_notifications')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('notification_dismissals');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('customer_shipping_addresses');

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'processing_fee_jmd')) {
                $table->dropColumn('processing_fee_jmd');
            }
        });

        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'processing_fee_jmd')) {
                $table->dropColumn('processing_fee_jmd');
            }
        });

        Schema::table('weight_rates', function (Blueprint $table) {
            if (Schema::hasColumn('weight_rates', 'pricing_tier_id')) {
                $table->dropForeign(['pricing_tier_id']);
                $table->dropColumn('pricing_tier_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'pricing_tier_id')) {
                $table->dropForeign(['pricing_tier_id']);
                $table->dropColumn('pricing_tier_id');
            }
        });

        Schema::dropIfExists('pricing_tiers');
    }
}
