<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MemberLoanLimitService
{
    /**
     * Resolve the maximum loan amount applicable to a member
     * for a particular loan type.
     */
    public function resolve(int $memberId, object $loanType): array
    {
        $loanTypeId = (int) ($loanType->loan_type_id ?? 0);

        $loanTypeName = trim(
            (string) ($loanType->loan_type_name ?? 'selected loan type')
        );

        $productMaximum = round(
            max(
                0,
                (float) ($loanType->loan_type_max_amount ?? 0)
            ),
            2
        );

        $individualLimit = null;

        if ($memberId > 0 && $loanTypeId > 0) {
            $individualLimit = DB::table('sacco_member_loan_limits')
                ->where(
                    'member_loan_limit_member_id',
                    $memberId
                )
                ->where(
                    'member_loan_limit_loan_type_id',
                    $loanTypeId
                )
                ->where(
                    'member_loan_limit_active',
                    1
                )
                ->whereRaw(
                    "COALESCE(member_loan_limit_deleted, 'N') <> 'Y'"
                )
                ->value('member_loan_limit_amount');
        }

        $hasIndividualLimit = $individualLimit !== null;

        $individualLimit = $hasIndividualLimit
            ? round(max(0, (float) $individualLimit), 2)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Effective maximum
        |--------------------------------------------------------------------------
        | The individual member limit cannot override the loan product maximum.
        |--------------------------------------------------------------------------
        */
        $effectiveMaximum = $hasIndividualLimit
            ? min($productMaximum, $individualLimit)
            : $productMaximum;

        $source = (
            $hasIndividualLimit
            && $individualLimit <= $productMaximum
        )
            ? 'individual_member_limit'
            : 'loan_type_maximum';

        return [
            'member_id' => $memberId,
            'loan_type_id' => $loanTypeId,
            'loan_type_name' => $loanTypeName,
            'loan_type_maximum' => $productMaximum,
            'has_individual_limit' => $hasIndividualLimit,
            'individual_limit' => $individualLimit,
            'effective_maximum' => round(
                $effectiveMaximum,
                2
            ),
            'source' => $source,
        ];
    }

    /**
     * Validate a requested loan amount against the applicable
     * product maximum and individual member limit.
     */
    public function validateRequestedAmount(
        int $memberId,
        object $loanType,
        float $requestedAmount
    ): array {
        $requestedAmount = round(
            $requestedAmount,
            2
        );

        $limit = $this->resolve(
            $memberId,
            $loanType
        );

        /*
        |--------------------------------------------------------------------------
        | Validate member reference
        |--------------------------------------------------------------------------
        */
        if ($memberId <= 0) {
            return array_merge(
                $limit,
                [
                    'requested_amount' => $requestedAmount,
                    'is_valid' => false,
                    'message' => 'Invalid member reference.',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate loan type reference
        |--------------------------------------------------------------------------
        */
        if ($limit['loan_type_id'] <= 0) {
            return array_merge(
                $limit,
                [
                    'requested_amount' => $requestedAmount,
                    'is_valid' => false,
                    'message' => 'Invalid loan type reference.',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate requested amount
        |--------------------------------------------------------------------------
        */
        if ($requestedAmount < 1) {
            return array_merge(
                $limit,
                [
                    'requested_amount' => $requestedAmount,
                    'is_valid' => false,
                    'message' => 'Loan amount must be greater than zero.',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate configured maximum
        |--------------------------------------------------------------------------
        */
        if ($limit['effective_maximum'] <= 0) {
            return array_merge(
                $limit,
                [
                    'requested_amount' => $requestedAmount,
                    'is_valid' => false,
                    'message' =>
                        'The selected loan type does not have a valid maximum amount configured.',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce effective maximum
        |--------------------------------------------------------------------------
        */
        if (
            $requestedAmount
            > $limit['effective_maximum']
        ) {
            if (
                $limit['source']
                === 'individual_member_limit'
            ) {
                $message =
                    "Loan amount exceeds this member's individual limit of KES "
                    . number_format(
                        $limit['effective_maximum'],
                        2
                    )
                    . ' for '
                    . $limit['loan_type_name']
                    . '.';
            } else {
                $message =
                    'Loan amount exceeds the maximum allowed amount of KES '
                    . number_format(
                        $limit['effective_maximum'],
                        2
                    )
                    . ' for '
                    . $limit['loan_type_name']
                    . '.';
            }

            return array_merge(
                $limit,
                [
                    'requested_amount' => $requestedAmount,
                    'is_valid' => false,
                    'message' => $message,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validation passed
        |--------------------------------------------------------------------------
        */
        return array_merge(
            $limit,
            [
                'requested_amount' => $requestedAmount,
                'is_valid' => true,
                'message' => null,
            ]
        );
    }
}