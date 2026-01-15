<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {

            // Reference to original FOSA transaction (used for negative transfer rows)
            $table->unsignedBigInteger('fosa_ref_id')
                ->nullable()
                ->after('fosa_id')
                ->comment('Original fosa_id being reduced (for transfer/reversal entries only)');

            // Action type: NORMAL (existing rows), TRANSFER_OUT, REVERSAL, etc.
            $table->string('fosa_action', 30)
                ->nullable()
                ->after('fosa_ref_id')
                ->comment('Transaction action: NORMAL, TRANSFER_OUT, REVERSAL');

            // Optional index for fast lookups and duplicate-prevention checks
            $table->index('fosa_ref_id');
            $table->index('fosa_action');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            $table->dropIndex(['fosa_ref_id']);
            $table->dropIndex(['fosa_action']);

            $table->dropColumn([
                'fosa_ref_id',
                'fosa_action',
            ]);
        });
    }
};
