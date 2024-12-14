<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailFieldsToSaccoMembersNewApplications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->char('email_sent', 1)->default('N')->comment('Indicates if the email has been sent (Y/N)');
            $table->string('email_key_unique', 255)->unique()->nullable()->comment('Unique key for email verification or additional information link');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->dropColumn('email_sent');
            $table->dropColumn('email_key_unique');
        });
    }
}