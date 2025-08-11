<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateMemberAggregatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 1800;      // 30 minutes
    public $queue = 'maintenance';

    public function handle(): void
    {
        $lock = Cache::lock('update_member_aggregates_lock', 55 * 60); // prevent overlap ~1h
        if (!$lock->get()) {
            Log::warning('UpdateMemberAggregatesJob: skipped (another run in progress).');
            return;
        }

        try {
            // 1) Reset ALL totals to zero first (prevents stale balances)
            DB::statement("
                UPDATE sacco_members
                SET member_total_share = 0,
                    member_total_fosa = 0,
                    member_total_share_capital = 0
            ");

            // 2) Shares (withdrawable savings)
            DB::statement("
                UPDATE sacco_members m
                JOIN (
                    SELECT share_member_id AS member_id,
                           SUM(COALESCE(share_amount_paying,0)) AS total_shares
                    FROM sacco_shares
                    GROUP BY share_member_id
                ) s ON s.member_id = m.member_id
                SET m.member_total_share = s.total_shares
            ");

            // 3) FOSA (transactional savings)
            DB::statement("
                UPDATE sacco_members m
                JOIN (
                    SELECT fosa_member_id AS member_id,
                           SUM(COALESCE(fosa_amount_paying,0)) AS total_fosa
                    FROM sacco_fosas
                    GROUP BY fosa_member_id
                ) f ON f.member_id = m.member_id
                SET m.member_total_fosa = f.total_fosa
            ");

            // 4) Share Capital (non-withdrawable)
            DB::statement("
                UPDATE sacco_members m
                JOIN (
                    SELECT share_capitalmember_id AS member_id,
                           SUM(COALESCE(share_capitalamount_paying,0)) AS total_capital
                    FROM sacco_capital_shares
                    GROUP BY share_capitalmember_id
                ) c ON c.member_id = m.member_id
                SET m.member_total_share_capital = c.total_capital
            ");

            Log::info('UpdateMemberAggregatesJob: totals updated (shares, fosa, capital).');
        } catch (\Throwable $e) {
            Log::error('UpdateMemberAggregatesJob failed: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}