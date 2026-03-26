<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_loan_batch_trans_deductions', function (Blueprint $table) {
            $table->charset = 'latin1';
            $table->collation = 'latin1_swedish_ci';

            $table->increments('batch_trans_deduction_id');

            $table->integer('batch_trans_deduction_batch_id')->nullable();
            $table->integer('batch_trans_deduction_batch_trans_id')->nullable();

            $table->integer('batch_trans_deduction_deduction_type_id')->nullable();

            // snapshot fields from sacco_loan_deductions_types
            $table->string('batch_trans_deduction_name', 250)->nullable();
            $table->string('batch_trans_deduction_code', 100)->nullable();
            $table->string('batch_trans_deduction_value_type', 20)->nullable();   // FIXED / PERCENT
            $table->string('batch_trans_deduction_effect', 30)->nullable();       // ADD_TO_LOAN / DEDUCT_FROM_DISBURSEMENT
            $table->integer('batch_trans_deduction_account')->nullable();

            // actual applied value for this temp batch transaction
            $table->double('batch_trans_deduction_amount')->default(0);
            $table->string('batch_trans_deduction_description', 250)->nullable();

            // transfer/finalization tracking
            $table->string('batch_trans_deduction_updated', 1)->default('N');

            // audit
            $table->integer('batch_trans_deduction_by')->nullable();
            $table->timestamp('batch_trans_deduction_on')->nullable()->useCurrent();
            $table->string('batch_trans_deduction_ip', 100)->nullable();

            // soft delete audit
            $table->string('batch_trans_deduction_deleted', 1)->default('N');
            $table->integer('batch_trans_deduction_deleted_by')->nullable();
            $table->dateTime('batch_trans_deduction_deleted_on')->nullable();
            $table->string('batch_trans_deduction_deleted_ip', 100)->nullable();

            $table->index(['batch_trans_deduction_batch_id'], 'idx_batch_ded_batch_id');
            $table->index(['batch_trans_deduction_batch_trans_id'], 'idx_batch_ded_trans_id');
            $table->index(['batch_trans_deduction_deduction_type_id'], 'idx_batch_ded_type_id');
            $table->index(['batch_trans_deduction_deleted'], 'idx_batch_ded_deleted');
            $table->index(
                ['batch_trans_deduction_batch_trans_id', 'batch_trans_deduction_deleted'],
                'idx_batch_ded_trans_deleted'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_loan_batch_trans_deductions');
    }
};