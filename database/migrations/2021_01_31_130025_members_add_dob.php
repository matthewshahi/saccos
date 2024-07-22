<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MembersAddDob extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        Schema::table('sacco_members', function (Blueprint $table) {
            // Check if the column already exists
            if (!Schema::hasColumn('sacco_members', 'member_last_mobile_login')) {
                $table->datetime('member_last_mobile_login')
                    ->nullable();
                    //->after('member_dob');
            }
            else{
                $table->dateTime('member_last_mobile_login')->nullable();
            }
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
