<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sacco_loans', 'loan_suspended_on')) {
            Schema::table('sacco_loans', function (Blueprint $table) {
                $table->dateTime('loan_suspended_on')
                    ->nullable()
                    ->after('loan_stoped');
            });
        }

        if (!Schema::hasColumn('sacco_loans', 'loan_suspended_reason')) {
            Schema::table('sacco_loans', function (Blueprint $table) {
                $table->string('loan_suspended_reason', 255)
                    ->nullable()
                    ->after('loan_suspended_on');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sacco_loans', 'loan_suspended_reason')) {
            Schema::table('sacco_loans', function (Blueprint $table) {
                $table->dropColumn('loan_suspended_reason');
            });
        }

        if (Schema::hasColumn('sacco_loans', 'loan_suspended_on')) {
            Schema::table('sacco_loans', function (Blueprint $table) {
                $table->dropColumn('loan_suspended_on');
            });
        }
    }
};
