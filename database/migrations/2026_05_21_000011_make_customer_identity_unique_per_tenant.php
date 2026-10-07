<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->index('email', 'users_email_index');
            $table->index(['tenant_id', 'user_type', 'email'], 'users_tenant_type_email_index');
            $table->index(['tenant_id', 'user_type', 'phone'], 'users_tenant_type_phone_index');
            $table->index(['tenant_id', 'user_type', 'trn'], 'users_tenant_type_trn_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_type_trn_index');
            $table->dropIndex('users_tenant_type_phone_index');
            $table->dropIndex('users_tenant_type_email_index');
            $table->dropIndex('users_email_index');
            $table->unique('email');
        });
    }
};
