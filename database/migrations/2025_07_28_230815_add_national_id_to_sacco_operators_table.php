<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNationalIdToSaccoOperatorsTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_operators', function (Blueprint $table) {
            $table->string('national_id')->nullable()->after('phone');
        });
    }

    public function down()
    {
        Schema::table('sacco_operators', function (Blueprint $table) {
            $table->dropColumn('national_id');
        });
    }
}