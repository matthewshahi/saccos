<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'sacco_members';

        $indexes = [
            'idx_sacco_members_email' => 'member_email',
            'idx_sacco_members_phone_no' => 'member_phone_no',
            'idx_sacco_members_sacco_id' => 'member_sacco_id',
            'idx_sacco_members_active' => 'member_active',
        ];

        foreach ($indexes as $indexName => $columnName) {
            if (
                Schema::hasColumn($tableName, $columnName) &&
                !$this->indexExists($tableName, $indexName)
            ) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName, $columnName) {
                    $table->index($columnName, $indexName);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'sacco_members';

        $indexes = [
            'idx_sacco_members_email',
            'idx_sacco_members_phone_no',
            'idx_sacco_members_sacco_id',
            'idx_sacco_members_active',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne("
            SELECT COUNT(*) AS index_count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [
            $databaseName,
            $tableName,
            $indexName,
        ]);

        return (int) ($result->index_count ?? 0) > 0;
    }
};