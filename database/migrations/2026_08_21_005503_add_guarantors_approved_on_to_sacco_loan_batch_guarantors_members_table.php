<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'sacco_loan_batch_guarantors_members',
            function (Blueprint $table) {
                $table->dateTime('guarantors_approved_on')
                    ->nullable()
                    ->after('guarantors_approved');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'sacco_loan_batch_guarantors_members',
            function (Blueprint $table) {
                $table->dropColumn('guarantors_approved_on');
            }
        );
    }
};