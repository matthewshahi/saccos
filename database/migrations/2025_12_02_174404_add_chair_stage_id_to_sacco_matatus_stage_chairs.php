<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_stage_chairs', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_matatus_stage_chairs', 'chair_stage_id')) {
                $table->unsignedBigInteger('chair_stage_id')->nullable()->after('id');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_stage_chairs', function (Blueprint $table) {
            $table->dropColumn('chair_stage_id');
        });
    }
};
