<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sacco_matatus_stages')) {

            Schema::create('sacco_matatus_stages', function (Blueprint $table) {

                $table->bigIncrements('id');

                // Stage name e.g. “Githurai 45 Main Stage”
                $table->string('stage_name', 255)->unique();

                // Optional: stage chair
                $table->string('stage_chair', 255)->nullable();

                // Optional: geo location for future expansion
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();

                // Optional: who created this stage (member/ admin)
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('sacco_matatus_stages');
    }
};
