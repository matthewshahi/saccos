<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGuarantorsEmailsSentToSaccoLoanBatchGuarantorsMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('sacco_loan_batch_guarantors_members')) {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                // Check and add 'guarantors_approved' if it does not exist
                if (!Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_approved')) {
                    $table->char('guarantors_approved', 1)->default('N');
                }

                // Check and add 'guarantors_email_sent' if it does not exist
                if (!Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
                    $table->char('guarantors_email_sent', 1)->default('N')->after('guarantors_approved');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('sacco_loan_batch_guarantors_members')) {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                // Drop 'guarantors_email_sent' if it exists
                if (Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
                    $table->dropColumn('guarantors_email_sent');
                }

                // Drop 'guarantors_approved' if it was added by this migration
                if (Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_approved')) {
                    $table->dropColumn('guarantors_approved');
                }
            });
        }
    }
}