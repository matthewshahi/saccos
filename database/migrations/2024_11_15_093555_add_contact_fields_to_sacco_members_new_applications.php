<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContactFieldsToSaccoMembersNewApplications extends Migration
{
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->boolean('contacted')->nullable()->default(false);
            $table->string('contacted_by')->nullable();
            $table->date('contacted_on')->nullable();
            $table->text('comments')->nullable();
        });
    }

    public function down()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->dropColumn(['contacted', 'contacted_by', 'contacted_on', 'comments']);
        });
    }
}