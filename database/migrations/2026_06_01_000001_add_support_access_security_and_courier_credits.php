<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'security_code_hash')) {
                $table->string('security_code_hash')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'security_code_regenerated_at')) {
                $table->timestamp('security_code_regenerated_at')->nullable()->after('security_code_hash');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'credit_balance_jmd')) {
                $table->decimal('credit_balance_jmd', 12, 2)->default(0)->after('per_package_base_fee_jmd');
            }
        });

        if (! Schema::hasTable('courier_support_access_codes')) {
        Schema::create('courier_support_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->string('label')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('active_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at', 'revoked_at'], 'csac_tenant_exp_rev_idx');
        });
        }

        if (! Schema::hasTable('master_tech_access_codes')) {
        Schema::create('master_tech_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_hash');
            $table->string('label')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at', 'revoked_at'], 'mtac_dates_idx');
        });
        }

        if (! Schema::hasTable('tenant_credit_transactions')) {
        Schema::create('tenant_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 40);
            $table->decimal('amount_jmd', 12, 2);
            $table->decimal('balance_after_jmd', 12, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type'], 'tct_tenant_type_idx');
            $table->index(['reference_type', 'reference_id'], 'tct_ref_idx');
        });
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'payment_method')) {
                $table->string('payment_method', 80)->nullable()->after('status');
            }

            if (! Schema::hasColumn('wallet_transactions', 'reference_number')) {
                $table->string('reference_number', 150)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('wallet_transactions', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('reference_number');
            }

            if (! Schema::hasColumn('wallet_transactions', 'receipt_original_name')) {
                $table->string('receipt_original_name')->nullable()->after('receipt_path');
            }
        });

        Schema::table('superadmin_logistics_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_logistics_invoices', 'discount_jmd')) {
                $table->decimal('discount_jmd', 12, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'amount_paid_jmd')) {
                $table->decimal('amount_paid_jmd', 12, 2)->default(0)->after('total');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'balance_jmd')) {
                $table->decimal('balance_jmd', 12, 2)->default(0)->after('amount_paid_jmd');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('paid_by');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'receipt_original_name')) {
                $table->string('receipt_original_name')->nullable()->after('receipt_path');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('superadmin_logistics_invoices', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            foreach (['receipt_original_name', 'receipt_path', 'reference_number', 'payment_method'] as $column) {
                if (Schema::hasColumn('wallet_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('superadmin_logistics_invoices', function (Blueprint $table) {
            foreach (['voided_by', 'voided_at', 'receipt_original_name', 'receipt_path', 'balance_jmd', 'amount_paid_jmd', 'discount_jmd'] as $column) {
                if (Schema::hasColumn('superadmin_logistics_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('tenant_credit_transactions');
        Schema::dropIfExists('master_tech_access_codes');
        Schema::dropIfExists('courier_support_access_codes');

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'credit_balance_jmd')) {
                $table->dropColumn('credit_balance_jmd');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['security_code_regenerated_at', 'security_code_hash'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
