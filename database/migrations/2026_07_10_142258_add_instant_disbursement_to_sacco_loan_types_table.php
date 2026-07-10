<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->boolean('loan_type_instant_disbursement')
                ->default(false)
                ->after('loan_type_instant_qualification');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->dropColumn('loan_type_instant_disbursement');
        });
    }
};