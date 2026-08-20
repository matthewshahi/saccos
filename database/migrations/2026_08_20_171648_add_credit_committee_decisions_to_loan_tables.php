<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Credit Committee approval decision snapshots
     * to pending loan applications and final loans.
     *
     * Both fields default to NULL.
     */
    public function up(): void
    {
        Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
            $table->json('batch_trans_credit_committee_decisions')
                ->nullable();
        });

        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->json('loan_credit_committee_decisions')
                ->nullable();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
            $table->dropColumn('batch_trans_credit_committee_decisions');
        });

        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->dropColumn('loan_credit_committee_decisions');
        });
    }
};