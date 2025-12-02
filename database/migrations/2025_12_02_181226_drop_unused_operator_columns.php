<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {

            // Check and drop old/unused columns safely
            if (Schema::hasColumn('sacco_matatus_operators', 'id_number')) {
                $table->dropColumn('id_number');
            }

            if (Schema::hasColumn('sacco_matatus_operators', 'license_number')) {
                $table->dropColumn('license_number');
            }

            if (Schema::hasColumn('sacco_matatus_operators', 'stage_id')) {
                $table->dropColumn('stage_id');
            }

            if (Schema::hasColumn('sacco_matatus_operators', 'stage_chair_id')) {
                $table->dropColumn('stage_chair_id');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {

            // Recreate columns if rolling back
            if (!Schema::hasColumn('sacco_matatus_operators', 'id_number')) {
                $table->string('id_number', 20)->nullable();
            }

            if (!Schema::hasColumn('sacco_matatus_operators', 'license_number')) {
                $table->string('license_number', 50)->nullable();
            }

            if (!Schema::hasColumn('sacco_matatus_operators', 'stage_id')) {
                $table->unsignedBigInteger('stage_id')->nullable();
            }

            if (!Schema::hasColumn('sacco_matatus_operators', 'stage_chair_id')) {
                $table->unsignedBigInteger('stage_chair_id')->nullable();
            }
        });
    }
};
