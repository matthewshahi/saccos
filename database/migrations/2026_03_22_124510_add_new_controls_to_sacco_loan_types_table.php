<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->integer('loan_type_max_qualification_period')
                ->nullable()
                ->after('loan_type_qualification_period');

            $table->string('loan_type_crb_required', 1)
                ->default('N')
                ->after('loan_type_max_qualification_period');

            $table->double('loan_type_crb_charge')
                ->default(0)
                ->after('loan_type_crb_required');

            $table->string('loan_type_crb_effect', 30)
                ->nullable()
                ->after('loan_type_crb_charge');
                // Expected values:
                // ADD_TO_LOAN
                // DEDUCT_FROM_DISBURSEMENT

            $table->string('loan_type_commission_required', 1)
                ->default('N')
                ->after('loan_type_crb_effect');

            $table->string('loan_type_commission_type', 20)
                ->nullable()
                ->after('loan_type_commission_required');
                // Expected values:
                // FIXED
                // PERCENT

            $table->double('loan_type_commission_value')
                ->default(0)
                ->after('loan_type_commission_type');

            $table->string('loan_type_commission_effect', 30)
                ->nullable()
                ->after('loan_type_commission_value');
                // Expected values:
                // ADD_TO_LOAN
                // DEDUCT_FROM_DISBURSEMENT
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->dropColumn([
                'loan_type_max_qualification_period',
                'loan_type_crb_required',
                'loan_type_crb_charge',
                'loan_type_crb_effect',
                'loan_type_commission_required',
                'loan_type_commission_type',
                'loan_type_commission_value',
                'loan_type_commission_effect',
            ]);
        });
    }
};