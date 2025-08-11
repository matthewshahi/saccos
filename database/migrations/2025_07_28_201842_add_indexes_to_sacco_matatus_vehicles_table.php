<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToSaccoMatatusVehiclesTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_vehicles', function (Blueprint $table) {
            $table->index('vehicles_registration_number');
            $table->index('vehicles_make');
            $table->index('vehicles_model');
            $table->index('vehicles_year');
            $table->index('vehicles_chassis_number');
            $table->index('vehicles_insurance_provider');
            $table->index('vehicles_insurance_expiry');
            $table->index('vehicles_last_inspection_date');
            $table->index('vehicles_psv_license_number');
            $table->index('vehicles_psv_expiry');
            $table->index('vehicles_route_name');
            $table->index('vehicles_member_id');
            $table->index('vehicles_status');
            $table->index(['vehicles_make', 'vehicles_model']); // Composite index
            $table->index(['vehicles_status', 'vehicles_route_name']); // Composite index
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_vehicles', function (Blueprint $table) {
            $table->dropIndex(['vehicles_registration_number']);
            $table->dropIndex(['vehicles_make']);
            $table->dropIndex(['vehicles_model']);
            $table->dropIndex(['vehicles_year']);
            $table->dropIndex(['vehicles_chassis_number']);
            $table->dropIndex(['vehicles_insurance_provider']);
            $table->dropIndex(['vehicles_insurance_expiry']);
            $table->dropIndex(['vehicles_last_inspection_date']);
            $table->dropIndex(['vehicles_psv_license_number']);
            $table->dropIndex(['vehicles_psv_expiry']);
            $table->dropIndex(['vehicles_route_name']);
            $table->dropIndex(['vehicles_member_id']);
            $table->dropIndex(['vehicles_status']);
            $table->dropIndex(['vehicles_make', 'vehicles_model']);
            $table->dropIndex(['vehicles_status', 'vehicles_route_name']);
        });
    }
}