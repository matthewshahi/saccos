<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {

            // Add numeric reference instead of text
            if (!Schema::hasColumn('sacco_matatus_operators', 'stage_chair_id')) {
                $table->unsignedBigInteger('stage_chair_id')
                    ->nullable()
                    ->after('stage_id');
            }

            // Remove old text field if exists
            if (Schema::hasColumn('sacco_matatus_operators', 'stage_chair')) {
                $table->dropColumn('stage_chair');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->dropColumn('stage_chair_id');

            // Optionally restore text field
            $table->string('stage_chair', 255)->nullable();
        });
    }
};
