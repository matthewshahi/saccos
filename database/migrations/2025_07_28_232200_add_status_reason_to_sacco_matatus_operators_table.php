<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusReasonToSaccoMatatusOperatorsTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->string('status_reason', 100)->nullable()->after('status'); // e.g. suspended, banned, etc.
        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->dropColumn('status_reason');
        });
    }
}