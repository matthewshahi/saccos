<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoMatatusVehiclesTable extends Migration
{
    public function up()
    {
        Schema::create('sacco_matatus_vehicles', function (Blueprint $table) {
            $table->id();

            // Core identification
            $table->string('vehicles_registration_number')->unique();
            $table->string('vehicles_make')->nullable();                 // e.g., Toyota
            $table->string('vehicles_model')->nullable();                // e.g., Hiace
            $table->integer('vehicles_year')->nullable();
            $table->string('vehicles_chassis_number')->nullable();

            // Insurance
            $table->string('vehicles_insurance_provider')->nullable();
            $table->date('vehicles_insurance_expiry')->nullable();

            // PSV & inspection
            $table->date('vehicles_last_inspection_date')->nullable();
            $table->string('vehicles_psv_license_number')->nullable();
            $table->date('vehicles_psv_expiry')->nullable();

            // Route assignment
            $table->string('vehicles_route_name')->nullable();

            // Owner (SACCO Member)
            $table->unsignedBigInteger('vehicles_member_id')->nullable(); // From `members` table

            // Status
            $table->enum('vehicles_status', [
                'pending_approval',
                'active',
                'inactive',
                'suspended',
                'under_maintenance',
                'decommissioned',
                'blacklisted'
            ])->default('pending_approval');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sacco_matatus_vehicles');
    }
}
