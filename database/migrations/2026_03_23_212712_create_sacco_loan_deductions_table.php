<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacco_loan_deductions')) {
            Schema::create('sacco_loan_deductions', function (Blueprint $table) {
                $table->increments('loan_deduction_id');

                // Live loan link
                $table->integer('loan_deduction_loan_id')->nullable();

                // Traceability back to batch tables
                $table->integer('loan_deduction_batch_id')->nullable();
                $table->integer('loan_deduction_batch_trans_id')->nullable();
                $table->integer('loan_deduction_batch_deduction_id')->nullable();

                // Deduction identity
                $table->integer('loan_deduction_deduction_type_id')->nullable();
                $table->string('loan_deduction_name', 250)->nullable();
                $table->string('loan_deduction_code', 100)->nullable();
                $table->string('loan_deduction_value_type', 20)->nullable();
                $table->string('loan_deduction_effect', 30)->nullable();
                $table->integer('loan_deduction_account')->nullable();

                // Financials
                $table->double('loan_deduction_amount')->default(0);
                $table->string('loan_deduction_description', 250)->nullable();

                // Audit trail
                $table->string('loan_deduction_updated', 1)->default('N');
                $table->integer('loan_deduction_by')->nullable();
                $table->timestamp('loan_deduction_on')->nullable()->useCurrent();
                $table->string('loan_deduction_ip', 100)->nullable();

                // Soft delete
                $table->string('loan_deduction_deleted', 1)->default('N');
                $table->integer('loan_deduction_deleted_by')->nullable();
                $table->dateTime('loan_deduction_deleted_on')->nullable();
                $table->string('loan_deduction_deleted_ip', 100)->nullable();

                $table->index('loan_deduction_loan_id', 'idx_loan_deduction_loan_id');
                $table->index('loan_deduction_batch_id', 'idx_loan_deduction_batch_id');
                $table->index('loan_deduction_batch_trans_id', 'idx_loan_deduction_batch_trans_id');
                $table->index('loan_deduction_deduction_type_id', 'idx_loan_deduction_type_id');
                $table->index('loan_deduction_account', 'idx_loan_deduction_account');
                $table->index(
                    ['loan_deduction_loan_id', 'loan_deduction_deleted'],
                    'idx_loan_deduction_loan_deleted'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_loan_deductions');
    }
};