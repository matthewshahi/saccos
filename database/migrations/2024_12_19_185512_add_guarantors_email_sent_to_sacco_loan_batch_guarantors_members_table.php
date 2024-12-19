<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
        {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->char('guarantors_email_sent', 1)->default('N')->after('guarantors_approved');
            });
        }

        public function down()
        {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->dropColumn('guarantors_email_sent');
            });
        }
};
