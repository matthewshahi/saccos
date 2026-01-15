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
        Schema::table('sacco_fosas', function (Blueprint $table) {
            $table->index('fosa_ref_id', 'idx_fosa_ref_id');
            $table->index('fosa_amount_paying', 'idx_fosa_amount');
            $table->index('fosa_end_month_proc', 'idx_fosa_end_month');
            $table->index('fosa_date_paid', 'idx_fosa_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            $table->dropIndex('idx_fosa_ref_id');
            $table->dropIndex('idx_fosa_amount');
            $table->dropIndex('idx_fosa_end_month');
            $table->dropIndex('idx_fosa_date');
        });
    }
};
