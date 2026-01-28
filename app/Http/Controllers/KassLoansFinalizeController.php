<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * KassLoansFinalizeController (v1)
 *
 * Goals:
 * - Deterministic import from kass_staging_loans into sacco_loans + sacco_loan_payments
 * - Modular, readable, extensible
 * - No fuzzy member matching (exact rules only)
 * - Respect legacy columns (do not invent/rename)
 */
class KassLoansFinalizeController extends Controller
{
    public function index()
    {
        return view('kassloan.index', [
            'stats' => [
                'rows'  => DB::table('kass_staging_loans')->count(),
                'loans' => DB::table('sacco_loans')->count(),
                'pays'  => DB::table('sacco_loan_payments')->count(),
            ],
            'summary' => session('loan_summary')
        ]);
    }

    public function reset()
    {
        DB::table('sacco_loan_payments')->truncate();
        DB::table('sacco_loans')->truncate();

        return back()->with('loan_summary', [
            'message' => "Loan tables cleared successfully!"
        ]);
    }

    /**
     * Run finalisation (v1)
     * - One deterministic pass
     * - Logs unresolved members and skips them
     */
    public function run(Request $request)
    {
        $summary = $this->newSummary();

        $ctx = $this->buildContext();

        // Stream staging rows in canonical order to avoid memory blow-ups
        $cursor = DB::table('kass_staging_loans')
            ->select([
                'id',
                'raw_name',
                'adm_no',
                'company',
                'loan_type',
                'year',
                'month',
                'outstanding_balance',
                'principal_paid',
                'interest_paid',
                'period_index',
                'source_file',
            ])
            ->orderBy('raw_name')
            ->orderBy('adm_no')
            ->orderBy('loan_type')
            ->orderBy('year')
            ->orderByRaw($ctx['monthOrderSql'])
            ->orderBy('id')
            ->cursor();

        // We process per (adm_no, loan_type) stream.
        $streamKey = null;
        $buffer = [];

        foreach ($cursor as $row) {
            $summary['rows_seen']++;

            $key = $this->makeStreamKey($row);

            if ($streamKey === null) {
                $streamKey = $key;
            }

            // When stream changes, flush previous stream
            if ($key !== $streamKey) {
                $this->processStream($buffer, $ctx, $summary);
                $buffer = [];
                $streamKey = $key;
            }

            $buffer[] = $row;
        }

        // Flush last stream
        if (!empty($buffer)) {
            $this->processStream($buffer, $ctx, $summary);
        }

      return view('kassloan.index', [
    'stats' => [
        'rows'  => DB::table('kass_staging_loans')->count(),
        'loans' => DB::table('sacco_loans')->count(),
        'pays'  => DB::table('sacco_loan_payments')->count(),
    ],
    'summary' => $summary,
]);
    }
    
    /* ============================================================
     * Stream processing (top-level orchestration per member+type)
     * ============================================================ */

    /**
     * @param array<int, object> $rawRows raw rows for ONE (adm_no, loan_type) stream
     */
    
    private function processStream(array $rawRows, array $ctx, array &$summary): void
{
    $summary['streams_processed']++;

    /* ------------------------------------------------------------
     * 1) Aggregate duplicates per (year, month) within this stream
     * ------------------------------------------------------------ */
    $monthly = $ctx['stagingAggregator']->aggregateMonthly(
        $rawRows,
        $ctx['monthToNum']
    );

    if (empty($monthly)) {
        return;
    }

    /* ------------------------------------------------------------
     * 2) Resolve member (deterministic only)
     * ------------------------------------------------------------ */
    $memberId = $ctx['memberResolver']->resolve($monthly[0]);
    if (!$memberId) {
        $summary['streams_skipped_unresolved_member']++;
        $ctx['logger']->unresolvedMember(
            $monthly[0],
            'Unable to resolve member (ADM, exact name, token-set exact all failed).'
        );
        $summary['unresolved_members'][] =
            $monthly[0]->raw_name . ' / ADM:' . ($monthly[0]->adm_no ?? '');
        return;
    }

    /* ------------------------------------------------------------
     * 3) Resolve loan type id (create if missing)
     * ------------------------------------------------------------ */
    $loanTypeId = $ctx['loanTypeResolver']
        ->resolveLoanTypeId($monthly[0]->loan_type);

    /* ------------------------------------------------------------
     * 4) Segment into loan cycles
     * ------------------------------------------------------------ */
    $cycles = $ctx['segmenter']->segment($monthly);
    if (empty($cycles)) {
        return;
    }

    /* ------------------------------------------------------------
     * 5) Iterate cycles with TOP-UP AWARE state
     * ------------------------------------------------------------ */
    $previousLoanId = null;
    $previousCycle  = null;

    foreach ($cycles as $cycleIndex => $cycle) {

        /* --------------------------------------------------------
         * 5.1) TOP-UP HANDLING (close previous loan if exists)
         * -------------------------------------------------------- */
        if ($previousLoanId !== null && $previousCycle !== null) {

            // Outstanding balance at end of previous cycle
            $lastRow     = end($previousCycle);
            $outstanding = (float) ($lastRow->outstanding_balance ?? 0);

            // Period in which the new loan starts
            $closePeriod = (int) ($cycle[0]->period ?? 0);

            if ($outstanding > 0 && $closePeriod > 0) {
                $ctx['topupHandler']->closePreviousLoan(
                    $previousLoanId,
                    $outstanding,
                    $closePeriod
                );

                $summary['topups_closed'] =
                    ($summary['topups_closed'] ?? 0) + 1;
            }
        }

        /* --------------------------------------------------------
         * 5.2) Create / reuse NEW loan header
         * -------------------------------------------------------- */
        $loanId = $ctx['loanWriter']->upsertLoanHeader(
            $memberId,
            $loanTypeId,
            $cycle,
            $ctx
        );

        if (!$loanId) {
            $summary['cycles_failed']++;
            $ctx['logger']->error('Failed to create/reuse loan header', [
                'member_id'    => $memberId,
                'loan_type_id' => $loanTypeId,
                'first_period' => $cycle[0]->period ?? null,
            ]);
            continue;
        }

        $summary['loan_cycles_created_or_reused']++;

        /* --------------------------------------------------------
         * 5.3) Insert normal monthly repayments
         * -------------------------------------------------------- */
        $inserted = $ctx['paymentWriter']
            ->insertPayments($loanId, $cycle, $ctx);

        $summary['payments_inserted'] += $inserted;

        /* --------------------------------------------------------
         * 5.4) Update state for next cycle (critical)
         * -------------------------------------------------------- */
        $previousLoanId = $loanId;
        $previousCycle  = $cycle;
    }
}


    /* ============================================================
     * Context / DI-lite
     * ============================================================ */

   private function buildContext(): array
{
    $monthToNum = $this->monthToNumMap();

    return [
        'monthToNum' => $monthToNum,

        // Used in ORDER BY raw SQL so months sort correctly
        'monthOrderSql' => $this->monthOrderSql(),

        // Logging
        'logger' => new KassLoanFinalizeLogger(),

        // Staging aggregation
        'stagingAggregator' => new KassStagingAggregator(),

        // Deterministic member resolution
        'memberResolver' => new KassMemberResolverV1(),

        // Loan type resolution (create if missing)
        'loanTypeResolver' => new KassLoanTypeResolverV1(),

        // Loan cycle segmentation
        'segmenter' => new KassLoanSegmenterV1(),

        // Loan header writer
        'loanWriter' => new KassLoanWriterV1(),

        // Monthly payments writer
        'paymentWriter' => new KassPaymentWriterV1(),

        // Top-up / refinance handler (NEW)
        'topupHandler' => new KassLoanTopUpHandlerV1(),
    ];
}


    private function newSummary(): array
    {
        return [
            'rows_seen' => 0,
            'streams_processed' => 0,
            'streams_skipped_unresolved_member' => 0,
            'loan_cycles_created_or_reused' => 0,
            'payments_inserted' => 0,
            'cycles_failed' => 0,
            'unresolved_members' => [],
            'message' => 'KASS loan finalisation completed (v1).',
        ];
    }

    private function makeStreamKey(object $row): string
    {
        // Stream identity: member + loan_type
        // We keep raw_name only for ordering, not identity.
        $adm = trim((string)($row->adm_no ?? ''));
        $type = strtoupper(trim((string)($row->loan_type ?? '')));
        return $adm . '|' . $type;
    }

    /* ============================================================
     * Month helpers
     * ============================================================ */

    private function monthToNumMap(): array
    {
        return [
            'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4,
            'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8,
            'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
        ];
    }

    private function monthOrderSql(): string
    {
        return "
            CASE UPPER(month)
                WHEN 'JAN' THEN 1
                WHEN 'FEB' THEN 2
                WHEN 'MAR' THEN 3
                WHEN 'APR' THEN 4
                WHEN 'MAY' THEN 5
                WHEN 'JUN' THEN 6
                WHEN 'JUL' THEN 7
                WHEN 'AUG' THEN 8
                WHEN 'SEP' THEN 9
                WHEN 'OCT' THEN 10
                WHEN 'NOV' THEN 11
                WHEN 'DEC' THEN 12
                ELSE 99
            END
        ";
    }
}

/* ============================================================
 * Module 1: Logger (file + laravel log)
 * ============================================================ */

class KassLoanFinalizeLogger
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = storage_path('logs/kass_loans_finalize.log');
    }

    public function unresolvedMember(object $row, string $reason): void
    {
        $msg = sprintf(
            "[UNRESOLVED_MEMBER] staging_id=%s name=%s adm=%s company=%s loan_type=%s year=%s month=%s reason=%s",
            $row->id ?? '',
            $row->raw_name ?? '',
            $row->adm_no ?? '',
            $row->company ?? '',
            $row->loan_type ?? '',
            $row->year ?? '',
            $row->month ?? '',
            $reason
        );

        \Log::warning($msg);
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL, FILE_APPEND);
    }

    public function error(string $message, array $context = []): void
    {
        $msg = "[ERROR] " . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        \Log::error($msg);
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL, FILE_APPEND);
    }
}

/* ============================================================
 * Module 2: Aggregate same-month duplicates (per stream)
 * ============================================================ */

class KassStagingAggregator
{
    /**
     * @param array<int, object> $rows
     * @return array<int, object> monthly aggregates with fields:
     *   raw_name, adm_no, company, loan_type, year, month,
     *   principal_paid, interest_paid, outstanding_balance,
     *   period_index (max/first non-null), source_file (first),
     *   period (YYYYMM int), month_num
     */
    public function aggregateMonthly(array $rows, array $monthToNum): array
    {
        $buckets = [];

        foreach ($rows as $r) {
            $year = (int)($r->year ?? 0);
            $mTxt = strtoupper(trim((string)($r->month ?? '')));
            $mNum = $monthToNum[preg_replace('/[^A-Z]/', '', $mTxt)] ?? null;
            if (!$year || !$mNum) {
                continue; // skip invalid month/year rows safely
            }

            $period = ($year * 100) + $mNum;
            $key = $period;

            if (!isset($buckets[$key])) {
                $buckets[$key] = (object)[
                    'raw_name' => $r->raw_name,
                    'adm_no' => $r->adm_no,
                    'company' => $r->company,
                    'loan_type' => $r->loan_type,
                    'year' => $year,
                    'month' => $r->month,
                    'month_num' => $mNum,
                    'period' => $period,

                    // aggregate fields
                    'principal_paid' => 0.0,
                    'interest_paid' => 0.0,
                    'outstanding_balance' => null, // we will keep max
                    'period_index' => null,         // we will keep first numeric seen
                    'source_file' => $r->source_file ?? null,
                ];
            }

            $bucket = $buckets[$key];

            $bucket->principal_paid += (float)($r->principal_paid ?? 0);
            $bucket->interest_paid  += (float)($r->interest_paid ?? 0);

            $out = (float)($r->outstanding_balance ?? 0);
            if ($bucket->outstanding_balance === null || $out > (float)$bucket->outstanding_balance) {
                $bucket->outstanding_balance = $out;
            }

            // If any row in month has numeric period_index, keep it (authoritative loan start marker)
            $idx = $r->period_index;
            if ($bucket->period_index === null && is_numeric($idx) && (int)$idx > 0) {
                $bucket->period_index = (int)$idx;
            }

            $buckets[$key] = $bucket;
        }

        ksort($buckets);

        return array_values($buckets);
    }
}

/* ============================================================
 * Module 3: Member resolver v1 (no fuzzy)
 * - adm_no => member_sacco_id
 * - exact normalized full name
 * - exact token-set match (order independent)
 * ============================================================ */

class KassMemberResolverV1
{
    public function resolve(object $row): ?int
    {
        // 1) ADM number (primary)
        $adm = trim((string)($row->adm_no ?? ''));
        if ($adm !== '') {
            $id = DB::table('sacco_members')
                ->where('member_sacco_id', $adm)
                ->where('member_deleted', 'N')
                ->value('member_id');
            if ($id) return (int)$id;
        }

        // 2) Exact normalized name match
        $rawName = $this->normalizeName((string)($row->raw_name ?? ''));
        if ($rawName === '') {
            return null;
        }

        $id = DB::table('sacco_members')
            ->where('member_deleted', 'N')
            ->where('member_name', $rawName)
            ->value('member_id');
        if ($id) return (int)$id;

        // 3) Exact token-set match (order independent)
        // We do this deterministically: scan candidates that contain ALL tokens (LIKE),
        // then verify exact token set equality in PHP to avoid partial/fuzzy results.
        $tokens = $this->nameTokens($rawName);
        if (count($tokens) < 2) {
            return null; // per your rule: we never match on single name
        }

        $q = DB::table('sacco_members')
            ->select(['member_id', 'member_name'])
            ->where('member_deleted', 'N');

        // Narrow candidates (must contain all tokens)
        foreach ($tokens as $t) {
            $q->where('member_name', 'LIKE', '%' . $t . '%');
        }

        $candidates = $q->limit(50)->get(); // cap to keep predictable

        $matched = [];
        foreach ($candidates as $cand) {
            $candNorm = $this->normalizeName((string)$cand->member_name);
            $candTokens = $this->nameTokens($candNorm);

            if ($this->tokenSetsEqual($tokens, $candTokens)) {
                $matched[] = (int)$cand->member_id;
            }
        }

        if (count($matched) === 1) {
            return $matched[0];
        }

        return null; // 0 or ambiguous => unresolved in v1
    }

    private function normalizeName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);
        // Keep letters, spaces, apostrophes only (consistent, deterministic)
        $name = preg_replace("/[^A-Z'\s]/", ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function nameTokens(string $normalizedName): array
    {
        $parts = array_values(array_filter(explode(' ', $normalizedName)));
        // Never use one-name matching
        return $parts;
    }

    private function tokenSetsEqual(array $a, array $b): bool
    {
        sort($a);
        sort($b);
        return $a === $b;
    }
}

/* ============================================================
 * Module 4: Loan type resolver v1 (create if missing)
 * ============================================================ */

class KassLoanTypeResolverV1
{
    public function resolveLoanTypeId(?string $loanType): int
    {
        $name = strtoupper(trim((string)$loanType));
        if ($name === '') {
            $name = 'UNKNOWN LOAN';
        }

        $id = DB::table('sacco_loan_types')
            ->where('loan_type_name', $name)
            ->value('loan_type_id');

        if ($id) return (int)$id;

        // Create minimally, using safe defaults (same pattern you used)
        $clean = preg_replace('/[^A-Z]/', '', $name);
        $code = substr($clean, 0, 4);
        if ($code === '') $code = 'TYPE';

        return (int) DB::table('sacco_loan_types')->insertGetId([
            'loan_type_name'                  => $name,
            'loan_type_interest'              => 12,
            'loan_type_interest_type'         => 'REDUCING BALANCE',
            'loan_type_duration'              => 36,
            'loan_type_guaranteable_percent'  => 100,
            'loan_type_code'                  => $code,
            'loan_type_max_amount'            => 0,
            'loan_type_qualification_period'  => 0,
            'loan_type_acount'                => null,
            'loan_type_int_account'           => null,
            'loan_type_comm_account'          => null,
            'loan_type_insurable'             => '0',
            'loan_type_share_factor'          => 0,
            'loan_type_instant_qualification' => 0,
            'loan_type_by'                    => 1,
            'loan_type_ip'                    => 'MIGRATION',
        ]);
    }
}

/* ============================================================
 * Module 5: Segmenter v1 (period_index, zero reset, balance jump)
 * ============================================================ */

class KassLoanSegmenterV1
{
    /**
     * @param array<int, object> $monthly aggregates (ordered by period asc)
     * @return array<int, array<int, object>> list of cycles
     */
    public function segment(array $monthly): array
    {
        $cycles = [];
        $current = [];
        $prev = null;

        foreach ($monthly as $cur) {
            $startNew = false;

            if ($prev === null) {
                $startNew = true;
            } else {
                // Priority: period_index appearance (authoritative)
                if (is_numeric($cur->period_index) && (int)$cur->period_index > 0 && !empty($current)) {
                    $startNew = true;
                }

                // Zero reset
                if (!$startNew) {
                    $prevOut = (float)($prev->outstanding_balance ?? 0);
                    $curOut  = (float)($cur->outstanding_balance ?? 0);

                    if ($prevOut == 0.0 && $curOut > 0.0) {
                        $startNew = true;
                    }
                }

                // Material balance jump (fallback)
                if (!$startNew) {
                    $prevOut = (float)($prev->outstanding_balance ?? 0);
                    $curOut  = (float)($cur->outstanding_balance ?? 0);

                    // Statistical-ish guardrail: jump ratio + absolute floor
                    // (deterministic; can be tuned later)
                    if ($prevOut > 0.0 && $curOut > ($prevOut * 1.20) && ($curOut - $prevOut) > 5000) {
                        $startNew = true;
                    }
                }
            }

            if ($startNew && !empty($current)) {
                $cycles[] = $current;
                $current = [];
            }

            $current[] = $cur;
            $prev = $cur;
        }

        if (!empty($current)) {
            $cycles[] = $current;
        }

        return $cycles;
    }
}

/* ============================================================
 * Module 6: Loan header writer v1 (idempotent-ish)
 * ============================================================ */

class KassLoanWriterV1
{
    /**
     * Create or reuse a sacco_loans header for this cycle.
     *
     * We keep v1 conservative:
     * - loan_amount = max outstanding in cycle
     * - taken_period = first period in cycle
     *
     * @param array<int, object> $cycle
     */
    public function upsertLoanHeader(int $memberId, int $loanTypeId, array $cycle, array $ctx): ?int
    {
        if (empty($cycle)) return null;

        $startPeriod = (int)($cycle[0]->period ?? 0);
        if (!$startPeriod) return null;

        $maxOut = 0.0;
        foreach ($cycle as $r) {
            $out = (float)($r->outstanding_balance ?? 0);
            if ($out > $maxOut) $maxOut = $out;
        }

        // Attempt reuse (deterministic key)
        $existing = DB::table('sacco_loans')
            ->where('loan_member', $memberId)
            ->where('loan_loan_type', $loanTypeId)
            ->where('loan_taken_period', $startPeriod)
            ->where('loan_amount', $maxOut)
            ->value('loan_id');

        if ($existing) {
            return (int)$existing;
        }

        return (int) DB::table('sacco_loans')->insertGetId([
            'loan_member'             => $memberId,
            'loan_loan_type'          => $loanTypeId,
            'loan_loan_category'      => 1,
            'loan_amount'             => $maxOut,
            'loan_insurance'          => 0,
            'loan_commision'          => 0,

            'loan_taken_period'       => $startPeriod,
            'loan_taken_start_period' => $startPeriod,

            'loan_interest_payable'   => 0,
            'loan_amount_guaranteed'  => $maxOut,
            'loan_loan_paid'          => 0,

            'loan_doc_no'             => 'KASS_IMPORT',
            'loan_description'        => 'IMPORTED LOAN',
            'loan_on'                 => now(),
            'loan_by'                 => 1,
            'loan_ip'                 => 'MIGRATION',
            'loan_stoped'             => 'N',
        ]);
    }
}

/* ============================================================
 * Module 7: Payment writer v1 (one payment per month if paid)
 * ============================================================ */

class KassPaymentWriterV1
{
    /**
     * @param array<int, object> $cycle
     * @return int number of inserted payments
     */
    public function insertPayments(int $loanId, array $cycle, array $ctx): int
    {
        $inserted = 0;

        foreach ($cycle as $r) {
            $principal = (float)($r->principal_paid ?? 0);
            $interest  = (float)($r->interest_paid ?? 0);

            if ($principal == 0.0 && $interest == 0.0) {
                continue;
            }

            $period = (int)($r->period ?? 0);
            if (!$period) {
                continue;
            }

            $year  = (int) floor($period / 100);
            $month = (int) ($period % 100);

            $paidOn = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
            $desc = 'IMPORT ' . strtoupper(trim((string)($r->loan_type ?? 'LOAN'))) . ' ' . $period;

            // Idempotency check for reruns (conservative)
            $exists = DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loanId)
                ->where('loan_payments_period', $period)
                ->where('loan_payments_amount', $principal)
                ->where('loan_payments_interest', $interest)
                ->value('loan_payments_id');

            if ($exists) {
                continue;
            }

            DB::table('sacco_loan_payments')->insert([
                'loan_payments_amount'      => $principal,
                'loan_payments_interest'    => $interest,
                'loan_payments_description' => $desc,
                'loan_payments_docno'       => 'KASS_IMPORT',
                'loan_payments_paid_in_by'  => 'KASS_IMPORT',
                'loan_payments_period'      => $period,
                'loan_payments_paid_on'     => $paidOn,
                'loan_payments_loan_id'     => $loanId,
                'loan_end_month_proc'       => 'N',
                'loan_payments_on'          => now(),
                'loan_payments_by'          => 1,
                'loan_payments_ip'          => 'MIGRATION',
            ]);

            // Update loan paid (principal only; matches your existing pattern)
            DB::table('sacco_loans')
                ->where('loan_id', $loanId)
                ->update([
                    'loan_loan_paid' => DB::raw('COALESCE(loan_loan_paid,0) + ' . $principal)
                ]);

            $inserted++;
        }

        return $inserted;
    }
}
/* ============================================================
 * Module 8: Top-up handler v1
 * - Clears previous loan when a new cycle starts
 * ============================================================ */

class KassLoanTopUpHandlerV1
{
    /**
     * Close previous loan by inserting a clearing payment
     */
    public function closePreviousLoan(
        int $previousLoanId,
        float $outstandingBalance,
        int $closePeriod
    ): void {
        if ($outstandingBalance <= 0) {
            return;
        }

        // Idempotency guard
        $exists = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $previousLoanId)
            ->where('loan_payments_period', $closePeriod)
            ->where('loan_payments_amount', $outstandingBalance)
            ->value('loan_payments_id');

        if ($exists) {
            return;
        }

        $year  = (int) floor($closePeriod / 100);
        $month = (int) ($closePeriod % 100);
        $paidOn = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // 1) Insert clearing payment
        DB::table('sacco_loan_payments')->insert([
            'loan_payments_amount'      => $outstandingBalance,
            'loan_payments_interest'    => 0,
            'loan_payments_description' => 'IMPORT LOAN TOP-UP CLEARANCE',
            'loan_payments_docno'       => 'KASS_IMPORT',
            'loan_payments_paid_in_by'  => 'KASS_IMPORT',
            'loan_payments_period'      => $closePeriod,
            'loan_payments_paid_on'     => $paidOn,
            'loan_payments_loan_id'     => $previousLoanId,
            'loan_end_month_proc'       => 'N',
            'loan_payments_on'          => now(),
            'loan_payments_by'          => 1,
            'loan_payments_ip'          => 'MIGRATION',
        ]);

        // 2) Mark loan fully paid & stopped
        DB::table('sacco_loans')
            ->where('loan_id', $previousLoanId)
            ->update([
                'loan_loan_paid' => DB::raw('loan_amount'),
                'loan_stoped'    => 'Y',
                'loan_stopped_on'=> now(),
                'loan_stopped_by'=> 1,
            ]);
    }
}
