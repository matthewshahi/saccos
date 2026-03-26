<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_loan_deductions_types', function (Blueprint $table) {
            $table->charset = 'latin1';
            $table->collation = 'latin1_swedish_ci';

            $table->increments('deduction_type_id');

            $table->string('deduction_type_name', 250);
            $table->string('deduction_type_code', 100)->nullable();
            $table->text('deduction_type_description')->nullable();

            // FIXED or PERCENT
            $table->string('deduction_type_value_type', 20)->default('FIXED');

            // Default amount or percent
            $table->double('deduction_type_default_value')->default(0);

            // ADD_TO_LOAN or DEDUCT_FROM_DISBURSEMENT
            $table->string('deduction_type_effect', 30)->nullable();

            // Posting account
            $table->integer('deduction_type_account')->nullable();

            $table->tinyInteger('deduction_type_active')->default(1);
            $table->string('deduction_type_deleted', 1)->default('N');

            $table->integer('deduction_type_by')->nullable();
            $table->timestamp('deduction_type_transdate')->nullable()->useCurrent();
            $table->string('deduction_type_ip', 100)->nullable();

            $table->integer('deduction_type_deleted_by')->nullable();
            $table->dateTime('deduction_type_deleted_on')->nullable();
            $table->string('deduction_type_deleted_ip', 100)->nullable();

            $table->index(['deduction_type_deleted', 'deduction_type_name'], 'idx_deduction_type_status_name');
            $table->index(['deduction_type_active', 'deduction_type_deleted'], 'idx_deduction_type_active_deleted');
            $table->index('deduction_type_code', 'idx_deduction_type_code');
            $table->index('deduction_type_account', 'idx_deduction_type_account');
        });

        DB::table('sacco_loan_deductions_types')->insert([
            [
                'deduction_type_name' => 'CRB Check',
                'deduction_type_code' => 'CRB',
                'deduction_type_description' => 'Credit reference bureau check charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Processing Fee',
                'deduction_type_code' => 'PROCESSING',
                'deduction_type_description' => 'Loan processing charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Admin Fee',
                'deduction_type_code' => 'ADMIN',
                'deduction_type_description' => 'Administrative loan charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Insurance',
                'deduction_type_code' => 'INSURANCE',
                'deduction_type_description' => 'Loan insurance charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'ADD_TO_LOAN',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Commission',
                'deduction_type_code' => 'COMMISSION',
                'deduction_type_description' => 'Loan commission charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Legal Fee',
                'deduction_type_code' => 'LEGAL',
                'deduction_type_description' => 'Legal documentation charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Valuation Fee',
                'deduction_type_code' => 'VALUATION',
                'deduction_type_description' => 'Security or collateral valuation charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Appraisal Fee',
                'deduction_type_code' => 'APPRAISAL',
                'deduction_type_description' => 'Loan appraisal charge',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Top Up Offset',
                'deduction_type_code' => 'TOPUP_OFFSET',
                'deduction_type_description' => 'Amount retained to clear previous loan during top-up',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
            [
                'deduction_type_name' => 'Other Deduction',
                'deduction_type_code' => 'OTHER',
                'deduction_type_description' => 'Any other manually defined loan deduction',
                'deduction_type_value_type' => 'FIXED',
                'deduction_type_default_value' => 0,
                'deduction_type_effect' => 'DEDUCT_FROM_DISBURSEMENT',
                'deduction_type_account' => null,
                'deduction_type_active' => 1,
                'deduction_type_deleted' => 'N',
                'deduction_type_by' => 1,
                'deduction_type_ip' => '127.0.0.1',
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_loan_deductions_types');
    }
};