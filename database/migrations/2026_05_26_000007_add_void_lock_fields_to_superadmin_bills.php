<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('superadmin_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('superadmin_bills', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('superadmin_bills', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (! Schema::hasColumn('superadmin_bills', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_bills', function (Blueprint $table) {
            if (Schema::hasColumn('superadmin_bills', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }
            if (Schema::hasColumn('superadmin_bills', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
            if (Schema::hasColumn('superadmin_bills', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
        });
    }
};
