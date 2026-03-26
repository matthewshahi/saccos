<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loan_batch_trans', function (Blueprint $table) {
            $table->double('batch_trans_other_deductions')
                ->default(0)
                ->after('batch_trans_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loan_batch_trans', function (Blueprint $table) {
            $table->dropColumn('batch_trans_other_deductions');
        });
    }
};