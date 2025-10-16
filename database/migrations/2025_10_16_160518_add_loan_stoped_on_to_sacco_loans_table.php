<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_loans', 'loan_stoped_on')) {
                $table->timestamp('loan_stoped_on')->nullable()->after('loan_stoped')
                      ->comment('Date when the loan was stopped or cleared via top-up');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_loans', 'loan_stoped_on')) {
                $table->dropColumn('loan_stoped_on');
            }
        });
    }
};
