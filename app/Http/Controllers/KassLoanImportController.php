<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KassLoanImportController extends Controller
{
    /*
     * Normalised FULL loan type mapping (ALL CAPITALISED)
     */
    protected $lastLoan = [];

    protected $loanTypeMap = [
        'LOAN 1' => 'NORMAL LOAN',
        'NORMAL' => 'NORMAL LOAN',
        'NORM' => 'NORMAL LOAN',

        'LOAN 2' => 'EMERGENCY LOAN',
        'EMERGENCY' => 'EMERGENCY LOAN',

        'LOAN 3' => 'SCHOOL FEES LOAN',
        'SCHOOL' => 'SCHOOL FEES LOAN',

        'LOAN 4' => 'UWEZO LOAN',
        'UWEZO' => 'UWEZO LOAN',
        'UWAZO' => 'UWEZO LOAN',

        'SPARK' => 'SPARK LOAN',

        'TOPUP' => 'NORMAL TOP-UP LOAN',
        'TOP UP' => 'NORMAL TOP-UP LOAN',

        'OKOA' => 'OKOA LOAN',

        'KARIBISHA' => 'KARIBISHA LOAN',

        'MOBILE' => 'MOBILE LOAN',
        'M-LOAN' => 'MOBILE LOAN',
        'M LOAN' => 'MOBILE LOAN',

        'LOAN' => 'NORMAL LOAN'
    ];

    /* ============================================================
     *  INDEX (Dashboard)
     * ============================================================ */
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

    /* ============================================================
     *  RESET LOAN TABLES
     * ============================================================ */
    public function reset()
    {
        DB::table('sacco_loan_payments')->truncate();
        DB::table('sacco_loans')->truncate();

        return back()->with('loan_summary', [
            'message' => "Loan tables cleared successfully!"
        ]);
    }

    /* ============================================================
     *  RUN LOAN IMPORT
     * ============================================================ */
    public function run(Request $request)
    {
        $summary = [
            'rows_processed'      => 0,
            'loan_cycles_created' => 0,
            'payments_inserted'   => 0,
            'unresolved_members'   => []
        ];

        $groups = DB::table('kass_staging_loans')
    ->orderBy('raw_name')
    ->orderBy('loan_type')   // raw ordering is fine
    ->orderBy('year')
    ->orderByRaw("
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
    ")
    ->get()
    ->map(function ($r) {
        $r->loan_type = $this->resolveLoanType($r->loan_type);
        return $r;
    })
    ->groupBy(['raw_name', 'loan_type']);



            



        foreach ($groups as $loanGroups) {
            foreach ($loanGroups as $loanRows) {

                $loanType = $loanRows[0]->loan_type;

                // $loanRows = collect($loanRows)
                //     ->filter(fn($r) => $r->loan_type === $loanType)
                //     ->values();

//                 $loanRows = collect($loanRows)->map(function ($r) {
//     $r->loan_type = $this->resolveLoanType($r->loan_type);
//     return $r;
// })->values();



                $loanRows = $loanRows->values();
                $loanRows = $loanRows->sortBy(function ($r) {
                    return ((int)$r->year * 100) + $this->safeMonth($r->month);
                })->values();

                $cycles   = $this->detectLoanCycles($loanRows);

                foreach ($cycles as $cycle) {
                    $this->importLoanCycle($cycle, $summary);
                }
            }
        }

        return redirect()->route('kassloan.index')
            ->with('loan_summary', $summary);
    }

    /* ============================================================
     *  DETECT LOAN CYCLES
     * ============================================================ */
    protected function detectLoanCycles($rows)
    {
        $cycles  = [];
        $current = [];
        $prevRow = null;

        foreach ($rows as $row) {

            $startNewLoan = false;

            $outstanding = (float)$row->outstanding_balance;
            $periodIndex = trim($row->period_index);

            $curYear  = (int)$row->year;
            $curMonth = $this->safeMonth($row->month);
            $curPeriod = ($curYear * 100) + $curMonth;

            /*
         * ============================================================
         * PRIORITY 1 — FIRST ROW ALWAYS STARTS A NEW LOAN
         * ============================================================
         */
            if ($prevRow === null) {
                $startNewLoan = true;
            } else {
                $prevOutstanding = (float)$prevRow->outstanding_balance;
                $prevPeriodIndex = trim($prevRow->period_index);
                $prevYear   = (int)$prevRow->year;
                $prevMonth  = $this->safeMonth($prevRow->month);
                $prevPeriod = ($prevYear * 100) + $prevMonth;

                /*
             * ==================================================================
             * PRIORITY 2 — PERIOD INDEX CHANGE → TRUE NEW LOAN
             * ==================================================================
             * New loan ONLY when:
             *   - current period_index is numeric AND > 0
             *   - AND previous period_index was NULL/blank OR different number
             * This is the MOST RELIABLE signal in the payroll structure.
             * ==================================================================
             */
                // if (
                //     is_numeric($periodIndex) &&
                //     (int)$periodIndex > 0 &&
                //     (
                //         $prevPeriodIndex === null ||
                //         trim($prevPeriodIndex) === '' ||
                //         $prevPeriodIndex != $periodIndex
                //     )
                // ) {
                //     $startNewLoan = true;
                // }

                $curIdx  = $row->period_index;
                $prevIdx = $prevRow->period_index ?? null;

                // NEW loan ONLY when period_index APPEARS
                if (
                    is_numeric($curIdx)
                    && (int)$curIdx > 0
                    && !empty($current) // important
                ) {
                    $startNewLoan = true;
                }



                /*
             * ==================================================================
             * PRIORITY 3 — PREVIOUS OUTSTANDING == 0 → DEFINITELY NEW LOAN
             * ==================================================================
             * When a loan hits zero, the next deduction is ALWAYS a new loan.
             * ==================================================================
             */ else if ($prevOutstanding == 0) {
                    $startNewLoan = true;
                }

                /*
             * ==================================================================
             * PRIORITY 4 — OUTSTANDING INCREASE → POSSIBLE NEW LOAN
             * ==================================================================
             * But NOT on every increase — only when:
             *   - prevOutstanding is small (loan nearly finished)
             *   AND outstanding jumps upward (e.g. 8,748 → 50,000)
             * ==================================================================
             */
                // else if (
                //         $outstanding > $prevOutstanding &&
                //         $prevOutstanding < 15000         // threshold to avoid false triggers
                //     ) {
                //         $startNewLoan = true;
                //     }

                /*
             * ==================================================================
             * PRIORITY 5 — MONTH GAP > 3 MONTHS → NEW LOAN
             * ==================================================================
             * Used ONLY if none of the above triggered.
             * ==================================================================
             */ else {
                    $gap = $this->monthDiff($prevYear, $prevMonth, $curYear, $curMonth);
                    if ($gap > 3) {
                        $startNewLoan = true;
                    }
                }
            }

            /*
         * ============================================================
         * CLOSE PREVIOUS CYCLE IF A NEW ONE STARTS
         * ============================================================
         */
            if ($startNewLoan && !empty($current)) {
                $cycles[] = $current;
                $current  = [];
            }

            $current[] = $row;
            $prevRow   = $row;
        }

        /*
     * Push the final cycle
     */
        if (!empty($current)) {
            $cycles[] = $current;
        }

        return $cycles;
    }



    protected function monthDiff($y1, $m1, $y2, $m2)
    {
        $m1 = $this->safeMonth($m1);
        $m2 = $this->safeMonth($m2);

        if (!$m1 || !$m2) return 999; // safety fallback

        return (($y2 * 12) + $m2) - (($y1 * 12) + $m1);
    }

    /* ============================================================
     *  IMPORT ONE LOAN CYCLE
     * ============================================================ */
    protected function importLoanCycle($cycle, &$summary)
    {


        $cycle = collect($cycle)->sortBy(function ($r) {
            return ((int)$r->year * 100) + $this->safeMonth($r->month);
        })->values()->all();

        if (empty($cycle)) {
            return;
        }

        $first = $cycle[0];
        $sourceTag = $this->sourceFileTag($first);

        /* ------------------------------------------
     * 1) Resolve Member
     * ------------------------------------------ */
        $memberId = $this->resolveMember($first);
        if (!$memberId) {
            $summary['unresolved_members'][] = $first->raw_name . " / " . $first->company;
            $this->logUnresolvedMember($first, "Unable to resolve member for loan cycle.");
            return;
        }

        /* ------------------------------------------
     * 2) Resolve Loan Type
     * ------------------------------------------ */
         $loanTypeName = $this->resolveLoanType($first->loan_type);
        // $loanTypeName = strtoupper(trim($first->loan_type));

        $loanTypeId   = $this->getLoanTypeId($loanTypeName);

        /* ------------------------------------------
     * 3) Identify if this cycle is a TOP-UP
     * ------------------------------------------ */
        /* ------------------------------------------
 * 3) Identify if this cycle is a TOP-UP
 * ------------------------------------------ */
$previousLoanId = $this->lastLoan[$memberId][$loanTypeId] ?? null;

$isTopUp = (
    $previousLoanId &&
    is_numeric($first->period_index) &&
    (int) $first->period_index > 0
);


   

        // period_index is required
        $cycleIndex = is_numeric($first->period_index)
            ? (int) $first->period_index
            : null;

 


       


        $initialOutstanding = (float)$cycle[0]->outstanding_balance;

        $remainingBalance = 0;

        if ($isTopUp) {

            $prevLoan = DB::table('sacco_loans')->where('loan_id', $previousLoanId)->first();

            $loanPaid = (float)($prevLoan->loan_loan_paid ?? 0);
            $loanAmt  = (float)($prevLoan->loan_amount ?? 0);

            $remainingBalance = max($loanAmt - $loanPaid, 0);

            // period of new top-up loan
            $startYear   = (int)$cycle[0]->year;
            $startMonth  = $this->safeMonth($cycle[0]->month);
            $startPeriod = sprintf('%04d%02d', $startYear, $startMonth);

            if ($remainingBalance > 0) {

                // AUTO-CLOSE PAYMENT (Refinanced into new loan)
                DB::table('sacco_loan_payments')->insert([
                    'loan_payments_amount'       => $remainingBalance,
                    'loan_payments_interest'     => 0,
                    'loan_payments_description'  => "AUTO-CLOSE — TOP-UP REFINANCE — Prev Loan No: {$previousLoanId} — {$sourceTag}",
                    'loan_payments_docno'        => 'AUTO',
                    'loan_payments_paid_in_by'   => $first->company,
                    'loan_payments_period'       => $startPeriod,
                    'loan_payments_paid_on'      => Carbon::now()->toDateString(),
                    'loan_payments_loan_id'      => $previousLoanId,
                    'loan_end_month_proc'        => 'N',
                    'loan_payments_on'           => now(),
                    'loan_payments_by'           => 1,
                    'loan_payments_ip'           => 'TOPUP',
                ]);

                DB::table('sacco_loans')
                    ->where('loan_id', $previousLoanId)
                    ->update([
                        'loan_loan_paid' => DB::raw("COALESCE(loan_loan_paid,0) + {$remainingBalance}"),
                        'loan_stoped'    => 'Y',
                        'loan_stoped_on' => now(),
                    ]);

                $summary['payments_inserted']++;
            }

            // IMPORTANT FIX:
            // DO NOT add remainingBalance to the new loan amount.
            // The outstanding_balance already includes it.
        }


        /* ------------------------------------------
     * 5) Create NEW LOAN HEADER (normal or top-up)
     * ------------------------------------------ */
        $lastRow       = end($cycle);
        $latestPrincipal = (float)$lastRow->principal_paid;
        $latestInterest  = (float)$lastRow->interest_paid;
        $monthlyTotal    = $latestPrincipal + $latestInterest;

        $startYear  = (int)$cycle[0]->year;
        $startMonth = $this->safeMonth($cycle[0]->month);
        $startPeriod = sprintf('%04d%02d', $startYear, $startMonth);

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loanTypeId)
            ->first();

        $paymentMonths = $loanType ? (int)$loanType->loan_type_duration : count($cycle);

        $loanId = DB::table('sacco_loans')->insertGetId([
            'loan_member'                      => $memberId,
            'loan_loan_type'                   => $loanTypeId,
            'loan_loan_category'               => 1,
            'loan_amount'                      => $initialOutstanding,
            'loan_insurance'                   => 0,
            'loan_commision'                   => 0,

            'loan_taken_period'                => $startPeriod,
            'loan_taken_start_period'          => $startPeriod,
            'loan_start_deduction_period'      => $startPeriod,

            'loan_payment_period'              => $paymentMonths,

            'loan_interest_payable'            => 0,
            'loan_monthly_repayment_amount'    => $monthlyTotal,
            'loan_monthly_repayment_principal' => $latestPrincipal,
            'loan_amount_guaranteed'           => $initialOutstanding,
            'loan_loan_paid'                   => 0,

            'loan_doc_no' => $isTopUp
                ? 'TOPUP-FROM-LOAN-' . $previousLoanId
                : 'IMPORT',



            'loan_old_loan_id' => $isTopUp ? $previousLoanId : null,
            'loan_description' => $isTopUp
                ? "TOP-UP IMPORT — {$loanTypeName} — {$this->companyFromFile($first)} — Loan No: {$previousLoanId} — {$sourceTag}"
                : "IMPORT — {$loanTypeName} — {$this->companyFromFile($first)} — {$sourceTag}",



            'loan_on'                          => now(),
            'loan_by'                          => 1,
            'loan_ip'                          => $isTopUp ? 'TOPUP' : 'MIGRATION',
            'loan_stoped'                      => 'N',
        ]);

        $summary['loan_cycles_created']++;

        /* Track last loan for top-up detection */
        $this->lastLoan[$memberId][$loanTypeId] = $loanId;


        /* ------------------------------------------
 * 5.5) INSERT SYSTEM ADJUSTMENT PAYMENTS
 * ------------------------------------------ */
        if (count($cycle) > 1) {

            for ($i = 1; $i < count($cycle); $i++) {

    $prev = $cycle[$i - 1];
    $cur  = $cycle[$i];

    /* ============================================================
     * NORMALISE VALUES (ONCE — DO NOT REPEAT)
     * ============================================================ */
    // $prevOutstanding = $this->moneyToCents($prev->outstanding_balance);
    // $curOutstanding  = $this->moneyToCents($cur->outstanding_balance);
    // $prevPrincipal   = $this->moneyToCents($prev->principal_paid);

    $prevOutstanding = $this->moneyToCents($prev->outstanding_balance);
$curOutstanding  = $this->moneyToCents($cur->outstanding_balance);
$prevPrincipal = $this->moneyToCents($prev->principal_paid ?? 0);

    /* ============================================================
     * HARD STOPS — ABSOLUTE (NO SYS-ADJ BEYOND THIS POINT)
     * ============================================================ */

    // 1. Loan closes on current row
    if ($curOutstanding === 0) {
        continue;
    }

    // 2. Principal clears or over-clears balance
//    $prevPrincipal = $this->moneyToCents($prev->principal_paid ?? 0);

// 2. Principal clears or over-clears balance
if ($prevOutstanding > 0 && abs($prevPrincipal) >= $prevOutstanding) {
    continue;
}



    // 3. Explained jump (new loan / top-up)
    if (is_numeric($cur->period_index) && (int)$cur->period_index > 0) {
        continue;
    }

    // 4. Missing principal — nothing to reconcile
    if ($prev->principal_paid === null) {
        continue;
    }

    // 5. Previous loan already closed
    if ($prevOutstanding === 0) {
        continue;
    }
    // 6. Current row has its own principal — handled by normal repayment
// if ($cur->principal_paid !== null && (float)$cur->principal_paid > 0) {
//     continue;
// }


    /* ============================================================
     * RECONCILIATION (ONLY REACHED IF ALL HARD STOPS PASSED)
     * ============================================================ */

$expectedNext = $prevOutstanding - $prevPrincipal;
$deltaCents   = $curOutstanding - $expectedNext;

    // Ignore rounding noise (≤ KES 2)
    if (abs($deltaCents) <= 200) {
        continue;
    }

    // Adjustment is negative of delta
    $amount = (-$deltaCents) / 100;

    $year  = (int) $cur->year;
    $month = $this->safeMonth($cur->month);
    if (!$year || !$month) {
        continue;
    }

    $period   = sprintf('%04d%02d', $year, $month);
    $datePaid = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

    DB::table('sacco_loan_payments')->insert([
        'loan_payments_amount'      => $amount,
        'loan_payments_interest'    => 0,
        'loan_payments_description' =>
            "SYSTEM ADJUSTMENT — BALANCE RECONCILE — {$this->sourceFileTag($cur)}",
        'loan_payments_docno'       => 'SYS-ADJ',
        'loan_payments_paid_in_by'  => 'SYSTEM',
        'loan_payments_period'      => $period,
        'loan_payments_paid_on'     => $datePaid,
        'loan_payments_loan_id'     => $loanId,
        'loan_payments_on'          => now(),
        'loan_payments_by'          => 1,
        'loan_payments_ip'          => 'SYSTEM',
    ]);

    DB::table('sacco_loans')
        ->where('loan_id', $loanId)
        ->update([
            'loan_loan_paid' => DB::raw("COALESCE(loan_loan_paid,0) + ({$amount})")
        ]);

    $summary['payments_inserted']++;
}


        }




        /* ------------------------------------------
     * 6) Insert normal payments for this cycle
     * ------------------------------------------ */
        foreach ($cycle as $row) {
            $principal = (float)$row->principal_paid;
            $interest  = (float)$row->interest_paid;

            $origYear  = (int)$row->year;
            $origMonth = $row->month;

            $validYear  = $this->safeYear($origYear);
            $validMonth = $this->safeMonth($origMonth);

            if ($validYear && $validMonth) {
                $period   = sprintf('%04d%02d', $validYear, $validMonth);
                $datePaid = Carbon::create($validYear, $validMonth, 1)->endOfMonth()->toDateString();
            } else {
                $period   = $startPeriod;
                $datePaid = Carbon::create($startYear, $startMonth, 1)->endOfMonth()->toDateString();
            }
            $paySourceTag = $this->sourceFileTag($row);


            $companyFromFile = $this->companyFromFile($row);


            DB::table('sacco_loan_payments')->insert([
                'loan_payments_amount'       => $principal,
                'loan_payments_interest'     => $interest,
                'loan_payments_docno'        => 'IMPORT',
                'loan_payments_description' =>
                "IMPORT REPAYMENT — {$loanTypeName} — {$companyFromFile} — {$paySourceTag}",
                'loan_payments_paid_in_by'   => $companyFromFile,
                'loan_payments_period'       => $period,
                'loan_payments_paid_on'      => $datePaid,
                'loan_payments_loan_id'      => $loanId,
                'loan_end_month_proc'        => 'N',
                'loan_payments_on'           => now(),
                'loan_payments_by'           => 1,
                'loan_payments_ip'           => 'MIGRATION',
            ]);

            DB::table('sacco_loans')
                ->where('loan_id', $loanId)
                ->update([
                    'loan_loan_paid' => DB::raw("COALESCE(loan_loan_paid,0) + {$principal}")
                ]);

            $summary['payments_inserted']++;
            $summary['rows_processed']++;
        }
    }



    /* ============================================================
     *  MEMBER RESOLUTION
     *  ADM → EXACT → TOKENS → COMPANY FUZZY
     * ============================================================ */
    protected function resolveMember($row)
    {
        /* 1) ADM NUMBER */
        $adm = trim((string)$row->adm_no);
        if ($adm !== '' && strtoupper($adm) !== 'ADM NO') {
            $memberId = DB::table('sacco_members')
                ->where('member_sacco_id', $adm)
                ->value('member_id');
            if ($memberId) return $memberId;
        }

        /* 2) CLEAN NAME (same as member importer) */
        $cleanName = strtoupper(trim(preg_replace('/\s+/', ' ', $row->raw_name ?? '')));
        if ($cleanName === '') {
            throw new \Exception("Loan import error — empty raw_name in staging ID {$row->id}");
        }

        /* Extract FIRST + LAST NAME */
        $parts = array_values(array_filter(explode(' ', $cleanName)));
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[count($parts) - 1] ?? '';

        /* 3) EXACT MATCH */
        $memberId = DB::table('sacco_members')
            ->where('member_name', $cleanName)
            ->value('member_id');
        if ($memberId) return $memberId;

        /* 4) TOKEN MATCH */
        $tokens = $this->tokens($cleanName);

        if (!empty($tokens)) {
            $q = DB::table('sacco_members');
            foreach ($tokens as $t) {
                if (strlen($t) >= 3) {
                    $q->where('member_name', 'LIKE', "%{$t}%");
                }
            }
            $m = $q->value('member_id');
            if ($m) return $m;

            if (count($tokens) >= 3) {
                foreach ($this->twoCombos($tokens) as $pair) {
                    [$a, $b] = explode(' ', $pair);
                    $m = DB::table('sacco_members')
                        ->where('member_name', 'LIKE', "%{$a}%")
                        ->where('member_name', 'LIKE', "%{$b}%")
                        ->value('member_id');
                    if ($m) return $m;
                }
            }
        }

        /* 5) COMPANY MATCH (clean company the SAME WAY as member import) */
        $company = strtoupper(trim(preg_replace('/\s+/', ' ', $row->company ?? '')));

        $candidates = DB::table('sacco_members')
            ->join('sacco_department', 'department_id', 'member_dept')
            ->join('sacco_company', 'company_id', 'department_company_id')
            ->where('company_name', $company)
            ->where('member_name', 'LIKE', "% {$lastName}")
            ->select('sacco_members.member_id', 'sacco_members.member_name')
            ->get();

        if ($candidates->count() === 1) {
            return $candidates[0]->member_id;
        }

        if ($candidates->count() > 1) {
            foreach ($candidates as $cand) {
                $candFirst = explode(' ', $cand->member_name)[0] ?? '';
                similar_text($firstName, $candFirst, $percent);
                if ($percent >= 80) return $cand->member_id;
            }
        }

        // throw new \Exception("Loan import: member NOT FOUND for '{$row->raw_name}' (ADM '{$adm}', COMPANY '{$company}')");
        $this->logUnresolvedMember($row, "No match after ADM, exact, tokens, and company fuzzy match.");
        return null; // allow importer to continue

    }

    /* ============================================================
     *  LOAN TYPE
     * ============================================================ */
    protected function resolveLoanType($raw)
    {
        $key = strtoupper(trim($raw));
        return $this->loanTypeMap[$key] ?? $key;   // IMPORTANT FIX
    }

    protected function getLoanTypeId($name)
    {
        $name = strtoupper(trim($name));

        // 1) Try to find an existing loan type
        $id = DB::table('sacco_loan_types')
            ->where('loan_type_name', $name)
            ->value('loan_type_id');

        if ($id) {
            return $id;
        }

        // 2) If NOT FOUND — create a new loan type using NORMAL LOAN defaults
        $clean = preg_replace('/[^A-Z]/', '', $name);
        $code = substr($clean, 0, 4);
        if ($code === '') {
            $code = 'TYPE';
        }


        $newId = DB::table('sacco_loan_types')->insertGetId([
            'loan_type_name'                => $name,
            'loan_type_interest'            => 12,
            'loan_type_interest_type'       => 'REDUCING BALANCE',
            'loan_type_duration'            => 36,
            'loan_type_guaranteable_percent' => 100,
            'loan_type_code'                => $code,
            'loan_type_max_amount'          => 0,
            'loan_type_qualification_period' => 0,
            'loan_type_acount'              => null,
            'loan_type_int_account'         => null,
            'loan_type_comm_account'        => null,
            'loan_type_insurable'           => '0',
            'loan_type_share_factor'        => 0,
            'loan_type_instant_qualification' => 0,
            'loan_type_by'                  => 1,
            'loan_type_ip'                  => 'MIGRATION',
        ]);

        return $newId;
    }


    /* ============================================================
     *  HELPERS
     * ============================================================ */
    protected function monthNum($month)
    {
        return $this->safeMonth($month);
    }


    protected function tokens(string $name): array
    {
        $name = strtolower($name);
        $name = preg_replace("/[^a-z'\s]/", ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return array_values(array_filter(explode(' ', $name)));
    }

    protected function twoCombos(array $parts): array
    {
        $out = [];
        for ($i = 0; $i < count($parts); $i++) {
            for ($j = $i + 1; $j < count($parts); $j++) {
                $pair = [$parts[$i], $parts[$j]];
                sort($pair);
                $out[] = implode(' ', $pair);
            }
        }
        return array_values(array_unique($out));
    }

    protected function logUnresolvedMember($row, $reason)
    {
        $msg = sprintf(
            "[UNRESOLVED] Name: %s | ADM: %s | Company: %s | Reason: %s",
            $row->raw_name,
            $row->adm_no ?? '',
            $row->company ?? '',
            $reason
        );

        // Write to custom log file
        \Log::channel('single')->warning($msg);

        // ALSO write to a custom log file
        file_put_contents(
            storage_path('logs/kass_loan_import.log'),
            date('Y-m-d H:i:s') . "  " . $msg . "\n",
            FILE_APPEND
        );
    }
    protected function safeYear($year)
    {
        return ($year >= 2000 && $year <= 2035) ? $year : null;
    }

    protected function safeMonth($monthText)
    {
        if ($monthText === null) return null;

        $clean = strtoupper(trim($monthText));

        // Numeric case
        if (is_numeric($clean)) {
            $m = intval($clean);
            return ($m >= 1 && $m <= 12) ? $m : null;
        }

        // Textual case → sanitize
        $clean = preg_replace('/[^A-Z]/', '', $clean);

        $months = [
            'JAN' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'APR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AUG' => 8,
            'SEP' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12
        ];

        return $months[$clean] ?? null;
    }

    protected function sourceFileTag($row): string
    {
        $file = $row->source_file ?? '';
        $file = trim((string)$file);

        if ($file === '') return 'FILE:UNKNOWN';

        // Keep it short + consistent
        return 'FILE:' . basename($file);
    }

    protected function companyFromFile($row): string
    {
        $file = $row->source_file ?? '';
        $file = basename($file);

        // Remove extension
        $file = preg_replace('/\.(xls|xlsx|csv)$/i', '', $file);

        // Remove year and anything after it
        $file = preg_replace('/\s*\d{4}.*/', '', $file);

        return strtoupper(trim($file));
    }



    protected function logAdjustment(array $data): void
    {
        file_put_contents(
            storage_path('logs/kass_loan_adjustments.log'),
            date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
    protected function moneyToCents($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Normalize strings like "50,000.00"
        $normalized = str_replace([',', ' '], '', (string) $value);

        return (int) round(((float) $normalized) * 100);
    }
}
