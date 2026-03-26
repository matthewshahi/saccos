<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->boolean('loan_type_active')
                ->default(true)
                ->after('loan_type_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->dropColumn('loan_type_active');
        });
    }
};