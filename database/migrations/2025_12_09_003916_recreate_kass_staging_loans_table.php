<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Drop old version completely
        Schema::dropIfExists('kass_staging_loans');

        // Create new, fully improved structure
        Schema::create('kass_staging_loans', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Member identity
            $table->string('raw_name')->nullable();
            $table->string('member_identifier')->nullable(); // PFNO if ever found
            $table->string('adm_no')->nullable();
            $table->string('company')->nullable();

            // Loan definition fields
            $table->string('loan_type')->nullable();         // NORMAL, NORMAL A, EMERGENCY, etc.
            $table->string('loan_key')->nullable();          // Unique grouping key for loan streams

            // New enhancement fields
            $table->decimal('loan_start_balance', 18, 2)->nullable(); // First DR value for the loan
            $table->integer('loan_month_index')->nullable();          // Sequential index of months per loan

            // Monthly loan records (per sheet)
            $table->integer('year')->nullable();
            $table->string('month', 10)->nullable();

            $table->decimal('outstanding_balance', 18, 2)->nullable(); // DR
            $table->decimal('principal_paid', 18, 2)->nullable();       // CR
            $table->decimal('interest_paid', 18, 2)->nullable();        // INT

            // Loan metadata (tenure column)
            $table->integer('period_index')->nullable(); // PERIOD from sheet (carried down)

            // Audit source
            $table->string('source_file')->nullable();
            $table->longText('raw_row_json')->nullable();

            $table->timestamps();

            // Indexes for fast grouping
            $table->index(['adm_no', 'company']);
            $table->index(['loan_type']);
            $table->index(['loan_key']);
            $table->index(['year', 'month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('kass_staging_loans');
    }
};
