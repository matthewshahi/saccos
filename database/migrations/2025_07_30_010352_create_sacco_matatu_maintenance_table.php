<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoMatatuMaintenanceTable extends Migration
{
    public function up()
    {
        Schema::create('sacco_matatu_maintenance', function (Blueprint $table) {
            $table->id();

            // Vehicle and basic tracking
            $table->unsignedBigInteger('maintenance_vehicle_id');
            $table->date('maintenance_date');
            $table->string('maintenance_type')->comment('e.g., Routine, Breakdown, Overhaul');
            $table->boolean('maintenance_internal')->default(false)->comment('True if done by SACCO’s own mechanic');

            // Description & report details
            $table->text('maintenance_description');
            $table->string('maintenance_fault_reported_by')->nullable();
            $table->integer('maintenance_odometer_reading')->nullable();
            $table->date('maintenance_next_due_date')->nullable();

            // Vendor & cost tracking
            $table->string('maintenance_vendor')->nullable()->comment('Garage or mechanic');
            $table->decimal('maintenance_cost', 12, 2);
            $table->string('maintenance_payment_mode')->nullable();
            $table->string('maintenance_doc_ref')->nullable()->comment('Receipt or invoice reference');
            $table->string('maintenance_parts_used')->nullable()->comment('Comma-separated if needed');
            $table->string('maintenance_attachment_path')->nullable()->comment('Path to uploaded receipt');

            // Workflow & approval
            $table->string('maintenance_status')->default('completed')->comment('e.g., pending, completed');
            $table->unsignedBigInteger('maintenance_approved_by')->nullable();

            // Audit
            $table->unsignedBigInteger('maintenance_recorded_by');
            $table->string('maintenance_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sacco_matatu_maintenance');
    }
}