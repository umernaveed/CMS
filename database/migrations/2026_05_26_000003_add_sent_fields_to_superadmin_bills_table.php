<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('superadmin_bills', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('reviewed_at');
            $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn('sent_at');
        });
    }
};
