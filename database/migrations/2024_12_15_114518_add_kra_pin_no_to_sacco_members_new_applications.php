<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKraPinNoToSaccoMembersNewApplications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->string('kra_pin_no', 20)->nullable()->after('national_id')->comment('KRA PIN Number');
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
            $table->dropColumn('kra_pin_no');
        });
    }
}