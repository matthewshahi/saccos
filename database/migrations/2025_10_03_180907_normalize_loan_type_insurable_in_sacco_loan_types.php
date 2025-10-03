<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize loan_type_insurable column
        DB::table('sacco_loan_types')
            ->whereNull('loan_type_insurable')
            ->orWhere('loan_type_insurable', '!=', 'Y')
            ->update(['loan_type_insurable' => 'N']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional rollback - set everything back to 'Y'
        // (only do this if you want to undo the change)
        DB::table('sacco_loan_types')->update([
            'loan_type_insurable' => 'Y'
        ]);
    }
};