<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoOperatorsTable extends Migration
{
    public function up()
    {
        Schema::create('sacco_operators', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('id_number')->nullable();
            $table->string('photo')->nullable(); // path to image
            $table->string('badge_number')->nullable(); // internal or NTSA badge
            $table->string('license_number')->nullable();
            $table->enum('operator_type', ['driver', 'conductor'])->default('driver');

            $table->unsignedBigInteger('introduced_by_member_id')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // No foreign key constraint, but index for lookups
            $table->index('introduced_by_member_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sacco_operators');
    }
}