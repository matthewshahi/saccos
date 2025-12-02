<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {

            if (!Schema::hasColumn('sacco_matatus_operators', 'stage_id')) {
                $table->unsignedBigInteger('stage_id')
                    ->nullable()
                    ->after('introduced_by_member_id'); 
            }

        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->dropColumn('stage_id');
        });
    }
};
