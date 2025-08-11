<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // sacco_shares (idx_share_member_id)
        if (! $this->indexExists('sacco_shares', 'idx_share_member_id')) {
            DB::statement("ALTER TABLE sacco_shares ADD INDEX idx_share_member_id (share_member_id)");
        }

        // sacco_fosas (idx_fosa_member_id)
        if (! $this->indexExists('sacco_fosas', 'idx_fosa_member_id')) {
            DB::statement("ALTER TABLE sacco_fosas ADD INDEX idx_fosa_member_id (fosa_member_id)");
        }

        // sacco_capital_shares (idx_capital_member_id)
        if (! $this->indexExists('sacco_capital_shares', 'idx_capital_member_id')) {
            DB::statement("ALTER TABLE sacco_capital_shares ADD INDEX idx_capital_member_id (share_capitalmember_id)");
        }
    }

    public function down(): void
    {
        if ($this->indexExists('sacco_shares', 'idx_share_member_id')) {
            DB::statement("DROP INDEX idx_share_member_id ON sacco_shares");
        }

        if ($this->indexExists('sacco_fosas', 'idx_fosa_member_id')) {
            DB::statement("DROP INDEX idx_fosa_member_id ON sacco_fosas");
        }

        if ($this->indexExists('sacco_capital_shares', 'idx_capital_member_id')) {
            DB::statement("DROP INDEX idx_capital_member_id ON sacco_capital_shares");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(1) AS c
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ", [$table, $index]);

        return isset($row->c) && (int)$row->c > 0;
    }
};