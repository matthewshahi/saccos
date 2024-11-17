<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDobToSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Add the column only if it doesn't already exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'dob')) {
                $table->date('dob')->nullable()->after('last_name');
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
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Drop the column only if it exists
            if (Schema::hasColumn('sacco_members_new_applications', 'dob')) {
                $table->dropColumn('dob');
            }
        });
    }
}