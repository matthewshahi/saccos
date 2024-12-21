<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGuarantorsEmailSentToSaccoLoanBatchGuarantorsMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->char('guarantors_email_sent', 1)->default('N');
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
        if (Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->dropColumn('guarantors_email_sent');
            });
        }
    }
}