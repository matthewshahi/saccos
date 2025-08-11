<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sacco_matatus_operator_vehicle_assignments', function (Blueprint $table) {
            $table->id();

            // Shortened field names
            $table->unsignedBigInteger('v_assignment_operator_id');
            $table->unsignedBigInteger('v_assignment_vehicle_id');

            $table->date('v_assignment_start_date')->nullable();
            $table->date('v_assignment_end_date')->nullable();

            $table->string('v_assignment_status')->default('assigned'); // assigned, relieved, suspended, etc.

            $table->timestamps();

            // Short custom index names
            $table->index(
                ['v_assignment_operator_id', 'v_assignment_vehicle_id'],
                'idx_op_vehicle'
            );
            $table->index('v_assignment_status', 'idx_status');
            $table->index('v_assignment_start_date', 'idx_start_date');
            $table->index('v_assignment_end_date', 'idx_end_date');

            // Shortened foreign key constraint names
            $table->foreign('v_assignment_operator_id', 'fk_op')
                ->references('id')
                ->on('sacco_matatus_operators');

            $table->foreign('v_assignment_vehicle_id', 'fk_vehicle')
                ->references('id')
                ->on('sacco_matatus_vehicles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_matatus_operator_vehicle_assignments');
    }
};