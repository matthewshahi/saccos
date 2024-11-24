<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->string('marital_status', 50)->nullable()->after('comments'); // Marital status
            $table->string('gender', 10)->nullable()->after('marital_status'); // Gender
            $table->string('dependents', 5)->nullable()->after('gender'); // Number of dependents
            $table->string('monthly_income', 100)->nullable()->after('dependents'); // Monthly income in words
            $table->string('next_of_kin_name', 100)->nullable()->after('monthly_income'); // Next of kin name
            $table->string('next_of_kin_relationship', 50)->nullable()->after('next_of_kin_name'); // Next of kin relationship
            $table->string('next_of_kin_phone', 15)->nullable()->after('next_of_kin_relationship'); // Next of kin phone
            $table->string('next_of_kin_id', 20)->nullable()->after('next_of_kin_phone'); // Next of kin ID
            $table->string('bank_name', 100)->nullable()->after('next_of_kin_id'); // Bank name
            $table->string('bank_branch', 100)->nullable()->after('bank_name'); // Bank branch
            $table->string('bank_account_number', 50)->nullable()->after('bank_branch'); // Bank account number
            $table->string('certification_statement', 255)->nullable()->after('bank_account_number'); // Certification statement
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->dropColumn([
                'marital_status',
                'gender',
                'dependents',
                'monthly_income',
                'next_of_kin_name',
                'next_of_kin_relationship',
                'next_of_kin_phone',
                'next_of_kin_id',
                'bank_name',
                'bank_branch',
                'bank_account_number',
                'certification_statement',
            ]);
        });
    }
}