<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'onboarding_documents')) {
                $table->json('onboarding_documents')->nullable()->after('logo');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'account_type')) {
                $table->string('account_type', 30)->default('personal')->after('pricing_tier_id');
            }
            if (! Schema::hasColumn('customers', 'company_name')) {
                $table->string('company_name')->nullable()->after('account_type');
            }
            if (! Schema::hasColumn('customers', 'company_registration')) {
                $table->string('company_registration', 150)->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('customers', 'representative_role')) {
                $table->string('representative_role', 150)->nullable()->after('company_registration');
            }
            if (! Schema::hasColumn('customers', 'business_document_path')) {
                $table->string('business_document_path', 500)->nullable()->after('representative_role');
            }
            if (! Schema::hasColumn('customers', 'business_document_original_name')) {
                $table->string('business_document_original_name')->nullable()->after('business_document_path');
            }
            if (! Schema::hasColumn('customers', 'business_document_mime_type')) {
                $table->string('business_document_mime_type', 150)->nullable()->after('business_document_original_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach ([
                'business_document_mime_type',
                'business_document_original_name',
                'business_document_path',
                'representative_role',
                'company_registration',
                'company_name',
                'account_type',
            ] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'onboarding_documents')) {
                $table->dropColumn('onboarding_documents');
            }
        });
    }
};
