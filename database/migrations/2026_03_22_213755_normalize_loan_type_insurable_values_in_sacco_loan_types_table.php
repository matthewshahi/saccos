<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing values to what the code actually uses: Y / N
        DB::table('sacco_loan_types')
            ->where('loan_type_insurable', '1')
            ->update(['loan_type_insurable' => 'Y']);

        DB::table('sacco_loan_types')
            ->where(function ($q) {
                $q->whereNull('loan_type_insurable')
                  ->orWhere('loan_type_insurable', '')
                  ->orWhere('loan_type_insurable', '0');
            })
            ->update(['loan_type_insurable' => 'N']);

        // Enforce consistent default for new rows
        DB::statement("
            ALTER TABLE sacco_loan_types
            MODIFY loan_type_insurable VARCHAR(1) NOT NULL DEFAULT 'Y'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE sacco_loan_types
            MODIFY loan_type_insurable VARCHAR(1) DEFAULT 'Y'
        ");

        // Optional rollback mapping
        DB::table('sacco_loan_types')
            ->where('loan_type_insurable', 'Y')
            ->update(['loan_type_insurable' => '1']);

        DB::table('sacco_loan_types')
            ->where('loan_type_insurable', 'N')
            ->update(['loan_type_insurable' => '0']);
    }
};