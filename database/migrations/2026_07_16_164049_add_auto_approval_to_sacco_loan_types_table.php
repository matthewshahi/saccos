<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sacco_loan_types', 'loan_type_auto_approval')) {
            Schema::table('sacco_loan_types', function (Blueprint $table) {
                $table->boolean('loan_type_auto_approval')
                    ->default(false)
                    ->after('loan_type_instant_qualification')
                    ->comment(
                        'Automatically approve eligible self-service loan applications when all applicable conditions are met'
                    );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sacco_loan_types', 'loan_type_auto_approval')) {
            Schema::table('sacco_loan_types', function (Blueprint $table) {
                $table->dropColumn('loan_type_auto_approval');
            });
        }
    }
};