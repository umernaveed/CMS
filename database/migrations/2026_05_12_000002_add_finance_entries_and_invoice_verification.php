<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->enum('type', ['income', 'expense']);
            $table->string('category', 120);
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('entry_date');
            $table->string('reference_number', 150)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'entry_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('verified_package_ids')->nullable()->after('notes');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_package_ids');
            $table->datetime('verified_at')->nullable()->after('verified_by');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_package_ids', 'verified_by', 'verified_at']);
        });

        Schema::dropIfExists('finance_entries');
    }
};
