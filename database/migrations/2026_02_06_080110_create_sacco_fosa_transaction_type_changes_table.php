<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) If table does not exist, create it (with your indexes)
        if (!Schema::hasTable('sacco_fosa_transaction_type_changes')) {
            Schema::create('sacco_fosa_transaction_type_changes', function (Blueprint $table) {
                $table->bigIncrements('change_id');

                $table->unsignedBigInteger('fosa_id');

                $table->unsignedBigInteger('from_type_id')->nullable();
                $table->unsignedBigInteger('to_type_id');

                $table->string('change_reason', 255)->nullable();
                $table->text('change_notes')->nullable();

                $table->unsignedBigInteger('changed_by')->nullable();
                $table->string('changed_ip', 64)->nullable();
                $table->timestamp('changed_at')->useCurrent();

                // ✅ your indexes
                $table->index('fosa_id');
                $table->index('from_type_id');
                $table->index('to_type_id');
                $table->index('changed_by');
                $table->index('changed_at');
            });

            return;
        }

        // 2) Table exists: add missing columns, then add your indexes
        Schema::table('sacco_fosa_transaction_type_changes', function (Blueprint $table) {

            // Columns (only add if missing)
            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'change_id')) {
                $table->bigIncrements('change_id');
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'fosa_id')) {
                $table->unsignedBigInteger('fosa_id');
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'from_type_id')) {
                $table->unsignedBigInteger('from_type_id')->nullable();
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'to_type_id')) {
                $table->unsignedBigInteger('to_type_id');
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'change_reason')) {
                $table->string('change_reason', 255)->nullable();
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'change_notes')) {
                $table->text('change_notes')->nullable();
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'changed_by')) {
                $table->unsignedBigInteger('changed_by')->nullable();
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'changed_ip')) {
                $table->string('changed_ip', 64)->nullable();
            }

            if (!Schema::hasColumn('sacco_fosa_transaction_type_changes', 'changed_at')) {
                $table->timestamp('changed_at')->useCurrent();
            }

            // ✅ add your indexes (will error if they already exist)
            $table->index('fosa_id');
            $table->index('from_type_id');
            $table->index('to_type_id');
            $table->index('changed_by');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_fosa_transaction_type_changes');
    }
};
