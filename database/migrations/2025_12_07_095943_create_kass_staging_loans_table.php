<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('kass_staging_loans', function (Blueprint $table) {
        $table->id();
        $table->string('raw_name')->nullable();
        $table->string('member_identifier')->nullable();
        $table->string('adm_no')->nullable();
        $table->string('company')->nullable();

        // Loan attributes
        $table->string('loan_type')->nullable(); // NORMAL, SCHOOL, EMERGENCY, etc
        $table->integer('year')->nullable();
        $table->string('month')->nullable();

        $table->decimal('principal_disbursed', 15, 2)->nullable();
        $table->decimal('repayment_amount', 15, 2)->nullable();
        $table->decimal('interest_amount', 15, 2)->nullable();
        $table->string('period_index')->nullable();

        $table->string('source_file')->nullable();
        $table->unsignedBigInteger('matched_member_id')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kass_staging_loans');
    }
};
