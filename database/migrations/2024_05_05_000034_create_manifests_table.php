<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManifestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('manifest_number', 100);
            $table->string('file_path', 500)->nullable();
            $table->string('cargo_company', 255)->nullable();
            $table->unsignedBigInteger('customs_office_id')->nullable();
            $table->date('date_of_departure')->nullable();
            $table->string('shipping_agent_code', 100)->nullable();
            $table->string('exporter_name', 255)->nullable();
            $table->text('exporter_address')->nullable();
            $table->unsignedBigInteger('loading_port_id')->nullable();
            $table->string('master_air_bill', 255)->nullable();
            $table->string('voyage_number', 255)->nullable();
            $table->string('carrier_code', 255)->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->datetime('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'manifest_number']);
            $table->index('tenant_id');
            $table->index('customs_office_id');
            $table->index('loading_port_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customs_office_id')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('loading_port_id')->references('id')->on('loading_ports')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('manifests');
    }
}