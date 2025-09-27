<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SharesClearanceController extends Controller
{
    /** 
     * Show main page with search input 
     */
    public function index(Request $request)
{
    // 🔍 Clean up search term (trim + collapse spaces)
    $term = preg_replace('/\s+/', ' ', trim($request->get('term', '')));

    $query = DB::table('sacco_members as m')
        ->leftJoin('sacco_department as d', 'd.department_id', '=', 'm.member_dept')
        ->leftJoin('sacco_company as c', 'c.company_id', '=', 'd.department_company_id')
        ->select(
            'm.member_id',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_total_share',
            'd.department_name',
            'c.company_name'
        )
        ->where('m.member_active', 'Y')
        ->where('m.member_deleted', 'N')
        ->where('m.member_total_share', '>', 0); // ✅ Only positive shares

    // Apply search filters if term exists
    if ($term !== '') {
        $query->where(function ($q) use ($term) {
            $q->where('m.member_name', 'like', "%{$term}%")
                ->orWhere('m.member_sacco_id', 'like', "%{$term}%")
                ->orWhere('m.member_national_id', 'like', "%{$term}%")
                ->orWhere('m.member_phone_no', 'like', "%{$term}%")
                ->orWhere('c.company_name', 'like', "%{$term}%");
        });
    }

    // ✅ Fetch the members now
    $members = $query->orderBy('m.member_name')
        ->limit(20)
        ->get();

    // ✅ Fetch the active accounting period
    $activePeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->value('period_name');

    if (!$activePeriod) {
        abort(400, "No active accounting period found. Please set an active period first.");
    }

    return view('shares_clearance.index', [
        'members'      => $members,
        'term'         => $term,
        'activePeriod' => $activePeriod,
    ]);
}
    /**
     * AJAX: Search members (limit 20)
     */
    public function searchMembers(Request $request)
    {
        $term = trim($request->get('term', ''));

        $members = DB::table('sacco_members as m')
            ->leftJoin('sacco_department as d', 'd.department_id', '=', 'm.member_dept')
            ->leftJoin('sacco_company as c', 'c.company_id', '=', 'd.department_company_id')
            ->select(
                'm.member_id',
                'm.member_name',
                'm.member_national_id',
                'm.member_sacco_id',
                'm.member_phone_no',
                'c.company_name',
                'd.department_name'
            )
            ->where('m.member_active', 'Y')
            ->where('m.member_deleted', 'N')
            ->when($term, function ($q) use ($term) {
                $q->where(function ($q2) use ($term) {
                    $q2->where('m.member_name', 'like', "%{$term}%")
                        ->orWhere('m.member_national_id', 'like', "%{$term}%")
                        ->orWhere('m.member_sacco_id', 'like', "%{$term}%")
                        ->orWhere('m.member_phone_no', 'like', "%{$term}%")
                        ->orWhere('c.company_name', 'like', "%{$term}%")
                        ->orWhere('d.department_name', 'like', "%{$term}%");
                });
            })
            ->orderBy('m.member_name')
            ->limit(20)
            ->get();

        $results = $members->map(function ($m) {
            return [
                'id'   => $m->member_id,
                'text' => "{$m->member_name} ({$m->member_sacco_id}) - {$m->member_national_id} - {$m->member_phone_no} - {$m->company_name}"
            ];
        });

        return response()->json($results);
    }

    /**
     * Get loans for a member (used in modal)
     */
    public function getLoans($memberId)
{
    // Fetch member info
    $member = DB::table('sacco_members as m')
        ->leftJoin('sacco_department as d', 'd.department_id', '=', 'm.member_dept')
        ->leftJoin('sacco_company as c', 'c.company_id', '=', 'd.department_company_id')
        ->select(
            'm.member_id',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_total_share',
            'd.department_name',
            'c.company_name'
        )
        ->where('m.member_id', $memberId)
        ->first();

    if (!$member) {
        return response('<div class="p-4 text-danger">Member not found.</div>', 404);
    }

    // ✅ Fetch active period
    $activePeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->value('period_name');

    if (!$activePeriod) {
        return response('<div class="p-4 text-danger">No active accounting period found. Please set an active period first.</div>', 400);
    }

    $loans = DB::table('sacco_loans as l')
        ->leftJoin('sacco_loan_types as t', 't.loan_type_id', '=', 'l.loan_loan_type')
        ->where('l.loan_member', $memberId)
        ->whereRaw('l.loan_amount > IFNULL(l.loan_loan_paid,0)')
        ->select(
            'l.loan_id',
            'l.loan_doc_no',
            't.loan_type_name',
            'l.loan_taken_period',
            'l.loan_amount',
            DB::raw('IFNULL(l.loan_loan_paid,0) as loan_paid'),
            DB::raw('(l.loan_amount - IFNULL(l.loan_loan_paid,0)) as principal_balance'),
            DB::raw('l.loan_interest_payable as interest_balance'),
            DB::raw('((l.loan_amount - IFNULL(l.loan_loan_paid,0)) + l.loan_interest_payable) as total_balance')
        )
        ->get();

    // ✅ Pass $activePeriod to modal view
    return view('shares_clearance.modal_loans', compact('member', 'loans', 'activePeriod'));
}
    /**
     * Process clearance (apply shares to loans)
     */



    public function process(Request $request)
    {
        $totalSharesUsed    = 0;
        $totalPrincipalPaid = 0;

        $activePeriod = DB::table('sacco_period')
    ->where('period_active', 'Y')
    ->value('period_name');

if (!$activePeriod) {
    throw new \Exception("No active accounting period found. Please set an active period before clearing shares.");
}

$currentPeriod = $request->period ?? $activePeriod;


        $request->validate([
            'member_id' => [
                'required',
                'integer',
                Rule::exists('sacco_members', 'member_id')
                    ->where('member_active', 'Y')
                    ->where('member_deleted', 'N')
            ],
            'loan_ids'       => 'required|array',
            'loan_ids.*'     => 'integer|exists:sacco_loans,loan_id',
            'loan_payments'  => 'required|array',
            'loan_payments.*' => 'numeric|min:0',
            'use_interest'   => 'nullable|boolean'
        ]);

        DB::beginTransaction();
        try {
            // Lock member
            $member = DB::table('sacco_members')
                ->where('member_id', $request->member_id)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                throw new \Exception("Member not found or inactive");
            }

            $availableShares = $member->member_total_share;
            $useInterest     = $request->boolean('use_interest', true);

            // Ensure total allocation does not exceed shares
            $totalToUse = array_sum($request->loan_payments);
            if ($totalToUse > $availableShares) {
                throw new \Exception("You cannot use more than available shares (" . number_format($availableShares, 2) . ")");
            }

            // Get default share account
            $defaultShareAcc = DB::table('sacco_defaults')
                ->where('default_name', 'default_share_account')
                ->value('default_value');

            if (!$defaultShareAcc || !is_numeric($defaultShareAcc)) {
                throw new \Exception("Default share account not configured");
            }

            // Process each loan
            foreach ($request->loan_ids as $loanId) {
                $amount = $request->loan_payments[$loanId] ?? 0;
                if ($amount <= 0) continue;

                $loan = DB::table('sacco_loans as l')
                    ->leftJoin('sacco_loan_types as t', 't.loan_type_id', '=', 'l.loan_loan_type')
                    ->where('l.loan_id', $loanId)
                    ->where('l.loan_member', $member->member_id)
                    ->select(
                        'l.*',
                        't.loan_type_name',
                        't.loan_type_interest',
                        't.loan_type_interest_type',
                        't.loan_type_acount',
                        't.loan_type_int_account'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$loan) {
                    throw new \Exception("Loan {$loanId} not found for member");
                }

                // Calculate outstanding
                $loanAmount   = is_numeric($loan->loan_amount) ? $loan->loan_amount : 0;
                $loanPaid     = is_numeric($loan->loan_loan_paid) ? $loan->loan_loan_paid : 0;

                $principalOutstanding = max(0, $loanAmount - $loanPaid);

                $expectedInterest     = 0;

                

                $alreadyPaidInterest = DB::table('sacco_loan_payments')
                    ->where('loan_payments_loan_id', $loan->loan_id)
                    ->where('loan_payments_period', $currentPeriod)
                    ->where('loan_payments_interest', '>', 0)
                    ->exists();



                if ($useInterest) {
                    if ($loan->loan_type_interest_type === 'REDUCING BALANCE') {
                        $expectedInterest = 0;

                        if (!$alreadyPaidInterest) {
                            $monthlyRate      = ($loan->loan_type_interest ?? 0) / 100 / 12;
                            $expectedInterest = $principalOutstanding * $monthlyRate;
                        }

                        $interestPaid  = min($amount, $expectedInterest);
                        $principalPaid = $amount - $interestPaid;
                    } elseif ($loan->loan_type_interest_type === 'FIXED INTEREST') {
                        $flatRate         = ($loan->loan_type_interest ?? 0) / 100 / 12;
                        $expectedInterest = $amount * $flatRate;
                    }
                }

                $maxPayable = $principalOutstanding + $expectedInterest;

                if ($amount > $maxPayable) {
                    throw new \Exception("Payment for Loan {$loan->loan_doc_no} ({$amount}) exceeds payable balance (" . number_format($maxPayable, 2) . ")");
                }

                // Validate accounts
                if (!$loan->loan_type_acount || !is_numeric($loan->loan_type_acount)) {
                    throw new \Exception("Loan principal account not configured for {$loan->loan_type_name}");
                }
                // if ($useInterest && $expectedInterest > 0 && (!$loan->loan_type_int_account || !is_numeric($loan->loan_type_int_account))) {
                //     throw new \Exception("Loan interest account not configured for {$loan->loan_type_name}");
                // }
                if ($amount < $expectedInterest) {
                    throw new \Exception("Payment ({$amount}) is less than the required interest ({$expectedInterest}). You must clear full interest first.");
                }

                $interestPaid  = $expectedInterest;
                $principalPaid = $amount - $interestPaid;


                $totalSharesUsed    += $amount;
                $totalPrincipalPaid += $principalPaid;



                DB::table('sacco_shares')->insert([
                    'share_member_id'     => $member->member_id,
                    'share_amount_paying' => -$amount,
                    'share_period'        => $currentPeriod,                 // ✅ Period (e.g. 202509)
                    'share_paid_by'       => $member->member_name,           // ✅ Member paying for self
                    'share_description'   => "Shares Clearance for Loan {$loan->loan_doc_no}",
                    'share_doc_no'        => $loan->loan_doc_no,
                    'share_date_paid'     => now(),
                    'share_by'            => Auth::id(),                     // Staff who processed
                    'share_ip'            => $request->ip(),
                    'share_transdate'     => now(),
                ]);


                // Update loan balances
                DB::table('sacco_loans')
                    ->where('loan_id', $loan->loan_id)
                    ->update([
                        'loan_loan_paid'       => ($loan->loan_loan_paid ?? 0) + $principalPaid,
                        //'loan_interest_payable'=> max(0, $loan->loan_interest_payable - $interestPaid),
                    ]);

                // Insert loan payment record
                DB::table('sacco_loan_payments')->insert([
                    'loan_payments_loan_id'   => $loan->loan_id,
                    'loan_payments_amount'    => $principalPaid,
                    'loan_payments_interest'  => $interestPaid,           // portion for interest
                    'loan_payments_period'    => $currentPeriod,
                    'loan_payments_paid_on'   => now()->toDateString(),   // aligns with schema
                    'loan_payments_description' => 'Shares Clearance',
                    'loan_payments_docno'     => $loan->loan_doc_no,
                    'loan_payments_by'        => Auth::id(),
                    'loan_payments_ip'        => $request->ip(),
                    'loan_payments_on'        => now(),                   // timestamp
                ]);

                $memberNo = "{$member->member_sacco_id} ({$member->member_id})";


                $memberLabel = "{$member->member_name} ({$member->member_sacco_id} / {$member->member_id})";
                $loanName    = ($loan->loan_type_name ?? 'Loan') . " ({$loan->loan_id})";
                $docNo       = $loan->loan_doc_no ?? "SHARESCLR-" . $loan->loan_id;


                $baseTrans = [
                    'accounts_trans_period'     => $currentPeriod,
                    'accounts_trans_doc_no'     => $docNo,
                    'accounts_trans_source'     => 'SharesClearance',
                    'accounts_trans_dat_date'   => now()->toDateString(),
                    'accounts_trans_transdate'  => now(),
                    'accounts_trans_user_id'    => Auth::id(),
                    'accounts_trans_ip'         => $request->ip(),
                    'accounts_trans_member_id'  => $member->member_id,
                    'accounts_trans_app_name'   => 'SaccoSystem',
                    'accounts_trans_cash_in_already' => 'N',
                    'accounts_trans_reconsiled' => 'N',
                    'accounts_trans_payment_type' => 'SharesClearance',
                ];

                if ($principalPaid > 0) {
                    DB::table('sacco_accounts_trans')->insert(array_merge($baseTrans, [
                        'accounts_trans_sub_account' => $loan->loan_type_acount,
                        'accounts_trans_debit'       => 0,
                        'accounts_trans_credit'      => $principalPaid,
                        'accounts_trans_decription'  => "Shares clearance (Principal) - {$loanName} [{$docNo}] for Member {$memberLabel}",
                    ]));
                }

                if ($interestPaid > 0) {
                    DB::table('sacco_accounts_trans')->insert(array_merge($baseTrans, [
                        'accounts_trans_sub_account' => $loan->loan_type_int_account,
                        'accounts_trans_debit'       => 0,
                        'accounts_trans_credit'      => $interestPaid,
                        'accounts_trans_decription'  => "Shares clearance (Interest) - {$loanName} [{$docNo}] for Member {$memberLabel}",
                    ]));
                }

                DB::table('sacco_accounts_trans')->insert(array_merge($baseTrans, [
                    'accounts_trans_sub_account' => $defaultShareAcc,
                    'accounts_trans_debit'       => $amount,
                    'accounts_trans_credit'      => 0,
                    'accounts_trans_decription'  => "Shares clearance applied to {$loanName} [{$docNo}] for Member {$memberLabel}",
                ]));
            }

            // ✅ Update member totals
            DB::table('sacco_members')
                ->where('member_id', $member->member_id)
                ->update([
                    'member_total_share' => DB::raw("member_total_share - {$totalSharesUsed}"),
                    'member_total_loan'  => DB::raw("member_total_loan - {$totalPrincipalPaid}")
                ]);

            DB::commit();
            return back()->with('success', 'Shares clearance completed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
