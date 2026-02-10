<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $indexName]);

        return !empty($row);
    }

    public function up(): void
    {
        // 1) Create table if missing
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

                // your indexes
                $table->index('fosa_id');
                $table->index('from_type_id');
                $table->index('to_type_id');
                $table->index('changed_by');
                $table->index('changed_at');
            });

            return;
        }

        // 2) Table exists: add missing columns + indexes (guarded)
        Schema::table('sacco_fosa_transaction_type_changes', function (Blueprint $table) {

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
        });

        // Add indexes only if missing (same default names Laravel uses)
        $table = 'sacco_fosa_transaction_type_changes';

        $indexes = [
            "{$table}_fosa_id_index"      => 'fosa_id',
            "{$table}_from_type_id_index" => 'from_type_id',
            "{$table}_to_type_id_index"   => 'to_type_id',
            "{$table}_changed_by_index"   => 'changed_by',
            "{$table}_changed_at_index"   => 'changed_at',
        ];

        foreach ($indexes as $indexName => $column) {
            if (!$this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($column, $indexName) {
                    $t->index($column, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_fosa_transaction_type_changes');
    }
};
