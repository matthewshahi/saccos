<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'sacco_loan_types';
        $oldGrace = 'loan_type_default_after_days';
        $newGrace = 'loan_type_grace_days_after_due';

        $hasOldGrace = Schema::hasColumn($table, $oldGrace);
        $hasNewGrace = Schema::hasColumn($table, $newGrace);

        if ($hasOldGrace && !$hasNewGrace) {
            Schema::table($table, function (Blueprint $table) use ($oldGrace, $newGrace) {
                $table->renameColumn($oldGrace, $newGrace);
            });
        } elseif (!$hasOldGrace && !$hasNewGrace) {
            Schema::table($table, function (Blueprint $table) use ($newGrace) {
                $table->unsignedInteger($newGrace)
                    ->default(45)
                    ->after('loan_type_auto_interest_on_period_change')
                    ->comment('Calendar grace days after contractual due date before the unpaid loan obligation is in default');
            });
        } elseif ($hasOldGrace && $hasNewGrace) {
            // Preserve already-configured values, then remove the obsolete duplicate field.
            DB::statement(
                "UPDATE {$table} SET {$newGrace} = {$oldGrace} WHERE {$oldGrace} IS NOT NULL"
            );

            Schema::table($table, function (Blueprint $table) use ($oldGrace) {
                $table->dropColumn($oldGrace);
            });
        }

        if (!Schema::hasColumn($table, 'loan_type_default_interest')) {
            Schema::table($table, function (Blueprint $table) use ($newGrace) {
                $table->double('loan_type_default_interest')
                    ->nullable()
                    ->after($newGrace)
                    ->comment('Default interest rate; NULL falls back to loan_type_interest');
            });
        }
    }

    public function down(): void
    {
        $table = 'sacco_loan_types';

        if (Schema::hasColumn($table, 'loan_type_default_interest')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('loan_type_default_interest');
            });
        }

        if (
            Schema::hasColumn($table, 'loan_type_grace_days_after_due')
            && !Schema::hasColumn($table, 'loan_type_default_after_days')
        ) {
            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn(
                    'loan_type_grace_days_after_due',
                    'loan_type_default_after_days'
                );
            });
        }
    }
};
