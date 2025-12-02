<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // For sacco_matatus_stages
        Schema::table('sacco_matatus_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_matatus_stages', 'deleted')) {
                $table->char('deleted', 1)
                      ->default('N')
                      ->after('stage_name');
            }
        });

        // For sacco_matatus_stage_chairs
        Schema::table('sacco_matatus_stage_chairs', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_matatus_stage_chairs', 'deleted')) {
                $table->char('deleted', 1)
                      ->default('N')
                      ->after('chair_stage_id');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_stages', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_matatus_stages', 'deleted')) {
                $table->dropColumn('deleted');
            }
        });

        Schema::table('sacco_matatus_stage_chairs', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_matatus_stage_chairs', 'deleted')) {
                $table->dropColumn('deleted');
            }
        });
    }
};
