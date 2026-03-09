<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberShareCapReconciliation extends Controller
{
    /**
     * IMPORT RECONCILIATIONS:
     * - Uses master table: shahi_corrected_capital_savings_25022026
     * - Matches: sacco_members.member_sacco_id  =  TRIM(master.sacco_id)
     * - Inserts variance transactions into:
     *    - sacco_shares
     *    - sacco_capital_shares
     * - Period: 202512 (Dec 2025)
     * - Date paid: 2025-12-31
     * - By: 1
     * - IP: IMPORT
     * - Idempotent: will not re-insert if doc_no already exists for that member
     */
    public function importReconciliations(Request $request)
    {
        // ===== Your fixed import settings =====
        $period   = '202512';
        $datePaid = '2025-12-31 23:59:59';
        $byUserId = 1;
        $ip       = 'IMPORT';

        $paidByShares  = 'IMPORT RECONCILIATIONS';
        $paidByCapital = 'IMPORT RECONCILIATIONS';

        $descShares  = 'IMPORT RECONCILIATIONS: Shares adjustment to match master totals as at 2025-12-31';
        $descCapital = 'IMPORT RECONCILIATIONS: Share capital adjustment to match master totals as at 2025-12-31';

        // Deterministic doc prefixes (idempotent per member)
        $docPrefixShares  = 'IMPORTRECON-SH-20251231-';   // + member_id
        $docPrefixCapital = 'IMPORTRECON-CAP-20251231-';  // + member_id

        $summary = [];

        DB::transaction(function () use (
            &$summary,
            $period,
            $datePaid,
            $byUserId,
            $ip,
            $paidByShares,
            $paidByCapital,
            $descShares,
            $descCapital,
            $docPrefixShares,
            $docPrefixCapital
        ) {
            // -------------------------------------------------------
            // Derived master (dedup by sacco_id to avoid duplicate inserts)
            // - total_shares/total_capital are VARCHAR => normalize:
            //    * remove commas
            //    * treat NULL / '' / 'null' as 0
            // -------------------------------------------------------
            $masterDerivedSql = "
                (
                    SELECT
                        sacco_id_trim,
                        MAX(target_shares)  AS target_shares,
                        MAX(target_capital) AS target_capital
                    FROM (
                        SELECT
                            TRIM(sacco_id) AS sacco_id_trim,

                            CASE
                                WHEN total_shares IS NULL OR TRIM(total_shares) = '' OR LOWER(TRIM(total_shares)) = 'null'
                                    THEN 0
                                ELSE CAST(REPLACE(TRIM(total_shares), ',', '') AS DECIMAL(18,2))
                            END AS target_shares,

                            CASE
                                WHEN total_capital IS NULL OR TRIM(total_capital) = '' OR LOWER(TRIM(total_capital)) = 'null'
                                    THEN 0
                                ELSE CAST(REPLACE(TRIM(total_capital), ',', '') AS DECIMAL(18,2))
                            END AS target_capital

                        FROM shahi_corrected_capital_savings_25022026
                        WHERE sacco_id IS NOT NULL
                          AND TRIM(sacco_id) <> ''
                          AND LOWER(TRIM(sacco_id)) <> 'null'
                    ) z
                    GROUP BY sacco_id_trim
                ) t
            ";

            // -------------------------------------------------------
            // Diagnostics: duplicates in master (by sacco_id)
            // -------------------------------------------------------
            $dup = DB::selectOne("
                SELECT COUNT(*) AS dup_keys
                FROM (
                    SELECT TRIM(sacco_id) AS sacco_id_trim, COUNT(*) c
                    FROM shahi_corrected_capital_savings_25022026
                    WHERE sacco_id IS NOT NULL
                      AND TRIM(sacco_id) <> ''
                      AND LOWER(TRIM(sacco_id)) <> 'null'
                    GROUP BY TRIM(sacco_id)
                    HAVING COUNT(*) > 1
                ) d
            ");
            $summary['master_duplicate_sacco_ids'] = (int)($dup->dup_keys ?? 0);

            // -------------------------------------------------------
            // Current totals (transactional truth)
            // -------------------------------------------------------
            $sharesTotalsSql = "
                (
                    SELECT share_member_id, SUM(COALESCE(share_amount_paying,0)) AS cur_shares
                    FROM sacco_shares
                    GROUP BY share_member_id
                ) s
            ";

            $capitalTotalsSql = "
                (
                    SELECT share_capitalmember_id, SUM(COALESCE(share_capitalamount_paying,0)) AS cur_capital
                    FROM sacco_capital_shares
                    GROUP BY share_capitalmember_id
                ) c
            ";

            // -------------------------------------------------------
            // 1) INSERT SHARE reconciliation transactions (variance)
            // -------------------------------------------------------
            $sharesInsertSql = "
                INSERT INTO sacco_shares
                    (share_member_id, share_amount_paying, share_paid_by, share_period, share_description, share_doc_no, share_date_paid, share_by, share_ip, share_end_month_proc)
                SELECT
                    m.member_id,
                    (t.target_shares - COALESCE(s.cur_shares,0)) AS variance,
                    ? AS share_paid_by,
                    ? AS share_period,
                    ? AS share_description,
                    CONCAT(?, m.member_id) AS share_doc_no,
                    ? AS share_date_paid,
                    ? AS share_by,
                    ? AS share_ip,
                    'N' AS share_end_month_proc
                FROM {$masterDerivedSql}
                JOIN sacco_members m
                    ON m.member_sacco_id = t.sacco_id_trim
                LEFT JOIN {$sharesTotalsSql}
                    ON s.share_member_id = m.member_id
                WHERE (t.target_shares - COALESCE(s.cur_shares,0)) <> 0
                  AND NOT EXISTS (
                      SELECT 1 FROM sacco_shares ss
                      WHERE ss.share_doc_no = CONCAT(?, m.member_id)
                  )
            ";

            $summary['shares_recon_inserted'] = DB::affectingStatement($sharesInsertSql, [
                $paidByShares,
                $period,
                $descShares,
                $docPrefixShares,
                $datePaid,
                $byUserId,
                $ip,
                $docPrefixShares,
            ]);

            // -------------------------------------------------------
            // 2) INSERT CAPITAL reconciliation transactions (variance)
            // -------------------------------------------------------
            $capitalInsertSql = "
                INSERT INTO sacco_capital_shares
                    (share_capitalmember_id, share_capitalamount_paying, share_capitalpaid_by, share_capitalperiod, share_capitaldescription, share_capitaldoc_no, share_capitaldate_paid, share_capitalby, share_capitalip, share_capitalend_month_proc)
                SELECT
                    m.member_id,
                    (t.target_capital - COALESCE(c.cur_capital,0)) AS variance,
                    ? AS share_capitalpaid_by,
                    ? AS share_capitalperiod,
                    ? AS share_capitaldescription,
                    CONCAT(?, m.member_id) AS share_capitaldoc_no,
                    ? AS share_capitaldate_paid,
                    ? AS share_capitalby,
                    ? AS share_capitalip,
                    'N' AS share_capitalend_month_proc
                FROM {$masterDerivedSql}
                JOIN sacco_members m
                    ON m.member_sacco_id = t.sacco_id_trim
                LEFT JOIN {$capitalTotalsSql}
                    ON c.share_capitalmember_id = m.member_id
                WHERE (t.target_capital - COALESCE(c.cur_capital,0)) <> 0
                  AND NOT EXISTS (
                      SELECT 1 FROM sacco_capital_shares cs
                      WHERE cs.share_capitaldoc_no = CONCAT(?, m.member_id)
                  )
            ";

            $summary['capital_recon_inserted'] = DB::affectingStatement($capitalInsertSql, [
                $paidByCapital,
                $period,
                $descCapital,
                $docPrefixCapital,
                $datePaid,
                $byUserId,
                $ip,
                $docPrefixCapital,
            ]);

            // -------------------------------------------------------
            // 3) Refresh cached totals on sacco_members
            // -------------------------------------------------------
            $summary['members_share_totals_updated'] = DB::affectingStatement("
                UPDATE sacco_members m
                LEFT JOIN (
                    SELECT share_member_id, SUM(COALESCE(share_amount_paying,0)) AS total
                    FROM sacco_shares
                    GROUP BY share_member_id
                ) x ON x.share_member_id = m.member_id
                SET m.member_total_share = COALESCE(x.total, 0)
            ");

            $summary['members_capital_totals_updated'] = DB::affectingStatement("
                UPDATE sacco_members m
                LEFT JOIN (
                    SELECT share_capitalmember_id, SUM(COALESCE(share_capitalamount_paying,0)) AS total
                    FROM sacco_capital_shares
                    GROUP BY share_capitalmember_id
                ) y ON y.share_capitalmember_id = m.member_id
                SET m.member_total_share_capital = COALESCE(y.total, 0)
            ");

            // -------------------------------------------------------
            // 4) Unmatched master sacco_ids (count + small sample)
            // -------------------------------------------------------
            $unmatchedCount = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM {$masterDerivedSql}
                LEFT JOIN sacco_members m
                    ON m.member_sacco_id = t.sacco_id_trim
                WHERE m.member_id IS NULL
            ");
            $summary['unmatched_master_rows'] = (int)($unmatchedCount->cnt ?? 0);

            $summary['unmatched_sample'] = DB::select("
                SELECT t.sacco_id_trim AS sacco_id
                FROM {$masterDerivedSql}
                LEFT JOIN sacco_members m
                    ON m.member_sacco_id = t.sacco_id_trim
                WHERE m.member_id IS NULL
                ORDER BY t.sacco_id_trim
                LIMIT 30
            ");
        });

        dd($summary);
    }
}