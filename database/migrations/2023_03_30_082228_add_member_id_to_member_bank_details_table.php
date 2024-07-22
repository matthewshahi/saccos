<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMemberIdToMemberBankDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_member_bank_details', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_member_id')->after('id');
        });
    }

    public function down()
    {
        Schema::table('sacco_member_bank_details', function (Blueprint $table) {

            $table->dropColumn('bank_member_id');
        });
    }
}
