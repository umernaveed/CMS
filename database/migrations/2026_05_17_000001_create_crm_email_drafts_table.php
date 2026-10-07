<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmEmailDraftsTable extends Migration
{
    public function up()
    {
        Schema::create('crm_email_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('target', 50)->default('all');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('email_group', 100)->nullable();
            $table->string('subject', 255);
            $table->longText('body');
            $table->enum('status', ['draft', 'sent'])->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('notification_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'target']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('outlets')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_email_drafts');
    }
}
