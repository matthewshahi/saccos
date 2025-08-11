<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sacco_shares
        if (! $this->indexExists('sacco_shares', 'idx_share_member_id')) {
            Schema::table('sacco_shares', function ($table) {
                $table->index('share_member_id', 'idx_share_member_id');
            });
        }

        // sacco_fosas
        if (! $this->indexExists('sacco_fosas', 'idx_fosa_member_id')) {
            Schema::table('sacco_fosas', function ($table) {
                $table->index('fosa_member_id', 'idx_fosa_member_id');
            });
        }

        // sacco_capital_shares
        if (! $this->indexExists('sacco_capital_shares', 'idx_capital_member_id')) {
            Schema::table('sacco_capital_shares', function ($table) {
                $table->index('share_capitalmember_id', 'idx_capital_member_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('sacco_shares', 'idx_share_member_id')) {
            Schema::table('sacco_shares', function ($table) {
                $table->dropIndex('idx_share_member_id');
            });
        }

        if ($this->indexExists('sacco_fosas', 'idx_fosa_member_id')) {
            Schema::table('sacco_fosas', function ($table) {
                $table->dropIndex('idx_fosa_member_id');
            });
        }

        if ($this->indexExists('sacco_capital_shares', 'idx_capital_member_id')) {
            Schema::table('sacco_capital_shares', function ($table) {
                $table->dropIndex('idx_capital_member_id');
            });
        }
    }

    /**
     * Check if a specific index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $connection->listTableIndexes($table);
        return array_key_exists($indexName, $indexes);
    }
};