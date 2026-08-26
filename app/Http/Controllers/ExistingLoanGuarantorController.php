<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use DomainException;
use Throwable;

class ExistingLoanGuarantorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | General constants
    |--------------------------------------------------------------------------
    */

    private float $moneyTolerance = 0.01;

    /*
    |--------------------------------------------------------------------------
    | Modal Context
    |--------------------------------------------------------------------------
    |
    | Called when "Add Guarantor" is clicked.
    |
    | Returns authoritative information about the selected loan:
    |
    | - borrower
    | - loan type
    | - loan balance
    | - guarantee requirement
    | - currently tied guarantee
    | - amount still available to guarantee
    | - number of current guarantors
    |
    */

    public function context(int $loan)
    {
        $loanRow = $this->getLoan($loan);

        if (!$loanRow) {
            return response()->json([
                'success' => false,
                'message' => 'Loan not found.',
            ], 404);
        }

        $summary = $this->buildLoanGuaranteeSummary($loanRow);

        return response()->json([
            'success' => true,

            'loan' => [
                'loan_id' => (int) $loanRow->loan_id,

                'loan_type_id' =>
                    (int) $loanRow->loan_loan_type,

                'loan_type_name' =>
                    (string) $loanRow->loan_type_name,

                'borrower_id' =>
                    (int) $loanRow->loan_member,

                'borrower_name' =>
                    (string) $loanRow->borrower_name,

                'borrower_sacco_id' =>
                    (string) $loanRow->borrower_sacco_id,

                'loan_amount' =>
                    round((float) $loanRow->loan_amount, 2),

                'loan_paid' =>
                    round((float) $loanRow->loan_loan_paid, 2),

                'loan_balance' =>
                    $summary['loan_balance'],

                'guarantee_percent' =>
                    $summary['guarantee_percent'],

                'guarantee_required' =>
                    $summary['guarantee_required'],

                'currently_guaranteed' =>
                    $summary['currently_guaranteed'],

                'guarantee_remaining' =>
                    $summary['guarantee_remaining'],

                'current_guarantor_count' =>
                    $summary['current_guarantor_count'],

                'maximum_guarantors' =>
                    $summary['maximum_guarantors'],

                'can_add_guarantor' =>
                    $summary['can_add_guarantor'],

                'blocking_message' =>
                    $summary['blocking_message'],
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | AJAX Search
    |--------------------------------------------------------------------------
    |
    | Searches active SACCO members who could potentially guarantee the
    | SPECIFIC loan supplied in the URL.
    |
    | Example:
    |
    | GET /loans/14654/guarantors/search?q=mary
    |
    */

    public function search(Request $request, int $loan)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'q' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter at least 2 characters.',
                'results' => [],
            ], 422);
        }

        $loanRow = $this->getLoan($loan);

        if (!$loanRow) {
            return response()->json([
                'success' => false,
                'message' => 'Loan not found.',
                'results' => [],
            ], 404);
        }

        $summary = $this->buildLoanGuaranteeSummary(
            $loanRow
        );

        if (!$summary['can_add_guarantor']) {
            return response()->json([
                'success' => false,
                'message' => $summary['blocking_message'],
                'results' => [],
            ], 422);
        }

        $query = trim(
            (string) $request->input('q')
        );

        /*
         * Existing guarantors on THIS loan.
         *
         * A member already attached to the loan must not be offered again.
         *
         * This includes a partially/fully freed guarantor while the
         * guarantor row remains a valid non-deleted record.
         */
        $existingGuarantorIds = DB::table(
            'sacco_loan_guarantors'
        )
            ->where(
                'loan_guar_loan_id',
                $loan
            )
            ->whereRaw(
                "COALESCE(loan_guar_deleted, 'N') <> 'Y'"
            )
            ->pluck(
                'loan_guar_guarantor_id'
            )
            ->map(fn ($id) => (int) $id)
            ->all();

        /*
         * Search a bounded candidate set.
         */
        $membersQuery = DB::table(
            'sacco_members'
        )
            ->where(
                'member_active',
                'Y'
            )
            ->whereRaw(
                "COALESCE(member_deleted, 'N') <> 'Y'"
            )
            ->where(function ($q) use ($query) {

                $search = '%' . $query . '%';

                $q->where(
                    'member_name',
                    'LIKE',
                    $search
                )
                    ->orWhere(
                        'member_sacco_id',
                        'LIKE',
                        $search
                    )
                    ->orWhere(
                        'member_national_id',
                        'LIKE',
                        $search
                    )
                    ->orWhere(
                        'member_phone_no',
                        'LIKE',
                        $search
                    )
                    ->orWhere(
                        'member_email',
                        'LIKE',
                        $search
                    );
            });

        if (!empty($existingGuarantorIds)) {
            $membersQuery->whereNotIn(
                'member_id',
                $existingGuarantorIds
            );
        }

        $members = $membersQuery
            ->select(
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_phone_no',
                'member_total_share',
                'member_tied_shares',
                'member_tied_shares_self'
            )
            ->orderBy('member_name')
            ->limit(20)
            ->get();

        if ($members->isEmpty()) {
            return response()->json([
                'success' => true,
                'results' => [],
            ]);
        }

        $memberIds = $members
            ->pluck('member_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Pending guarantee exposure
        |--------------------------------------------------------------------------
        |
        | Do not permit pending loan applications to reserve the same savings
        | while this existing-loan operation is also trying to tie them.
        |
        */

        $pendingRows = DB::table(
            'sacco_loan_batch_guarantors_members as g'
        )
            ->join(
                'sacco_loan_batch_trans_members as t',
                'g.guarantors_loan_batch_trans_id',
                '=',
                't.batch_trans_id'
            )
            ->whereIn(
                'g.guarantors_guarantor_id',
                $memberIds
            )
            ->whereRaw(
                "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
            )
            ->whereRaw(
                "COALESCE(t.batch_trans_deleted, 'N') <> 'Y'"
            )
            ->whereRaw(
                "COALESCE(t.batch_trans_updated, 'N') = 'N'"
            )
            ->select(
                'g.guarantors_guarantor_id',
                'g.guarantors_amount_guaranteed',
                't.batch_trans_member_id'
            )
            ->get();

        $pendingByMember = [];

        foreach ($pendingRows as $pending) {

            $guarantorId =
                (int) $pending->guarantors_guarantor_id;

            $amount =
                max(
                    0,
                    (float) $pending->guarantors_amount_guaranteed
                );

            if (!isset($pendingByMember[$guarantorId])) {
                $pendingByMember[$guarantorId] = [
                    'other' => 0.00,
                    'self' => 0.00,
                    'total' => 0.00,
                ];
            }

            if (
                (int) $pending->batch_trans_member_id
                ===
                $guarantorId
            ) {
                $pendingByMember[$guarantorId]['self']
                    += $amount;
            } else {
                $pendingByMember[$guarantorId]['other']
                    += $amount;
            }

            $pendingByMember[$guarantorId]['total']
                += $amount;
        }

        $results = [];

        foreach ($members as $member) {

            $memberId =
                (int) $member->member_id;

            $pending =
                $pendingByMember[$memberId]
                ?? [
                    'other' => 0,
                    'self' => 0,
                    'total' => 0,
                ];

            /*
            |--------------------------------------------------------------------------
            | HARD SAVINGS CEILING
            |--------------------------------------------------------------------------
            |
            | User rule:
            |
            | A member cannot guarantee more than the savings they actually
            | hold.
            |
            | Therefore ALL guarantee exposure counts:
            |
            | member_tied_shares
            | +
            | member_tied_shares_self
            | +
            | pending other guarantees
            | +
            | pending self guarantees
            |
            */

            $totalSavings =
                max(
                    0,
                    (float) $member->member_total_share
                );

            $alreadyTied =
                max(
                    0,
                    (float) $member->member_tied_shares
                )
                +
                max(
                    0,
                    (float) $member->member_tied_shares_self
                );

            $pendingExposure =
                max(
                    0,
                    (float) $pending['total']
                );

            $availableCapacity =
                max(
                    0,
                    $totalSavings
                    - $alreadyTied
                    - $pendingExposure
                );

            /*
             * No need to show members with no capacity.
             */
            if (
                $availableCapacity
                <= $this->moneyTolerance
            ) {
                continue;
            }

            /*
             * A guarantor can never be offered more than the loan itself
             * still needs.
             */
            $maximumForThisLoan = min(
                $availableCapacity,
                $summary['guarantee_remaining']
            );

            if (
                $maximumForThisLoan
                <= $this->moneyTolerance
            ) {
                continue;
            }

            $results[] = [
                'member_id' =>
                    $memberId,

                'member_name' =>
                    (string) $member->member_name,

                'member_sacco_id' =>
                    (string) $member->member_sacco_id,

                'member_national_id' =>
                    (string) ($member->member_national_id ?? ''),

                'member_phone_no' =>
                    (string) ($member->member_phone_no ?? ''),

                'is_self_guarantee' =>
                    $memberId === (int) $loanRow->loan_member,

                'total_savings' =>
                    round($totalSavings, 2),

                'tied_to_others' =>
                    round(
                        (float) $member->member_tied_shares,
                        2
                    ),

                'tied_to_self' =>
                    round(
                        (float) $member->member_tied_shares_self,
                        2
                    ),

                'pending_guarantees' =>
                    round($pendingExposure, 2),

                'available_capacity' =>
                    round($availableCapacity, 2),

                'maximum_for_this_loan' =>
                    round($maximumForThisLoan, 2),
            ];
        }

        return response()->json([
            'success' => true,

            'loan_id' =>
                (int) $loanRow->loan_id,

            'guarantee_remaining' =>
                $summary['guarantee_remaining'],

            'results' =>
                array_values($results),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Add Guarantor To Existing Loan
    |--------------------------------------------------------------------------
    |
    | THIS is the authoritative financial operation.
    |
    | Nothing sent by JavaScript is trusted other than:
    |
    | - guarantor_member_id
    | - amount
    | - reason
    |
    | borrower, loan type, balances, capacity, current guarantees etc are
    | all resolved again from the database.
    |
    */

    public function store(Request $request, int $loan)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'guarantor_member_id' => [
                    'required',
                    'integer',
                    'exists:sacco_members,member_id',
                ],

                'amount' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'reason' => [
                    'required',
                    'string',
                    'min:3',
                    'max:500',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $guarantorId =
            (int) $request->input(
                'guarantor_member_id'
            );

        $amount =
            round(
                (float) $request->input('amount'),
                2
            );

        $reason =
            trim(
                (string) $request->input('reason')
            );

        try {

            $result = DB::transaction(
                function () use (
                    $loan,
                    $guarantorId,
                    $amount,
                    $reason,
                    $request
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock the loan
                    |--------------------------------------------------------------------------
                    */

                    $loanRow = DB::table(
                        'sacco_loans'
                    )
                        ->where(
                            'loan_id',
                            $loan
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$loanRow) {
                        throw new DomainException(
                            'Loan not found.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Load product
                    |--------------------------------------------------------------------------
                    */

                    $loanType = DB::table(
                        'sacco_loan_types'
                    )
                        ->where(
                            'loan_type_id',
                            $loanRow->loan_loan_type
                        )
                        ->whereRaw(
                            "COALESCE(loan_type_deleted, 'N') <> 'Y'"
                        )
                        ->first();

                    if (!$loanType) {
                        throw new DomainException(
                            'The loan product attached to this loan is invalid.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Current outstanding loan balance
                    |--------------------------------------------------------------------------
                    */

                    $loanBalance = round(
                        max(
                            0,
                            (float) $loanRow->loan_amount
                            -
                            (float) ($loanRow->loan_loan_paid ?? 0)
                        ),
                        2
                    );

                    if (
                        $loanBalance
                        <= $this->moneyTolerance
                    ) {
                        throw new DomainException(
                            'A guarantor cannot be added because this loan is already fully paid.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Product guarantee rule
                    |--------------------------------------------------------------------------
                    */

                    $guaranteePercent =
                        max(
                            0,
                            (float) (
                                $loanType
                                ->loan_type_guaranteable_percent
                                ?? 0
                            )
                        );

                    if (
                        $guaranteePercent
                        <= 0
                    ) {
                        throw new DomainException(
                            'This loan product is configured not to require guarantors.'
                        );
                    }

                    /*
                     * Do not allow a product configuration to result in a
                     * guarantee larger than the current loan exposure.
                     *
                     * Example:
                     *
                     * balance = 100,000
                     * guarantee percent = 100%
                     * max guarantee = 100,000
                     *
                     * guarantee percent = 50%
                     * max guarantee = 50,000
                     */
                    $configuredGuarantee =
                        round(
                            $loanBalance
                            * ($guaranteePercent / 100),
                            2
                        );

                    $maximumLoanGuarantee =
                        round(
                            min(
                                $loanBalance,
                                $configuredGuarantee
                            ),
                            2
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Lock existing guarantor rows for this loan
                    |--------------------------------------------------------------------------
                    */

                    $existingGuarantors =
                        DB::table(
                            'sacco_loan_guarantors'
                        )
                            ->where(
                                'loan_guar_loan_id',
                                $loan
                            )
                            ->whereRaw(
                                "COALESCE(loan_guar_deleted, 'N') <> 'Y'"
                            )
                            ->lockForUpdate()
                            ->get();

                    /*
                    |--------------------------------------------------------------------------
                    | No member may guarantee the SAME loan twice
                    |--------------------------------------------------------------------------
                    */

                    $alreadyGuarantor =
                        $existingGuarantors
                            ->contains(
                                function ($row) use ($guarantorId) {
                                    return
                                        (int) $row->loan_guar_guarantor_id
                                        ===
                                        $guarantorId;
                                }
                            );

                    if ($alreadyGuarantor) {
                        throw new DomainException(
                            'This member is already a guarantor for this loan.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Current ACTIVE guarantee
                    |--------------------------------------------------------------------------
                    |
                    | Amount freed is no longer tied.
                    |
                    */

                    $currentlyGuaranteed =
                        round(
                            $existingGuarantors
                                ->sum(
                                    function ($row) {
                                        return max(
                                            0,
                                            (float) (
                                                $row
                                                ->loan_guar_amount_guaranteed
                                                ?? 0
                                            )
                                            -
                                            (float) (
                                                $row
                                                ->loan_guar_amount_freed
                                                ?? 0
                                            )
                                        );
                                    }
                                ),
                            2
                        );

                    $remainingGuarantee =
                        round(
                            max(
                                0,
                                $maximumLoanGuarantee
                                -
                                $currentlyGuaranteed
                            ),
                            2
                        );

                    if (
                        $remainingGuarantee
                        <= $this->moneyTolerance
                    ) {
                        throw new DomainException(
                            'This loan is already fully guaranteed. No additional guarantor can be added.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Loan may NEVER be over-guaranteed
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $amount
                        >
                        ($remainingGuarantee + $this->moneyTolerance)
                    ) {
                        throw new DomainException(
                            'This amount would over-guarantee the loan. '
                            . 'The maximum additional guarantee allowed is KES '
                            . number_format(
                                $remainingGuarantee,
                                2
                            )
                            . '.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Maximum guarantor count
                    |--------------------------------------------------------------------------
                    */

                    $maximumGuarantors =
                        (int) (
                            DB::table(
                                'sacco_defaults'
                            )
                                ->where(
                                    'default_name',
                                    'maximum_no_of_guarantors'
                                )
                                ->value(
                                    'default_value'
                                )
                            ?? 3
                        );

                    $maximumGuarantors =
                        max(
                            1,
                            $maximumGuarantors
                        );

                    /*
                     * Count only guarantors that currently have tied exposure.
                     */
                    $activeGuarantorCount =
                        $existingGuarantors
                            ->filter(
                                function ($row) {
                                    return (
                                        (float) (
                                            $row
                                            ->loan_guar_amount_guaranteed
                                            ?? 0
                                        )
                                        -
                                        (float) (
                                            $row
                                            ->loan_guar_amount_freed
                                            ?? 0
                                        )
                                    )
                                    >
                                    $this->moneyTolerance;
                                }
                            )
                            ->count();

                    if (
                        $activeGuarantorCount
                        >= $maximumGuarantors
                    ) {
                        throw new DomainException(
                            "This loan already has the maximum permitted number of guarantors ({$maximumGuarantors})."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Lock proposed guarantor/member
                    |--------------------------------------------------------------------------
                    */

                    $guarantor =
                        DB::table(
                            'sacco_members'
                        )
                            ->where(
                                'member_id',
                                $guarantorId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$guarantor) {
                        throw new DomainException(
                            'Guarantor not found.'
                        );
                    }

                    if (
                        strtoupper(
                            (string) (
                                $guarantor
                                ->member_active
                                ?? 'N'
                            )
                        )
                        !== 'Y'
                    ) {
                        throw new DomainException(
                            'The selected member is not active.'
                        );
                    }

                    if (
                        strtoupper(
                            (string) (
                                $guarantor
                                ->member_deleted
                                ?? 'N'
                            )
                        )
                        === 'Y'
                    ) {
                        throw new DomainException(
                            'The selected member is deleted.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Pending guarantee exposure
                    |--------------------------------------------------------------------------
                    |
                    | Lock pending rows too so that AJAX availability cannot
                    | become authoritative.
                    |
                    */

                    $pendingRows =
                        DB::table(
                            'sacco_loan_batch_guarantors_members as g'
                        )
                            ->join(
                                'sacco_loan_batch_trans_members as t',
                                'g.guarantors_loan_batch_trans_id',
                                '=',
                                't.batch_trans_id'
                            )
                            ->where(
                                'g.guarantors_guarantor_id',
                                $guarantorId
                            )
                            ->whereRaw(
                                "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
                            )
                            ->whereRaw(
                                "COALESCE(t.batch_trans_deleted, 'N') <> 'Y'"
                            )
                            ->whereRaw(
                                "COALESCE(t.batch_trans_updated, 'N') = 'N'"
                            )
                            ->select(
                                'g.guarantors_amount_guaranteed',
                                't.batch_trans_member_id'
                            )
                            ->lockForUpdate()
                            ->get();

                    $pendingOther = 0.00;
                    $pendingSelf = 0.00;

                    foreach ($pendingRows as $pending) {

                        $pendingAmount =
                            max(
                                0,
                                (float) (
                                    $pending
                                    ->guarantors_amount_guaranteed
                                    ?? 0
                                )
                            );

                        if (
                            (int) $pending
                                ->batch_trans_member_id
                            ===
                            $guarantorId
                        ) {
                            $pendingSelf +=
                                $pendingAmount;
                        } else {
                            $pendingOther +=
                                $pendingAmount;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | HARD MEMBER SAVINGS LIMIT
                    |--------------------------------------------------------------------------
                    |
                    | A member cannot have guarantee exposure greater than
                    | actual savings.
                    |
                    | We deliberately DO NOT multiply savings by
                    | max_guarantor_factor here.
                    |
                    | Existing tied exposure in BOTH pools counts.
                    |
                    */

                    $totalSavings =
                        max(
                            0,
                            (float) (
                                $guarantor
                                ->member_total_share
                                ?? 0
                            )
                        );

                    $tiedOthers =
                        max(
                            0,
                            (float) (
                                $guarantor
                                ->member_tied_shares
                                ?? 0
                            )
                        );

                    $tiedSelf =
                        max(
                            0,
                            (float) (
                                $guarantor
                                ->member_tied_shares_self
                                ?? 0
                            )
                        );

                    $availableCapacity =
                        round(
                            max(
                                0,
                                $totalSavings
                                -
                                $tiedOthers
                                -
                                $tiedSelf
                                -
                                $pendingOther
                                -
                                $pendingSelf
                            ),
                            2
                        );

                    if (
                        $amount
                        >
                        ($availableCapacity + $this->moneyTolerance)
                    ) {
                        throw new DomainException(
                            'The selected member does not have enough free savings to guarantee this amount. '
                            . 'Available capacity is KES '
                            . number_format(
                                $availableCapacity,
                                2
                            )
                            . '.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Insert permanent loan guarantor
                    |--------------------------------------------------------------------------
                    */

                    DB::table(
                        'sacco_loan_guarantors'
                    )
                        ->insert([
                            'loan_guar_loan_id' =>
                                $loan,

                            'loan_guar_guarantor_id' =>
                                $guarantorId,

                            'loan_guar_amount_guaranteed' =>
                                $amount,

                            'loan_guar_amount_freed' =>
                                0,

                            'loan_guar_description' =>
                                'Added to existing loan: '
                                . $reason,

                            'loan_guar_transfered' =>
                                null,

                            'loan_guar_by' =>
                                Auth::id(),

                            'loan_guar_on' =>
                                now(),

                            'loan_guar_ip' =>
                                $request->ip(),

                            'loan_guar_deleted' =>
                                'N',
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Tie the correct member share bucket
                    |--------------------------------------------------------------------------
                    */

                    $isSelfGuarantee =
                        $guarantorId
                        ===
                        (int) $loanRow->loan_member;

                    if ($isSelfGuarantee) {

                        DB::table(
                            'sacco_members'
                        )
                            ->where(
                                'member_id',
                                $guarantorId
                            )
                            ->increment(
                                'member_tied_shares_self',
                                $amount
                            );

                    } else {

                        DB::table(
                            'sacco_members'
                        )
                            ->where(
                                'member_id',
                                $guarantorId
                            )
                            ->increment(
                                'member_tied_shares',
                                $amount
                            );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Reconcile loan guarantee total
                    |--------------------------------------------------------------------------
                    |
                    | We use ACTIVE guarantee exposure:
                    |
                    | amount guaranteed - amount freed
                    |
                    */

                    $newActiveGuarantee =
                        round(
                            $currentlyGuaranteed
                            +
                            $amount,
                            2
                        );

                    DB::table(
                        'sacco_loans'
                    )
                        ->where(
                            'loan_id',
                            $loan
                        )
                        ->update([
                            'loan_amount_guaranteed' =>
                                $newActiveGuarantee,
                        ]);

                    return [
                        'loan_id' =>
                            $loan,

                        'borrower_id' =>
                            (int) $loanRow->loan_member,

                        'guarantor_id' =>
                            $guarantorId,

                        'guarantor_name' =>
                            (string) $guarantor->member_name,

                        'amount' =>
                            $amount,

                        'currently_guaranteed' =>
                            $newActiveGuarantee,

                        'guarantee_remaining' =>
                            round(
                                max(
                                    0,
                                    $maximumLoanGuarantee
                                    -
                                    $newActiveGuarantee
                                ),
                                2
                            ),
                    ];

                },

                /*
                 * Retry transaction if MySQL detects a deadlock.
                 */
                5
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Guarantor added successfully.',

                'data' =>
                    $result,
            ]);

        } catch (DomainException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {

            Log::error(
                'Failed to add guarantor to existing loan',
                [
                    'loan_id' =>
                        $loan,

                    'guarantor_id' =>
                        $guarantorId,

                    'amount' =>
                        $amount,

                    'user_id' =>
                        Auth::id(),

                    'message' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'The guarantor could not be added. Please try again or contact support.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Loan loader
    |--------------------------------------------------------------------------
    */

    private function getLoan(int $loan): ?object
    {
        return DB::table(
            'sacco_loans as l'
        )
            ->join(
                'sacco_loan_types as lt',
                'l.loan_loan_type',
                '=',
                'lt.loan_type_id'
            )
            ->join(
                'sacco_members as m',
                'l.loan_member',
                '=',
                'm.member_id'
            )
            ->where(
                'l.loan_id',
                $loan
            )
            ->select(
                'l.loan_id',
                'l.loan_member',
                'l.loan_loan_type',
                'l.loan_amount',
                'l.loan_loan_paid',
                'l.loan_amount_guaranteed',
                'l.loan_stoped',

                'lt.loan_type_name',
                'lt.loan_type_guaranteable_percent',

                'm.member_name as borrower_name',
                'm.member_sacco_id as borrower_sacco_id'
            )
            ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | Loan guarantee summary
    |--------------------------------------------------------------------------
    */

    private function buildLoanGuaranteeSummary(
        object $loan
    ): array {

        $loanBalance =
            round(
                max(
                    0,
                    (float) $loan->loan_amount
                    -
                    (float) (
                        $loan->loan_loan_paid
                        ?? 0
                    )
                ),
                2
            );

        $guaranteePercent =
            max(
                0,
                (float) (
                    $loan
                    ->loan_type_guaranteable_percent
                    ?? 0
                )
            );

        /*
         * Respect product percentage while still enforcing:
         *
         * guarantee <= outstanding loan balance
         */
        $configuredRequirement =
            round(
                $loanBalance
                *
                ($guaranteePercent / 100),
                2
            );

        $guaranteeRequired =
            round(
                min(
                    $loanBalance,
                    $configuredRequirement
                ),
                2
            );

        $guarantors =
            DB::table(
                'sacco_loan_guarantors'
            )
                ->where(
                    'loan_guar_loan_id',
                    $loan->loan_id
                )
                ->whereRaw(
                    "COALESCE(loan_guar_deleted, 'N') <> 'Y'"
                )
                ->get();

        $currentlyGuaranteed =
            round(
                $guarantors
                    ->sum(
                        function ($row) {

                            return max(
                                0,
                                (float) (
                                    $row
                                    ->loan_guar_amount_guaranteed
                                    ?? 0
                                )
                                -
                                (float) (
                                    $row
                                    ->loan_guar_amount_freed
                                    ?? 0
                                )
                            );
                        }
                    ),
                2
            );

        $guaranteeRemaining =
            round(
                max(
                    0,
                    $guaranteeRequired
                    -
                    $currentlyGuaranteed
                ),
                2
            );

        $maximumGuarantors =
            (int) (
                DB::table(
                    'sacco_defaults'
                )
                    ->where(
                        'default_name',
                        'maximum_no_of_guarantors'
                    )
                    ->value(
                        'default_value'
                    )
                ?? 3
            );

        $maximumGuarantors =
            max(
                1,
                $maximumGuarantors
            );

        $activeGuarantorCount =
            $guarantors
                ->filter(
                    function ($row) {

                        return (
                            (float) (
                                $row
                                ->loan_guar_amount_guaranteed
                                ?? 0
                            )
                            -
                            (float) (
                                $row
                                ->loan_guar_amount_freed
                                ?? 0
                            )
                        )
                        >
                        $this->moneyTolerance;
                    }
                )
                ->count();

        $canAdd =
            true;

        $blockingMessage =
            null;

        if (
            $loanBalance
            <= $this->moneyTolerance
        ) {
            $canAdd = false;

            $blockingMessage =
                'This loan is fully paid.';
        }

        elseif (
            $guaranteePercent
            <= 0
        ) {
            $canAdd = false;

            $blockingMessage =
                'This loan product is configured not to require guarantors.';
        }

        elseif (
            $guaranteeRemaining
            <= $this->moneyTolerance
        ) {
            $canAdd = false;

            $blockingMessage =
                'This loan is already fully guaranteed.';
        }

        elseif (
            $activeGuarantorCount
            >= $maximumGuarantors
        ) {
            $canAdd = false;

            $blockingMessage =
                "Maximum guarantor count of {$maximumGuarantors} has already been reached.";
        }

        return [
            'loan_balance' =>
                $loanBalance,

            'guarantee_percent' =>
                round(
                    $guaranteePercent,
                    2
                ),

            'guarantee_required' =>
                $guaranteeRequired,

            'currently_guaranteed' =>
                $currentlyGuaranteed,

            'guarantee_remaining' =>
                $guaranteeRemaining,

            'current_guarantor_count' =>
                $activeGuarantorCount,

            'maximum_guarantors' =>
                $maximumGuarantors,

            'can_add_guarantor' =>
                $canAdd,

            'blocking_message' =>
                $blockingMessage,
        ];
    }
}