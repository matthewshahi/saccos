<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreferredMonthlyContributionAndAdditionalFieldsToSaccoMembersNewApplications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Adding the 'preferred_monthly_contribution' column
            $table->decimal('preferred_monthly_contribution', 10, 2)->nullable()->after('dependents')->comment('Preferred Monthly Contribution in Ksh');

            // Adding other missing fields
            $table->text('reason_for_joining')->nullable()->after('preferred_monthly_contribution')->comment('Reason for joining the SACCO');
            $table->integer('dependents')->nullable()->after('monthly_income')->comment('Number of dependents');
            $table->string('occupation', 255)->nullable()->after('physical_location')->comment('Occupation');
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
            $table->dropColumn('preferred_monthly_contribution');
            $table->dropColumn('reason_for_joining');
            $table->dropColumn('dependents');
            $table->dropColumn('occupation');
        });
    }
}