<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperatorVehicleAssignmentsTable extends Migration
{
    public function up()
    {
        Schema::create('operator_vehicle_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('operator_id');
            $table->unsignedBigInteger('vehicle_id');

            $table->enum('role', ['driver', 'conductor'])->default('driver');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable(); // Null = still active
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('operator_id');
            $table->index('vehicle_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('operator_vehicle_assignments');
    }
}
