<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            $table->unsignedBigInteger('fosa_type_id')->nullable()->after('fosa_member_id');
        });
    }

    public function down()
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            $table->dropColumn('fosa_type_id');
        });
    }
};