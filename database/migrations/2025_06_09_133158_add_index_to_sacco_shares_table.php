<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddIndexToSaccoSharesTable extends Migration
{
    public function up()
    {
        // Check if the index already exists
        $indexExists = DB::select("
            SELECT COUNT(1) as count 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = DATABASE() 
              AND table_name = 'sacco_shares' 
              AND index_name = 'idx_share_period_member'
        ");

        if ($indexExists[0]->count == 0) {
            DB::statement("CREATE INDEX idx_share_period_member ON sacco_shares (share_period, share_member_id)");
        }
    }

    public function down()
    {
        // Drop the index only if it exists
        DB::statement("DROP INDEX IF EXISTS idx_share_period_member ON sacco_shares");
    }
}