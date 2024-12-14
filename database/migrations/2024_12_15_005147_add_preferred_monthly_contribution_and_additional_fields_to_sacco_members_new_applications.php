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
            // Adding the 'preferred_monthly_contribution' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'preferred_monthly_contribution')) {
                $table->decimal('preferred_monthly_contribution', 10, 2)->nullable()->after('dependents')->comment('Preferred Monthly Contribution in Ksh');
            }

            // Adding the 'reason_for_joining' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'reason_for_joining')) {
                $table->text('reason_for_joining')->nullable()->after('preferred_monthly_contribution')->comment('Reason for joining the SACCO');
            }

            // Adding the 'dependents' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'dependents')) {
                $table->integer('dependents')->nullable()->after('monthly_income')->comment('Number of dependents');
            }

            // Adding the 'occupation' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'occupation')) {
                $table->string('occupation', 255)->nullable()->after('physical_location')->comment('Occupation');
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
            if (Schema::hasColumn('sacco_members_new_applications', 'preferred_monthly_contribution')) {
                $table->dropColumn('preferred_monthly_contribution');
            }
            if (Schema::hasColumn('sacco_members_new_applications', 'reason_for_joining')) {
                $table->dropColumn('reason_for_joining');
            }
            if (Schema::hasColumn('sacco_members_new_applications', 'dependents')) {
                $table->dropColumn('dependents');
            }
            if (Schema::hasColumn('sacco_members_new_applications', 'occupation')) {
                $table->dropColumn('occupation');
            }
        });
    }
}