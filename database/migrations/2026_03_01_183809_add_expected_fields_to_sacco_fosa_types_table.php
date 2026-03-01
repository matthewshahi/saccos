<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_fosa_types', function (Blueprint $table) {
            // Add only if missing (safe on re-run / other envs)
            if (!Schema::hasColumn('sacco_fosa_types', 'expected_amount')) {
                $table->decimal('expected_amount', 15, 2)
                    ->nullable()
                    ->after('type_default');
            }

            if (!Schema::hasColumn('sacco_fosa_types', 'expected_period')) {
                $table->enum('expected_period', ['daily', 'weekly', 'monthly', 'yearly'])
                    ->nullable()
                    ->after('expected_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sacco_fosa_types', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_fosa_types', 'expected_period')) {
                $table->dropColumn('expected_period');
            }
            if (Schema::hasColumn('sacco_fosa_types', 'expected_amount')) {
                $table->dropColumn('expected_amount');
            }
        });
    }
};