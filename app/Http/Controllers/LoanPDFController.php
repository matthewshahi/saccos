<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CheckUserRights;
use App\Services\MemberLoanLimitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoanPDFController extends Controller
{
    /**
     * Download the self-service loan application/appraisal PDF.
     *
     * The applicant may download their own application.
     * Users with downloadloanpdf may download any application and
     * receive the official Credit Committee audit detail.
     */
    public function downloadLoanForm($loanId)
    {
        $loanId = (int) $loanId;
        $userId = (int) Auth::id();

        $isOfficialCopy =
            CheckUserRights::userHasRight('downloadloanpdf');

        /*
        |--------------------------------------------------------------------------
        | Application, applicant and product
        |--------------------------------------------------------------------------
        */
        $loan = DB::table(
            'sacco_loan_batch_trans_members as trans'
        )
            ->join(
                'sacco_members as m',
                'trans.batch_trans_member_id',
                '=',
                'm.member_id'
            )
            ->join(
                'sacco_loan_types as t',
                'trans.batch_trans_loan_type',
                '=',
                't.loan_type_id'
            )
            ->leftJoin(
                'sacco_loan_category as c',
                'trans.batch_trans_loan_category',
                '=',
                'c.loan_category_id'
            )
            ->select([
                /*
                 * Application fields.
                 */
                'trans.*',

                /*
                 * Applicant fields.
                 */
                'm.member_id',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_kra_pin',
                'm.member_postal_address',
                'm.member_phone_no',
                'm.member_email',
                'm.member_gender',
                'm.member_dob',
                'm.member_date_joined',
                'm.member_total_share',
                'm.member_total_share_capital',
                'm.member_total_fosa',
                'm.member_total_loan',
                'm.member_tied_shares',
                'm.member_tied_shares_self',
                'm.member_active',
                'm.member_deleted',
                'm.bank_name',
                'm.bank_branch',
                'm.bank_account_number',

                /*
                 * Product/appraisal configuration.
                 */
                't.loan_type_id',
                't.loan_type_name',
                't.loan_type_code',
                't.loan_type_interest',
                't.loan_type_interest_type',
                't.loan_type_duration',
                't.loan_type_guaranteable_percent',
                't.loan_type_max_amount',
                't.loan_type_qualification_period',
                't.loan_type_max_qualification_period',
                't.loan_type_crb_required',
                't.loan_type_crb_charge',
                't.loan_type_crb_effect',
                't.loan_type_commission_required',
                't.loan_type_commission_type',
                't.loan_type_commission_value',
                't.loan_type_commission_effect',
                't.loan_type_insurable',
                't.loan_type_insurance_effect',
                't.loan_type_share_factor',
                't.loan_type_instant_qualification',
                't.loan_type_active',
                't.loan_type_deleted',

                'c.loan_category_name',

                DB::raw(
                    'trans.batch_trans_on as loan_created_at'
                ),
            ])
            ->where(
                'trans.batch_trans_id',
                $loanId
            )
            ->first();

        abort_if(
            !$loan,
            404,
            'Loan application not found.'
        );

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */
        if (
            (int) $loan->member_id !== $userId
            && !$isOfficialCopy
        ) {
            abort(
                403,
                'Unauthorized: This loan does not belong to you.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Current active guarantors
        |--------------------------------------------------------------------------
        */
        $guarantors = DB::table(
            'sacco_loan_batch_guarantors_members as g'
        )
            ->join(
                'sacco_members as m',
                'g.guarantors_guarantor_id',
                '=',
                'm.member_id'
            )
            ->select([
                'g.guarantors_id',
                'g.guarantors_guarantor_id',
                'g.guarantors_amount_guaranteed',
                'g.guarantors_description',
                'g.guarantors_approved',
                'g.guarantors_on',
                'g.guarantors_approved_on',

                'm.member_name',
                'm.member_sacco_id',
                'm.member_phone_no',
            ])
            ->where(
                'g.guarantors_loan_batch_trans_id',
                $loanId
            )
            ->whereRaw(
                "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
            )
            ->orderBy(
                'g.guarantors_id'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Actual application charges
        |--------------------------------------------------------------------------
        */
        $charges = DB::table(
            'sacco_loan_batch_trans_members_deductions'
        )
            ->where(
                'batch_trans_deduction_batch_trans_id',
                $loanId
            )
            ->whereRaw(
                "COALESCE(batch_trans_deduction_deleted, 'N') <> 'Y'"
            )
            ->orderBy(
                'batch_trans_deduction_id'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Automated appraisal
        |--------------------------------------------------------------------------
        */
        $appraisal = $this->buildAppraisal(
            $loan,
            $guarantors,
            $charges
        );

        /*
        |--------------------------------------------------------------------------
        | Credit Committee result
        |--------------------------------------------------------------------------
        */
        $committee = $this->buildCommitteeSnapshot(
            $loan->batch_trans_credit_committee_decisions
                ?? null,
            (bool) $appraisal['guarantee']['is_sufficient']
        );

        /*
        |--------------------------------------------------------------------------
        | Overall system conclusion
        |--------------------------------------------------------------------------
        |
        | An N vote does not itself reject/delete the application.
        | It means Credit Committee approval has not been obtained.
        |--------------------------------------------------------------------------
        */
        if (!$appraisal['base_eligible']) {
            $systemOutcome =
                'REQUIRES MANUAL REVIEW';

            $systemOutcomeClass =
                'danger';
        } elseif (
            !$appraisal['guarantee']['is_sufficient']
        ) {
            $systemOutcome =
                'PENDING GUARANTOR APPROVAL';

            $systemOutcomeClass =
                'warning';
        } elseif (
            $committee['status'] === 'DECLINED'
        ) {
            $systemOutcome =
                'CREDIT COMMITTEE NOT APPROVED';

            $systemOutcomeClass =
                'danger';
        } elseif (
            $committee['status'] === 'APPROVED'
        ) {
            $systemOutcome =
                'CREDIT COMMITTEE APPROVED';

            $systemOutcomeClass =
                'success';
        } elseif (
            $committee['status'] === 'NOT_REQUIRED'
        ) {
            $systemOutcome =
                'APPRAISAL PASSED - COMMITTEE NOT REQUIRED';

            $systemOutcomeClass =
                'success';
        } else {
            $systemOutcome =
                'ELIGIBLE FOR CREDIT COMMITTEE REVIEW';

            $systemOutcomeClass =
                'info';
        }

        $appraisal['system_outcome'] =
            $systemOutcome;

        $appraisal['system_outcome_class'] =
            $systemOutcomeClass;

        /*
        |--------------------------------------------------------------------------
        | SACCO identity
        |--------------------------------------------------------------------------
        */
        $companyName = DB::table('sacco_defaults')
            ->where(
                'default_name',
                'company_name'
            )
            ->value('default_value')
            ?? 'SACCO Ltd';

        $data = [
            'loan' =>
                $loan,

            'guarantors' =>
                $guarantors,

            'charges' =>
                $charges,

            'appraisal' =>
                $appraisal,

            'committee' =>
                $committee,

            'companyName' =>
                $companyName,

            'generatedAt' =>
                now(),

            /*
             * Controls whether individual committee decisions
             * are rendered.
             */
            'isOfficialCopy' =>
                (bool) $isOfficialCopy,
        ];

        $pdf = Pdf::loadView(
            'pdf.loan_form',
            $data
        )
            ->setPaper(
                'A4',
                'portrait'
            );

        return $pdf->download(
            'LoanAppraisal_'
                . $loan->batch_trans_id
                . '.pdf'
        );
    }

    /**
     * Build the automated credit appraisal.
     */
    private function buildAppraisal(
        object $loan,
        $guarantors,
        $charges
    ): array {
        $requestedAmount = round(
            (float) (
                $loan->batch_trans_loan_amount ?? 0
            ),
            2
        );

        $duration = (int) (
            $loan->batch_trans_loan_duration ?? 0
        );

        $productMax = round(
            (float) (
                $loan->loan_type_max_amount ?? 0
            ),
            2
        );

        $productDuration = (int) (
            $loan->loan_type_duration ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Member status
        |--------------------------------------------------------------------------
        */
        $memberActive =
            strtoupper(
                trim(
                    (string) (
                        $loan->member_active ?? 'N'
                    )
                )
            ) === 'Y'
            &&
            strtoupper(
                trim(
                    (string) (
                        $loan->member_deleted ?? 'N'
                    )
                )
            ) !== 'Y';

        /*
        |--------------------------------------------------------------------------
        | Product status
        |--------------------------------------------------------------------------
        */
        $productActive =
            (int) (
                $loan->loan_type_active ?? 1
            ) === 1
            &&
            strtoupper(
                trim(
                    (string) (
                        $loan->loan_type_deleted ?? 'N'
                    )
                )
            ) !== 'Y';

        /*
        |--------------------------------------------------------------------------
        | Product amount rule
        |--------------------------------------------------------------------------
        */
        $amountPass =
            $requestedAmount > 0
            &&
            (
                $productMax <= 0
                ||
                $requestedAmount
                    <= $productMax + 0.01
            );

        /*
        |--------------------------------------------------------------------------
        | Duration rule
        |--------------------------------------------------------------------------
        */
        $durationPass =
            $duration > 0
            &&
            (
                $productDuration <= 0
                ||
                $duration <= $productDuration
            );

        /*
        |--------------------------------------------------------------------------
        | Membership qualification period
        |--------------------------------------------------------------------------
        */
        $instantQualification =
            (int) (
                $loan->loan_type_instant_qualification
                ?? 0
            ) === 1;

        $membershipRequiredMonths = max(
            0,
            (int) (
                $loan->loan_type_qualification_period
                ?? 0
            )
        );

        $membershipMonths = null;
        $membershipPass = true;

        $membershipMessage =
            'Membership qualification satisfied.';

        if ($instantQualification) {
            $membershipMessage =
                'Membership period bypassed by instant qualification setting.';
        } elseif (
            empty($loan->member_date_joined)
        ) {
            $membershipPass = false;

            $membershipMessage =
                'Member joining date is missing.';
        } else {
            try {
                $joined = Carbon::parse(
                    $loan->member_date_joined
                );

                if ($joined->isFuture()) {
                    $membershipPass = false;

                    $membershipMessage =
                        'Member joining date is in the future.';
                } else {
                    $membershipMonths = (int)
                        $joined->diffInMonths(now());

                    $membershipPass = !(
                        $membershipRequiredMonths > 0
                        &&
                        $joined->greaterThan(
                            now()
                                ->copy()
                                ->subMonths(
                                    $membershipRequiredMonths
                                )
                        )
                    );

                    $membershipMessage =
                        $membershipPass
                        ? 'Required SACCO membership period satisfied.'
                        : 'Requires at least '
                            . $membershipRequiredMonths
                            . ' months in the SACCO.';
                }
            } catch (Throwable $e) {
                $membershipPass = false;

                $membershipMessage =
                    'Member joining date is invalid.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Authoritative member/product limit validation
        |--------------------------------------------------------------------------
        */
        try {
            $memberLimitValidation =
                app(MemberLoanLimitService::class)
                    ->validateRequestedAmount(
                        (int) $loan->member_id,
                        $loan,
                        $requestedAmount
                    );

            $memberLimitPass =
                (bool) (
                    $memberLimitValidation['is_valid']
                    ?? false
                );

            $memberLimitMessage =
                (string) (
                    $memberLimitValidation['message']
                    ??
                    (
                        $memberLimitPass
                        ? 'Member-specific loan limit satisfied.'
                        : 'Member-specific loan limit not satisfied.'
                    )
                );
        } catch (Throwable $e) {
            $memberLimitPass = false;

            $memberLimitMessage =
                'Member-specific loan limit could not be evaluated.';
        }

        /*
        |--------------------------------------------------------------------------
        | Configured individual ceiling
        |--------------------------------------------------------------------------
        */
        $individualLimit = DB::table(
            'sacco_member_loan_limits'
        )
            ->where(
                'member_loan_limit_member_id',
                $loan->member_id
            )
            ->where(
                'member_loan_limit_loan_type_id',
                $loan->loan_type_id
            )
            ->where(
                'member_loan_limit_active',
                1
            )
            ->whereRaw(
                "COALESCE(member_loan_limit_deleted, 'N') <> 'Y'"
            )
            ->value(
                'member_loan_limit_amount'
            );

        $individualLimit =
            $individualLimit !== null
            ? round(
                (float) $individualLimit,
                2
            )
            : null;

        /*
        |--------------------------------------------------------------------------
        | Effective configured ceiling
        |--------------------------------------------------------------------------
        */
        $ceilings = [];

        if ($productMax > 0) {
            $ceilings[] = $productMax;
        }

        if (
            $individualLimit !== null
            && $individualLimit > 0
        ) {
            $ceilings[] = $individualLimit;
        }

        $effectiveConfiguredCeiling =
            !empty($ceilings)
            ? min($ceilings)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Shares and existing exposure
        |--------------------------------------------------------------------------
        |
        | Use member_total_share for the product share-factor display.
        | The MemberLoanLimitService remains authoritative for approval.
        |--------------------------------------------------------------------------
        */
        $memberShares = round(
            (float) (
                $loan->member_total_share ?? 0
            ),
            2
        );

        $shareCapital = round(
            (float) (
                $loan->member_total_share_capital ?? 0
            ),
            2
        );

        $shareFactor = max(
            0,
            (float) (
                $loan->loan_type_share_factor ?? 0
            )
        );

        /*
         * member_total_loan is the platform-maintained
         * current member exposure.
         */
        $outstandingLoans = round(
            (float) (
                $loan->member_total_loan ?? 0
            ),
            2
        );

        $indicativeGrossShareCapacity = round(
            $memberShares * $shareFactor,
            2
        );

        $indicativeAvailableShareCapacity = round(
            max(
                0,
                $indicativeGrossShareCapacity
                    - $outstandingLoans
            ),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Guarantees
        |--------------------------------------------------------------------------
        */
        $requiredGuarantee = round(
            (
                (float) (
                    $loan->loan_type_guaranteable_percent
                    ?? 0
                )
                / 100
            )
            * $requestedAmount,
            2
        );

        $submittedGuarantee = round(
            (float) $guarantors->sum(
                function ($row) {
                    return (float) (
                        $row->guarantors_amount_guaranteed
                        ?? 0
                    );
                }
            ),
            2
        );

        $approvedGuarantee = round(
            (float) $guarantors
                ->filter(
                    function ($row) {
                        return strtoupper(
                            trim(
                                (string) (
                                    $row->guarantors_approved
                                    ?? 'N'
                                )
                            )
                        ) === 'Y';
                    }
                )
                ->sum(
                    function ($row) {
                        return (float) (
                            $row->guarantors_amount_guaranteed
                            ?? 0
                        );
                    }
                ),
            2
        );

        /*
         * Match the existing guarantee sufficiency tolerance.
         */
        $guaranteePass =
            abs(
                $approvedGuarantee
                - $requiredGuarantee
            ) <= 1;

        /*
        |--------------------------------------------------------------------------
        | Application deduction rows
        |--------------------------------------------------------------------------
        */
        $chargeAdd = 0.00;
        $chargeDeduct = 0.00;

        foreach ($charges as $charge) {
            $amount = round(
                (float) (
                    $charge
                        ->batch_trans_deduction_amount
                    ?? 0
                ),
                2
            );

            $effect = strtoupper(
                trim(
                    (string) (
                        $charge
                            ->batch_trans_deduction_effect
                        ?? ''
                    )
                )
            );

            if ($effect === 'ADD_TO_LOAN') {
                $chargeAdd += $amount;
            } elseif (
                $effect ===
                'DEDUCT_FROM_DISBURSEMENT'
            ) {
                $chargeDeduct += $amount;
            }
        }

        $chargeAdd = round(
            $chargeAdd,
            2
        );

        $chargeDeduct = round(
            $chargeDeduct,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Commission and insurance
        |--------------------------------------------------------------------------
        */
        $insurance = round(
            (float) (
                $loan->batch_trans_insurance ?? 0
            ),
            2
        );

        $commission = round(
            (float) (
                $loan->batch_trans_commission ?? 0
            ),
            2
        );

        $commissionEffect =
            $this->normalizeEffect(
                $loan->loan_type_commission_effect
                    ?? 'ADD_TO_LOAN'
            );

        $insuranceEffect =
            $this->normalizeEffect(
                $loan->loan_type_insurance_effect
                    ?? 'ADD_TO_LOAN'
            );

        $commissionAdded =
            $commissionEffect === 'ADD_TO_LOAN'
            ? $commission
            : 0.00;

        $commissionDeducted =
            $commissionEffect ===
                'DEDUCT_FROM_DISBURSEMENT'
            ? $commission
            : 0.00;

        $insuranceAdded =
            $insuranceEffect === 'ADD_TO_LOAN'
            ? $insurance
            : 0.00;

        $insuranceDeducted =
            $insuranceEffect ===
                'DEDUCT_FROM_DISBURSEMENT'
            ? $insurance
            : 0.00;

        /*
        |--------------------------------------------------------------------------
        | Financial result
        |--------------------------------------------------------------------------
        |
        | Deliberately mirrors the existing final approval calculation.
        |--------------------------------------------------------------------------
        */
        $loanCommitment = round(
            $requestedAmount
                + $chargeAdd
                + $commissionAdded
                + $insuranceAdded,
            2
        );

        $netDisbursement = round(
            $requestedAmount
                - $commissionDeducted
                - $insuranceDeducted
                - $chargeDeduct,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Base appraisal result
        |--------------------------------------------------------------------------
        */
        $baseEligible =
            $memberActive
            && $productActive
            && $amountPass
            && $durationPass
            && $membershipPass
            && $memberLimitPass
            && $netDisbursement > 0;

        /*
        |--------------------------------------------------------------------------
        | Application record status
        |--------------------------------------------------------------------------
        */
        $applicationStatus = 'Pending';

        if (
            strtoupper(
                (string) (
                    $loan->batch_trans_deleted ?? 'N'
                )
            ) === 'Y'
        ) {
            $applicationStatus =
                'Cancelled / Rejected';
        } elseif (
            strtoupper(
                (string) (
                    $loan->batch_trans_updated ?? 'N'
                )
            ) === 'Y'
        ) {
            $applicationStatus =
                'Processed';
        }

        return [
            'base_eligible' =>
                $baseEligible,

            'application_status' =>
                $applicationStatus,

            /*
             * Individual automated checks.
             */
            'rules' => [
                'member_status' => [
                    'pass' =>
                        $memberActive,

                    'label' =>
                        'Member Account Status',

                    'detail' =>
                        $memberActive
                        ? 'Member is active.'
                        : 'Member account is inactive or deleted.',
                ],

                'product_status' => [
                    'pass' =>
                        $productActive,

                    'label' =>
                        'Loan Product Status',

                    'detail' =>
                        $productActive
                        ? 'Loan product is active.'
                        : 'Loan product is inactive or deleted.',
                ],

                'product_amount' => [
                    'pass' =>
                        $amountPass,

                    'label' =>
                        'Product Amount Limit',

                    'detail' =>
                        $productMax > 0
                        ? 'Requested KES '
                            . number_format(
                                $requestedAmount,
                                2
                            )
                            . ' against product maximum KES '
                            . number_format(
                                $productMax,
                                2
                            )
                            . '.'
                        : 'No positive product maximum configured.',
                ],

                'member_limit' => [
                    'pass' =>
                        $memberLimitPass,

                    'label' =>
                        'Member-Specific Limit',

                    'detail' =>
                        $memberLimitMessage,
                ],

                'duration' => [
                    'pass' =>
                        $durationPass,

                    'label' =>
                        'Repayment Period',

                    'detail' =>
                        $duration
                        . ' month(s) requested; '
                        . 'product maximum '
                        . $productDuration
                        . ' month(s).',
                ],

                'membership' => [
                    'pass' =>
                        $membershipPass,

                    'label' =>
                        'Membership Qualification',

                    'detail' =>
                        $membershipMessage,
                ],

                'net_disbursement' => [
                    'pass' =>
                        $netDisbursement > 0,

                    'label' =>
                        'Net Disbursement',

                    'detail' =>
                        $netDisbursement > 0
                        ? 'Estimated net disbursement is positive.'
                        : 'Estimated net disbursement is zero or negative.',
                ],
            ],

            'membership' => [
                'instant' =>
                    $instantQualification,

                'required_months' =>
                    $membershipRequiredMonths,

                'actual_months' =>
                    $membershipMonths,
            ],

            'capacity' => [
                'member_shares' =>
                    $memberShares,

                'share_capital' =>
                    $shareCapital,

                'share_factor' =>
                    $shareFactor,

                'indicative_gross_share_capacity' =>
                    $indicativeGrossShareCapacity,

                'outstanding_loans' =>
                    $outstandingLoans,

                'indicative_available_share_capacity' =>
                    $indicativeAvailableShareCapacity,

                'product_maximum' =>
                    $productMax,

                'individual_limit' =>
                    $individualLimit,

                'effective_configured_ceiling' =>
                    $effectiveConfiguredCeiling,
            ],

            'guarantee' => [
                'percentage' =>
                    (float) (
                        $loan
                            ->loan_type_guaranteable_percent
                        ?? 0
                    ),

                'required' =>
                    $requiredGuarantee,

                'submitted' =>
                    $submittedGuarantee,

                'approved' =>
                    $approvedGuarantee,

                'difference' =>
                    round(
                        max(
                            0,
                            $requiredGuarantee
                                - $approvedGuarantee
                        ),
                        2
                    ),

                'is_sufficient' =>
                    $guaranteePass,

                'status' =>
                    $guaranteePass
                    ? 'SATISFIED'
                    : 'PENDING',
            ],

            'financials' => [
                'requested_amount' =>
                    $requestedAmount,

                'expected_interest' =>
                    round(
                        (float) (
                            $loan
                                ->batch_trans_expected_interest
                            ?? 0
                        ),
                        2
                    ),

                'monthly_payment' =>
                    round(
                        (float) (
                            $loan
                                ->batch_trans_monthly_payment
                            ?? 0
                        ),
                        2
                    ),

                'monthly_principal' =>
                    round(
                        (float) (
                            $loan
                                ->batch_trans_monthly_payment_principal
                            ?? 0
                        ),
                        2
                    ),

                'insurance' =>
                    $insurance,

                'insurance_effect' =>
                    $insuranceEffect,

                'commission' =>
                    $commission,

                'commission_effect' =>
                    $commissionEffect,

                'other_add_to_loan' =>
                    $chargeAdd,

                'other_deduct_from_disbursement' =>
                    $chargeDeduct,

                'loan_commitment' =>
                    $loanCommitment,

                'net_disbursement' =>
                    $netDisbursement,

                /*
                 * Display separately.
                 * Do not subtract it again from the current
                 * final-approval net-disbursement calculation.
                 */
                'top_up_outstanding' =>
                    round(
                        (float) (
                            $loan
                                ->batch_trans_loan_to_top_up_amount
                            ?? 0
                        ),
                        2
                    ),
            ],

            'crb' => [
                'required' =>
                    strtoupper(
                        trim(
                            (string) (
                                $loan
                                    ->loan_type_crb_required
                                ?? 'N'
                            )
                        )
                    ) === 'Y',

                'charge' =>
                    round(
                        (float) (
                            $loan->loan_type_crb_charge
                            ?? 0
                        ),
                        2
                    ),

                'effect' =>
                    $loan->loan_type_crb_effect
                        ?? null,
            ],
        ];
    }

    /**
     * Decode and summarize Credit Committee decisions.
     *
     * Supports:
     * 1. Current wrapped JSON.
     * 2. Older member-ID keyed JSON.
     * 3. Legacy list rows containing member_id.
     */
    private function buildCommitteeSnapshot(
        $json,
        bool $guaranteeReady
    ): array {
        $decoded = [];

        if (
            $json !== null
            && trim((string) $json) !== ''
        ) {
            $candidate = json_decode(
                (string) $json,
                true
            );

            if (is_array($candidate)) {
                $decoded = $candidate;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Defaults
        |--------------------------------------------------------------------------
        */
        $defaultCategory = DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                'CREDIT_COMMITTEE'
            )
            ->value(
                'default_value'
            )
            ?? 'CREDIT_COMMITTEE';

        $defaultRequiredRaw = DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                'CREDIT_COMMITTEE_REQUIRED_APPROVALS'
            )
            ->value(
                'default_value'
            );

        $defaultRequired =
            is_numeric($defaultRequiredRaw)
            ? max(
                0,
                (int) $defaultRequiredRaw
            )
            : 1;

        /*
        |--------------------------------------------------------------------------
        | Prefer snapshot config when already present
        |--------------------------------------------------------------------------
        */
        $category =
            isset($decoded['category'])
            &&
            trim(
                (string) $decoded['category']
            ) !== ''
            ? trim(
                (string) $decoded['category']
            )
            : $defaultCategory;

        $required =
            array_key_exists(
                'required_approvals',
                $decoded
            )
            &&
            is_numeric(
                $decoded['required_approvals']
            )
            ? max(
                0,
                (int) $decoded[
                    'required_approvals'
                ]
            )
            : $defaultRequired;

        /*
        |--------------------------------------------------------------------------
        | Extract decision collection
        |--------------------------------------------------------------------------
        */
        $source =
            isset($decoded['decisions'])
            &&
            is_array($decoded['decisions'])
            ? $decoded['decisions']
            : $decoded;

        unset(
            $source['category'],
            $source['required_approvals'],
            $source['decisions']
        );

        $isLegacyList =
            array_is_list($source);

        $decisions = [];

        foreach ($source as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            /*
             * Current keyed structure uses member ID as key.
             * Legacy list structure must contain member_id.
             */
            if (
                isset($row['member_id'])
                && is_numeric($row['member_id'])
            ) {
                $memberId =
                    (int) $row['member_id'];
            } elseif (
                !$isLegacyList
                && is_numeric($key)
            ) {
                $memberId =
                    (int) $key;
            } else {
                $memberId = 0;
            }

            $decision = strtoupper(
                trim(
                    (string) (
                        $row['decision'] ?? ''
                    )
                )
            );

            if (
                $memberId <= 0
                ||
                !in_array(
                    $decision,
                    ['Y', 'N'],
                    true
                )
            ) {
                continue;
            }

            $decisions[$memberId] = [
                'member_id' =>
                    $memberId,

                'decision' =>
                    $decision,

                'classification_id' =>
                    isset(
                        $row['classification_id']
                    )
                    ? (int) $row[
                        'classification_id'
                    ]
                    : null,

                'classification_code' =>
                    $row['classification_code']
                        ?? null,

                'classification_name' =>
                    $row['classification_name']
                        ?? null,

                'decided_at' =>
                    $row['decided_at']
                        ?? $row['decision_date']
                        ?? null,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve names without storing them in the JSON
        |--------------------------------------------------------------------------
        */
        $memberIds =
            array_keys($decisions);

        $memberNames =
            empty($memberIds)
            ? collect()
            : DB::table(
                'sacco_members'
            )
                ->whereIn(
                    'member_id',
                    $memberIds
                )
                ->pluck(
                    'member_name',
                    'member_id'
                );

        $yesCount = 0;
        $noCount = 0;

        $latestDecisionAt = null;

        foreach (
            $decisions as
            $memberId => &$decisionRow
        ) {
            if (
                $decisionRow['decision'] === 'Y'
            ) {
                $yesCount++;
            } else {
                $noCount++;
            }

            $decisionRow['member_name'] =
                $memberNames[$memberId]
                ?? (
                    'Member #'
                    . $memberId
                );

            if (
                !empty(
                    $decisionRow['decided_at']
                )
            ) {
                try {
                    $decisionDate =
                        Carbon::parse(
                            $decisionRow[
                                'decided_at'
                            ]
                        );

                    if (
                        $latestDecisionAt === null
                        ||
                        $decisionDate
                            ->greaterThan(
                                $latestDecisionAt
                            )
                    ) {
                        $latestDecisionAt =
                            $decisionDate;
                    }
                } catch (Throwable $e) {
                    /*
                     * Malformed legacy timestamps are ignored.
                     */
                }
            }
        }

        unset($decisionRow);

        /*
        |--------------------------------------------------------------------------
        | Committee outcome
        |--------------------------------------------------------------------------
        |
        | N blocks approval but does NOT delete/reject the loan application.
        |--------------------------------------------------------------------------
        */
        if ($required === 0) {
            $status =
                'NOT_REQUIRED';
        } elseif ($noCount > 0) {
            $status =
                'DECLINED';
        } elseif (
            $yesCount >= $required
        ) {
            $status =
                'APPROVED';
        } else {
            $status =
                'PENDING';
        }

        if ($status === 'APPROVED') {
            $displayStatus =
                'Approved';
        } elseif (
            $status === 'DECLINED'
        ) {
            $displayStatus =
                'Not Approved';
        } elseif (
            $status === 'NOT_REQUIRED'
        ) {
            $displayStatus =
                'Not Required';
        } else {
            $displayStatus =
                $guaranteeReady
                ? 'Pending Credit Committee Review'
                : 'Awaiting Guarantor Approval';
        }

        return [
            'category' =>
                $category,

            'required_approvals' =>
                $required,

            'yes_count' =>
                $yesCount,

            'no_count' =>
                $noCount,

            'status' =>
                $status,

            'display_status' =>
                $displayStatus,

            'overall_decided_at' =>
                in_array(
                    $status,
                    [
                        'APPROVED',
                        'DECLINED',
                    ],
                    true
                )
                ? $latestDecisionAt
                : null,

            'decisions' =>
                collect(
                    array_values(
                        $decisions
                    )
                ),
        ];
    }

    /**
     * Normalize charge treatment.
     */
    private function normalizeEffect(
        $effect
    ): string {
        $effect = strtoupper(
            trim(
                (string) $effect
            )
        );

        return in_array(
            $effect,
            [
                'ADD_TO_LOAN',
                'DEDUCT_FROM_DISBURSEMENT',
            ],
            true
        )
            ? $effect
            : 'ADD_TO_LOAN';
    }
}