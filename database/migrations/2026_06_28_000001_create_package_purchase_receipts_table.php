<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_purchase_receipts')) {
            Schema::create('package_purchase_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained()->cascadeOnDelete();
                $table->string('path', 500);
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->timestamps();

                $table->index('package_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_purchase_receipts');
    }
};

