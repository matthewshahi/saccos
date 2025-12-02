<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sacco_matatus_stage_chairs')) {

            Schema::create('sacco_matatus_stage_chairs', function (Blueprint $table) {

                $table->bigIncrements('id');  // Primary key

                $table->unsignedBigInteger('stage_id')->nullable();

                // Chairperson name
                $table->string('chair_name', 255);

                // Optional contact
                $table->string('chair_phone', 20)->nullable();

                // Optional extra fields
                $table->string('chair_id_number', 20)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('sacco_matatus_stage_chairs');
    }
};
