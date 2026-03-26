<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_loans', 'loan_batch_trans_id')) {
                $table->unsignedInteger('loan_batch_trans_id')
                    ->nullable()
                    ->after('loan_batch_no');
            }

            if (!Schema::hasColumn('sacco_loans', 'loan_requested_amount')) {
                $table->double('loan_requested_amount')
                    ->default(0)
                    ->after('loan_loan_category');
            }

            if (!Schema::hasColumn('sacco_loans', 'loan_other_additions')) {
                $table->double('loan_other_additions')
                    ->default(0)
                    ->after('loan_commision');
            }

            if (!Schema::hasColumn('sacco_loans', 'loan_other_deductions')) {
                $table->double('loan_other_deductions')
                    ->default(0)
                    ->after('loan_other_additions');
            }

            if (!Schema::hasColumn('sacco_loans', 'loan_net_disbursement')) {
                $table->double('loan_net_disbursement')
                    ->default(0)
                    ->after('loan_other_deductions');
            }

            if (!Schema::hasColumn('sacco_loans', 'loan_charges_snapshot')) {
                $table->text('loan_charges_snapshot')
                    ->nullable()
                    ->after('loan_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('sacco_loans', 'loan_batch_trans_id')) {
                $drops[] = 'loan_batch_trans_id';
            }

            if (Schema::hasColumn('sacco_loans', 'loan_requested_amount')) {
                $drops[] = 'loan_requested_amount';
            }

            if (Schema::hasColumn('sacco_loans', 'loan_other_additions')) {
                $drops[] = 'loan_other_additions';
            }

            if (Schema::hasColumn('sacco_loans', 'loan_other_deductions')) {
                $drops[] = 'loan_other_deductions';
            }

            if (Schema::hasColumn('sacco_loans', 'loan_net_disbursement')) {
                $drops[] = 'loan_net_disbursement';
            }

            if (Schema::hasColumn('sacco_loans', 'loan_charges_snapshot')) {
                $drops[] = 'loan_charges_snapshot';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};