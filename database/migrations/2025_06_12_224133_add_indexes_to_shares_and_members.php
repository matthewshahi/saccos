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
        // Check and create index on sacco_shares.share_member_id
        if (!$this->indexExists('sacco_shares', 'idx_member_id')) {
            Schema::table('sacco_shares', function (Blueprint $table) {
                $table->index('share_member_id', 'idx_member_id');
            });
        }

        // Check and create index on sacco_shares.share_period
        if (!$this->indexExists('sacco_shares', 'idx_share_period')) {
            Schema::table('sacco_shares', function (Blueprint $table) {
                $table->index('share_period', 'idx_share_period');
            });
        }

        // Check and create index on sacco_members.member_deleted
        if (!$this->indexExists('sacco_members', 'idx_deleted')) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->index('member_deleted', 'idx_deleted');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_shares', function (Blueprint $table) {
            $table->dropIndex('idx_member_id');
            $table->dropIndex('idx_share_period');
        });

        Schema::table('sacco_members', function (Blueprint $table) {
            $table->dropIndex('idx_deleted');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::select("
            SELECT COUNT(1) as count 
            FROM information_schema.STATISTICS 
            WHERE table_schema = ? AND table_name = ? AND index_name = ?
        ", [$database, $table, $indexName]);

        return $result[0]->count > 0;
    }
};