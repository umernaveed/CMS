<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('reference_number');
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->string('receipt_mime_type')->nullable()->after('receipt_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_original_name', 'receipt_mime_type']);
        });
    }
};
