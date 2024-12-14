<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNextOfKinIdOrCertNoToSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->json('next_of_kin_id_or_cert_no')->nullable()->after('next_of_kin_phone')->comment('JSON field for Next of Kin ID No or Birth Certificate No');
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
            $table->dropColumn('next_of_kin_id_or_cert_no');
        });
    }
}