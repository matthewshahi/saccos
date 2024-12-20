<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->string('loan_email_sent', 1)->default('N')->after('loan_stoped');
        });
    }
    
    public function down()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->dropColumn('loan_email_sent');
        });
    }
};
