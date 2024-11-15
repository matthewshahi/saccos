<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDobAndTermsToSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Add date of birth column
            $table->date('dob')->nullable()->after('last_name');
            // Add terms column as a boolean to indicate terms acceptance
            $table->boolean('terms')->default(false)->after('location');
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
            $table->dropColumn('dob');
            $table->dropColumn('terms');
        });
    }
}