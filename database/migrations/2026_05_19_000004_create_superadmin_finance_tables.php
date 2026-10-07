<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_finance_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('category', 120);
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('entry_date');
            $table->string('reference_number', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'entry_date']);
        });

        Schema::create('superadmin_billing_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 80)->nullable()->unique();
            $table->decimal('amount_usd', 12, 2)->default(0);
            $table->decimal('amount_jmd', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_billing_charges');
        Schema::dropIfExists('superadmin_finance_entries');
    }
};
