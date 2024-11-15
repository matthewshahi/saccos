<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTermsToSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->boolean('terms')->default(false)->after('national_id'); // Adjust 'after' position as needed
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
            $table->dropColumn('terms');
        });
    }
}
