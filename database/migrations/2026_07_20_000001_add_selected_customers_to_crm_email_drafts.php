<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_email_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_email_drafts', 'target_customer_ids_json')) {
                $table->json('target_customer_ids_json')->nullable()->after('email_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_email_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('crm_email_drafts', 'target_customer_ids_json')) {
                $table->dropColumn('target_customer_ids_json');
            }
        });
    }
};