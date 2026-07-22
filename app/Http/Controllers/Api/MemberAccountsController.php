<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MemberAccountsController extends Controller
{
    private const TRANSACTION_LIMIT = 15;

    /**
     * GET /api/auth/accounts
     *
     * Returns the authenticated member's complete savings position:
     * - deposits / shares
     * - share capital
     * - other savings / FOSA, categorised by FOSA type
     * - special savings, including account-level balances
     *
     * Every section returns its total-to-date and only the latest 15
     * non-deleted, non-reversed transactions where applicable.
     */
    public function index(Request $request): JsonResponse
    {
        $authenticatedMember = $request->user();

        if (!$authenticatedMember || !isset($authenticatedMember->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $authenticatedMember->member_id;

        $member = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where(function ($query) {
                $query->whereNull('member_deleted')
                    ->orWhere('member_deleted', '<>', 'Y');
            })
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member record not found.',
            ], 404);
        }

        if (strtoupper((string) ($member->member_active ?? 'N')) !== 'Y') {
            return response()->json([
                'message' => 'Member account is inactive.',
            ], 403);
        }

        $deposits = $this->buildDeposits($memberId, $member);
        $capital = $this->buildCapital($memberId, $member);
        $otherSavings = $this->buildOtherSavings($memberId);
        $specialSavings = $this->buildSpecialSavings($memberId);

        $grandTotal = $this->money(
            $deposits['total']
            + $capital['total']
            + $otherSavings['total']
            + $specialSavings['total']
        );

        return response()->json([
            'member' => [
                'id' => $memberId,
                'name' => (string) ($member->member_name ?? ''),
                'member_number' => (string) ($member->member_sacco_id ?? ''),
                'status' => 'Active',
            ],
            'currency' => 'KES',
            'as_of' => now()->toIso8601String(),
            'transaction_limit' => self::TRANSACTION_LIMIT,
            'summary' => [
                'deposits' => $deposits['total'],
                'capital' => $capital['total'],
                'other_savings' => $otherSavings['total'],
                'special_savings' => $specialSavings['total'],
                'total_savings' => $grandTotal,
            ],
            'deposits' => $deposits,
            'capital' => $capital,
            'other_savings' => $otherSavings,
            'special_savings' => $specialSavings,
        ]);
    }

    private function buildDeposits(int $memberId, object $member): array
    {
        $transactions = DB::table('sacco_shares')
            ->where('share_member_id', $memberId)
            ->orderByRaw('COALESCE(share_date_paid, share_transdate) DESC')
            ->orderByDesc('share_period')
            ->orderByDesc('share_id')
            ->limit(self::TRANSACTION_LIMIT)
            ->get([
                'share_id',
                'share_amount_paying',
                'share_paid_by',
                'share_period',
                'share_description',
                'share_doc_no',
                'share_date_paid',
                'share_transdate',
            ])
            ->map(function ($row) {
                return $this->mapStandardTransaction(
                    id: (int) $row->share_id,
                    amount: (float) ($row->share_amount_paying ?? 0),
                    period: $row->share_period,
                    paidDate: $row->share_date_paid,
                    fallbackDate: $row->share_transdate,
                    description: $row->share_description,
                    fallbackDescription: 'Deposit transaction',
                    documentNumber: $row->share_doc_no,
                    paidBy: $row->share_paid_by
                );
            })
            ->values();

        return [
            'key' => 'deposits',
            'title' => 'Deposits',
            'total' => $this->money((float) ($member->member_total_share ?? 0)),
            'transaction_count' => DB::table('sacco_shares')
                ->where('share_member_id', $memberId)
                ->count(),
            'transactions' => $transactions,
        ];
    }

    private function buildCapital(int $memberId, object $member): array
    {
        $transactions = DB::table('sacco_capital_shares')
            ->where('share_capitalmember_id', $memberId)
            ->orderByRaw('COALESCE(share_capitaldate_paid, share_capitaltransdate) DESC')
            ->orderByDesc('share_capitalperiod')
            ->orderByDesc('share_capitalid')
            ->limit(self::TRANSACTION_LIMIT)
            ->get([
                'share_capitalid',
                'share_capitalamount_paying',
                'share_capitalpaid_by',
                'share_capitalperiod',
                'share_capitaldescription',
                'share_capitaldoc_no',
                'share_capitaldate_paid',
                'share_capitaltransdate',
            ])
            ->map(function ($row) {
                return $this->mapStandardTransaction(
                    id: (int) $row->share_capitalid,
                    amount: (float) ($row->share_capitalamount_paying ?? 0),
                    period: $row->share_capitalperiod,
                    paidDate: $row->share_capitaldate_paid,
                    fallbackDate: $row->share_capitaltransdate,
                    description: $row->share_capitaldescription,
                    fallbackDescription: 'Capital transaction',
                    documentNumber: $row->share_capitaldoc_no,
                    paidBy: $row->share_capitalpaid_by
                );
            })
            ->values();

        return [
            'key' => 'capital',
            'title' => 'Capital',
            'total' => $this->money((float) ($member->member_total_share_capital ?? 0)),
            'transaction_count' => DB::table('sacco_capital_shares')
                ->where('share_capitalmember_id', $memberId)
                ->count(),
            'transactions' => $transactions,
        ];
    }

    private function buildOtherSavings(int $memberId): array
    {
        $specialProductTokens = $this->specialSavingProductTokens();

        $baseQuery = $this->otherSavingsBaseQuery(
            $memberId,
            $specialProductTokens
        );

        $categoryRows = (clone $baseQuery)
            ->selectRaw("\n                COALESCE(ft.type_id, 0) AS type_id,\n                COALESCE(NULLIF(TRIM(ft.type_name), ''), 'UNSPECIFIED') AS type_name,\n                COALESCE(NULLIF(TRIM(ft.type_prefix), ''), '') AS type_prefix,\n                SUM(COALESCE(f.fosa_amount_paying, 0)) AS total,\n                COUNT(*) AS transaction_count\n            ")
            ->groupBy('ft.type_id', 'ft.type_name', 'ft.type_prefix')
            ->orderBy('type_name')
            ->get();

        $categories = $categoryRows
            ->map(function ($row) {
                return [
                    'type_id' => (int) $row->type_id,
                    'name' => (string) $row->type_name,
                    'prefix' => (string) $row->type_prefix,
                    'total' => $this->money((float) $row->total),
                    'transaction_count' => (int) $row->transaction_count,
                ];
            })
            ->values();

        $total = $this->money(
            $categories->sum(fn(array $category) => (float) $category['total'])
        );

        $transactions = (clone $baseQuery)
            ->select([
                'f.fosa_id',
                'f.fosa_action',
                'f.fosa_ref_id',
                'f.fosa_amount_paying',
                'f.fosa_paid_by',
                'f.fosa_period',
                'f.fosa_description',
                'f.fosa_doc_no',
                'f.fosa_date_paid',
                'f.fosa_transdate',
                'ft.type_id',
                'ft.type_name',
                'ft.type_prefix',
            ])
            ->orderByRaw('COALESCE(f.fosa_date_paid, f.fosa_transdate) DESC')
            ->orderByDesc('f.fosa_period')
            ->orderByDesc('f.fosa_id')
            ->limit(self::TRANSACTION_LIMIT)
            ->get()
            ->map(function ($row) {
                $transaction = $this->mapStandardTransaction(
                    id: (int) $row->fosa_id,
                    amount: (float) ($row->fosa_amount_paying ?? 0),
                    period: $row->fosa_period,
                    paidDate: $row->fosa_date_paid,
                    fallbackDate: $row->fosa_transdate,
                    description: $row->fosa_description,
                    fallbackDescription: 'Other savings transaction',
                    documentNumber: $row->fosa_doc_no,
                    paidBy: $row->fosa_paid_by
                );

                $transaction['action'] = strtoupper((string) ($row->fosa_action ?? 'NORMAL'));
                $transaction['reference_transaction_id'] = $row->fosa_ref_id !== null
                    ? (int) $row->fosa_ref_id
                    : null;
                $transaction['category'] = [
                    'type_id' => (int) ($row->type_id ?? 0),
                    'name' => trim((string) ($row->type_name ?? '')) !== ''
                        ? (string) $row->type_name
                        : 'UNSPECIFIED',
                    'prefix' => (string) ($row->type_prefix ?? ''),
                ];

                return $transaction;
            })
            ->values();

        return [
            'key' => 'other_savings',
            'title' => 'Other Savings',
            'total' => $total,
            'category_count' => $categories->count(),
            'transaction_count' => $categories->sum(
                fn(array $category) => (int) $category['transaction_count']
            ),
            'categories' => $categories,
            'transactions' => $transactions,
        ];
    }

    private function buildSpecialSavings(int $memberId): array
    {
        $transactionCountSubQuery = DB::table(
    'sacco_special_saving_transactions'
)
    ->selectRaw(
        'special_saving_transaction_account_id, ' .
        'COUNT(*) AS transaction_count'
    )
    ->where(
        'special_saving_transaction_member_id',
        $memberId
    )
    ->where(
        'special_saving_transaction_deleted',
        'N'
    )
    ->where(function ($query) {
        $query
            ->where(
                'special_saving_transaction_reversed',
                'N'
            )
            ->orWhereNull(
                'special_saving_transaction_reversed'
            );
    })
    ->groupBy(
        'special_saving_transaction_account_id'
    );
        $accountRows = DB::table('sacco_special_saving_accounts as a')
            ->leftJoin(
                'sacco_special_saving_products as p',
                'p.special_saving_product_id',
                '=',
                'a.special_saving_account_product_id'
            )
            ->leftJoinSub($transactionCountSubQuery, 'tc', function ($join) {
                $join->on(
                    'tc.special_saving_transaction_account_id',
                    '=',
                    'a.special_saving_account_id'
                );
            })
            ->where('a.special_saving_account_member_id', $memberId)
            ->where('a.special_saving_account_deleted', 'N')
            ->select([
                'a.special_saving_account_id',
                'a.special_saving_account_product_id',
                'a.special_saving_account_number',
                'a.special_saving_account_opening_date',
                'a.special_saving_account_last_interest_date',
                'a.special_saving_account_last_withdrawal_date',
                'a.special_saving_account_next_free_withdrawal_date',
                'a.special_saving_account_principal_balance',
                'a.special_saving_account_accrued_interest_balance',
                'a.special_saving_account_available_interest_balance',
                'a.special_saving_account_forfeited_interest_balance',
                'a.special_saving_account_total_balance',
                'a.special_saving_account_status',
                'p.special_saving_product_name',
                'p.special_saving_product_code',
                'p.special_saving_product_annual_interest_rate',
                'p.special_saving_product_monthly_interest_rate',
                'p.special_saving_product_withdrawal_cycle_months',
                DB::raw('COALESCE(tc.transaction_count, 0) AS transaction_count'),
            ])
            ->orderBy('p.special_saving_product_name')
            ->orderBy('a.special_saving_account_number')
            ->get();

        $accounts = $accountRows
            ->map(function ($row) {
                return [
                    'account_id' => (int) $row->special_saving_account_id,
                    'account_number' => (string) ($row->special_saving_account_number ?? ''),
                    'status' => (string) ($row->special_saving_account_status ?? ''),
                    'opening_date' => $this->isoDate($row->special_saving_account_opening_date),
                    'last_interest_date' => $this->isoDate($row->special_saving_account_last_interest_date),
                    'last_withdrawal_date' => $this->isoDate($row->special_saving_account_last_withdrawal_date),
                    'next_free_withdrawal_date' => $this->isoDate($row->special_saving_account_next_free_withdrawal_date),
                    'product' => [
                        'id' => (int) $row->special_saving_account_product_id,
                        'name' => (string) ($row->special_saving_product_name ?? 'Special Savings'),
                        'code' => (string) ($row->special_saving_product_code ?? ''),
                        'annual_interest_rate' => (float) ($row->special_saving_product_annual_interest_rate ?? 0),
                        'monthly_interest_rate' => (float) ($row->special_saving_product_monthly_interest_rate ?? 0),
                        'withdrawal_cycle_months' => (int) ($row->special_saving_product_withdrawal_cycle_months ?? 0),
                    ],
                    'balances' => [
                        'principal' => $this->money((float) $row->special_saving_account_principal_balance),
                        'accrued_interest' => $this->money((float) $row->special_saving_account_accrued_interest_balance),
                        'available_interest' => $this->money((float) $row->special_saving_account_available_interest_balance),
                        'forfeited_interest' => $this->money((float) $row->special_saving_account_forfeited_interest_balance),
                        'total' => $this->money((float) $row->special_saving_account_total_balance),
                    ],
                    'transaction_count' => (int) $row->transaction_count,
                ];
            })
            ->values();

        $summary = [
            'principal' => $this->money(
                $accounts->sum(fn(array $account) => (float) $account['balances']['principal'])
            ),
            'accrued_interest' => $this->money(
                $accounts->sum(fn(array $account) => (float) $account['balances']['accrued_interest'])
            ),
            'available_interest' => $this->money(
                $accounts->sum(fn(array $account) => (float) $account['balances']['available_interest'])
            ),
            'forfeited_interest' => $this->money(
                $accounts->sum(fn(array $account) => (float) $account['balances']['forfeited_interest'])
            ),
            'total' => $this->money(
                $accounts->sum(fn(array $account) => (float) $account['balances']['total'])
            ),
        ];

        $transactions = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin(
                'sacco_special_saving_accounts as a',
                'a.special_saving_account_id',
                '=',
                't.special_saving_transaction_account_id'
            )
            ->leftJoin(
                'sacco_special_saving_products as p',
                'p.special_saving_product_id',
                '=',
                't.special_saving_transaction_product_id'
            )
            ->where('t.special_saving_transaction_member_id', $memberId)
            ->where('t.special_saving_transaction_deleted', 'N')
            ->where(function ($query) {
                $query->where('t.special_saving_transaction_reversed', 'N')
                    ->orWhereNull('t.special_saving_transaction_reversed');
            })
            ->orderByDesc('t.special_saving_transaction_date')
            ->orderByDesc('t.special_saving_transaction_id')
            ->limit(self::TRANSACTION_LIMIT)
            ->get([
                't.special_saving_transaction_id',
                't.special_saving_transaction_account_id',
                't.special_saving_transaction_product_id',
                't.special_saving_transaction_type',
                't.special_saving_transaction_direction',
                't.special_saving_transaction_amount',
                't.special_saving_transaction_principal_amount',
                't.special_saving_transaction_interest_amount',
                't.special_saving_transaction_penalty_amount',
                't.special_saving_transaction_charge_amount',
                't.special_saving_transaction_date',
                't.special_saving_transaction_period',
                't.special_saving_transaction_doc_no',
                't.special_saving_transaction_reference',
                't.special_saving_transaction_source',
                't.special_saving_transaction_principal_balance_after',
                't.special_saving_transaction_accrued_interest_after',
                't.special_saving_transaction_available_interest_after',
                't.special_saving_transaction_total_balance_after',
                't.special_saving_transaction_description',
                'a.special_saving_account_number',
                'p.special_saving_product_name',
                'p.special_saving_product_code',
            ])
            ->map(function ($row) {
                $direction = strtoupper(trim((string) $row->special_saving_transaction_direction));
                $isCredit = $direction !== 'DEBIT';
                $type = strtoupper(trim((string) $row->special_saving_transaction_type));

                return [
                    'id' => (int) $row->special_saving_transaction_id,
                    'date' => $this->isoDate($row->special_saving_transaction_date),
                    'display_date' => $this->displayDate($row->special_saving_transaction_date),
                    'period' => (string) ($row->special_saving_transaction_period ?? ''),
                    'type' => $type,
                    'direction' => $isCredit ? 'CREDIT' : 'DEBIT',
                    'is_credit' => $isCredit,
                    'description' => $this->cleanDescription(
                        $row->special_saving_transaction_description,
                        $this->titleFromCode($type ?: 'SPECIAL SAVINGS TRANSACTION')
                    ),
                    'amount' => $this->money(abs((float) $row->special_saving_transaction_amount)),
                    'components' => [
                        'principal' => $this->money(abs((float) $row->special_saving_transaction_principal_amount)),
                        'interest' => $this->money(abs((float) $row->special_saving_transaction_interest_amount)),
                        'penalty' => $this->money(abs((float) $row->special_saving_transaction_penalty_amount)),
                        'charge' => $this->money(abs((float) $row->special_saving_transaction_charge_amount)),
                    ],
                    'balance_after' => [
                        'principal' => $this->money((float) $row->special_saving_transaction_principal_balance_after),
                        'accrued_interest' => $this->money((float) $row->special_saving_transaction_accrued_interest_after),
                        'available_interest' => $this->money((float) $row->special_saving_transaction_available_interest_after),
                        'total' => $this->money((float) $row->special_saving_transaction_total_balance_after),
                    ],
                    'document_number' => (string) ($row->special_saving_transaction_doc_no ?? ''),
                    'reference' => (string) ($row->special_saving_transaction_reference ?? ''),
                    'source' => (string) ($row->special_saving_transaction_source ?? ''),
                    'account' => [
                        'id' => (int) $row->special_saving_transaction_account_id,
                        'number' => (string) ($row->special_saving_account_number ?? ''),
                    ],
                    'product' => [
                        'id' => (int) $row->special_saving_transaction_product_id,
                        'name' => (string) ($row->special_saving_product_name ?? 'Special Savings'),
                        'code' => (string) ($row->special_saving_product_code ?? ''),
                    ],
                ];
            })
            ->values();

        return [
            'key' => 'special_savings',
            'title' => 'Special Savings',
            'total' => $summary['total'],
            'summary' => $summary,
            'account_count' => $accounts->count(),
            'transaction_count' => $accounts->sum(
                fn(array $account) => (int) $account['transaction_count']
            ),
            'accounts' => $accounts,
            'transactions' => $transactions,
        ];
    }

    private function otherSavingsBaseQuery(int $memberId, array $specialProductTokens)
    {
        $query = DB::table('sacco_fosas as f')
            ->leftJoin(
                'sacco_fosa_types as ft',
                'f.fosa_type_id',
                '=',
                'ft.type_id'
            )
            ->where('f.fosa_member_id', $memberId);

        if ($specialProductTokens !== []) {
            $query->where(function ($outer) use ($specialProductTokens) {
                $outer->whereNull('ft.type_id')
                    ->orWhere(function ($typed) use ($specialProductTokens) {
                        $typed->whereNotIn(
                            DB::raw('UPPER(TRIM(ft.type_name))'),
                            $specialProductTokens
                        )->whereNotIn(
                            DB::raw('UPPER(TRIM(ft.type_prefix))'),
                            $specialProductTokens
                        );
                    });
            });
        }

        return $query;
    }

    /**
     * Mirrors the desktop statement rule: a legacy FOSA type whose name or
     * prefix matches a special-savings product must not appear under FOSA.
     */
    private function specialSavingProductTokens(): array
    {
        return DB::table('sacco_special_saving_products')
            ->where('special_saving_product_deleted', 'N')
            ->get([
                'special_saving_product_name',
                'special_saving_product_code',
            ])
            ->flatMap(function ($product) {
                return [
                    strtoupper(trim((string) $product->special_saving_product_name)),
                    strtoupper(trim((string) $product->special_saving_product_code)),
                ];
            })
            ->filter(fn(string $token) => $token !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function mapStandardTransaction(
        int $id,
        float $amount,
        mixed $period,
        mixed $paidDate,
        mixed $fallbackDate,
        mixed $description,
        string $fallbackDescription,
        mixed $documentNumber,
        mixed $paidBy
    ): array {
        $effectiveDate = $paidDate ?: $fallbackDate;
        $isCredit = $amount >= 0;

        return [
            'id' => $id,
            'date' => $this->isoDate($effectiveDate),
            'display_date' => $this->displayDate($effectiveDate),
            'period' => (string) ($period ?? ''),
            'description' => $this->cleanDescription(
                $description,
                $fallbackDescription
            ),
            'document_number' => (string) ($documentNumber ?? ''),
            'paid_by' => (string) ($paidBy ?? ''),
            'amount' => $this->money(abs($amount)),
            'direction' => $isCredit ? 'CREDIT' : 'DEBIT',
            'is_credit' => $isCredit,
        ];
    }

    private function cleanDescription(mixed $description, string $fallback): string
    {
        $value = trim((string) ($description ?? ''));

        if ($value === '') {
            return $fallback;
        }

        $value = preg_replace('/^(CR|DR)\s*-\s*/i', '', $value) ?? $value;

        return trim($value) !== '' ? trim($value) : $fallback;
    }

    private function titleFromCode(string $value): string
    {
        $value = strtolower(str_replace(['_', '-'], ' ', trim($value)));

        return ucwords($value);
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function displayDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function money(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
