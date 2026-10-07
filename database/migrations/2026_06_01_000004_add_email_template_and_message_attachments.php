<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_email_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_email_drafts', 'attachments_json')) {
                $table->longText('attachments_json')->nullable()->after('body');
            }
        });

        Schema::table('system_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('system_notifications', 'attachments_json')) {
                $table->longText('attachments_json')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_email_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('crm_email_drafts', 'attachments_json')) {
                $table->dropColumn('attachments_json');
            }
        });

        Schema::table('system_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('system_notifications', 'attachments_json')) {
                $table->dropColumn('attachments_json');
            }
        });
    }
};
