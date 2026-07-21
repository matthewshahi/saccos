<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessDueSpecialSavingsInterest extends Command
{
    private const TIMEZONE = 'Africa/Nairobi';
    private const SYSTEM_SOURCE = 'AUTO_INTEREST';
    private const SYSTEM_IP = 'SYSTEM';

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'special-savings:process-due-interest
        {--date= : Processing date in YYYY-MM-DD format. Defaults to today in Africa/Nairobi}
        {--cutoff-date= : Earliest interest cycle-end date allowed. Defaults to config special_savings.interest_cutoff_date}
        {--limit= : Override the dynamically calculated batch size}
        {--min-batch=10 : Minimum automatic batch size}
        {--max-batch=100 : Maximum automatic batch size}
        {--account= : Process one special-savings account only}
        {--dry-run : Calculate and display results without posting interest}';

    /**
     * The console command description.
     */
    protected $description = 'Calculate and post due special-savings interest using each product\'s configured rules.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $asOfDate = $this->resolveProcessingDate();
            $cutoffDate = $this->resolveCutoffDate();
            $minBatch = max(1, (int) $this->option('min-batch'));
            $maxBatch = max($minBatch, (int) $this->option('max-batch'));
            $accountId = $this->option('account') !== null
                ? max(1, (int) $this->option('account'))
                : null;
            $dryRun = (bool) $this->option('dry-run');

            $dueAccounts = $this->loadDueAccounts($asOfDate, $cutoffDate, $accountId);
            $eligibleCount = $dueAccounts->count();

            if ($eligibleCount === 0) {
                $this->info(sprintf(
                    'No special-savings accounts are due for interest as at %s using cutoff %s.',
                    $asOfDate->toDateString(),
                    $cutoffDate->toDateString()
                ));

                return self::SUCCESS;
            }

            $batchSize = $this->resolveBatchSize(
                eligibleCount: $eligibleCount,
                minBatch: $minBatch,
                maxBatch: $maxBatch,
                accountId: $accountId
            );

            $selected = $dueAccounts->take($batchSize);

            $this->line(sprintf(
                'Due accounts: %d | Batch: %d | Date: %s | Cutoff: %s | Mode: %s',
                $eligibleCount,
                $selected->count(),
                $asOfDate->toDateString(),
                $cutoffDate->toDateString(),
                $dryRun ? 'DRY RUN' : 'LIVE'
            ));

            $summary = [
                'posted' => 0,
                'skipped' => 0,
                'duplicate' => 0,
                'not_due' => 0,
                'eligible' => 0,
                'errors' => 0,
                'total_interest' => 0.00,
            ];

            $previewRows = [];

            foreach ($selected as $candidate) {
                try {
                    if ($dryRun) {
                        $result = $this->previewAccount(
                            account: $candidate->account,
                            product: $candidate->product,
                            cycleStart: $candidate->cycle_start,
                            cycleEnd: $candidate->cycle_end
                        );
                    } else {
                        $result = $this->processAccount(
                            accountId: (int) $candidate->account->special_saving_account_id,
                            asOfDate: $asOfDate,
                            cutoffDate: $cutoffDate
                        );
                    }

                    $status = $result['status'];

                    if (array_key_exists($status, $summary)) {
                        $summary[$status]++;
                    }

                    if (in_array($status, ['posted', 'eligible'], true)) {
                        $summary['total_interest'] += (float) $result['interest_amount'];
                    }

                    if ($dryRun) {
                        $previewRows[] = [
                            $result['account_number'],
                            $result['product_code'],
                            $result['cycle_start'],
                            $result['cycle_end'],
                            $result['method'],
                            number_format((float) $result['qualifying_balance'], 2),
                            number_format((float) $result['periodic_rate'], 6) . '%',
                            number_format((float) $result['interest_amount'], 2),
                            strtoupper($result['status']),
                            $result['reason'] ?? '',
                        ];
                    }
                } catch (Throwable $e) {
                    $summary['errors']++;

                    Log::error('Automatic special-savings interest processing failed.', [
                        'account_id' => $candidate->account->special_saving_account_id ?? null,
                        'product_id' => $candidate->product->special_saving_product_id ?? null,
                        'as_of_date' => $asOfDate->toDateString(),
                        'cutoff_date' => $cutoffDate->toDateString(),
                        'exception' => $e,
                    ]);

                    $this->error(sprintf(
                        'Account %s failed: %s',
                        $candidate->account->special_saving_account_number
                            ?? $candidate->account->special_saving_account_id
                            ?? 'unknown',
                        $e->getMessage()
                    ));
                }
            }

            if ($dryRun && count($previewRows) > 0) {
                $this->newLine();
                $this->table([
                    'Account',
                    'Product',
                    'Cycle Start',
                    'Cycle End',
                    'Method',
                    'Qualifying Balance',
                    'Rate',
                    'Interest',
                    'Status',
                    'Reason',
                ], $previewRows);
            }

            $this->newLine();
            $this->info(sprintf(
                'Completed. Posted: %d | Eligible preview: %d | Skipped: %d | Duplicate: %d | Not due: %d | Errors: %d | Interest: KES %s',
                $summary['posted'],
                $summary['eligible'],
                $summary['skipped'],
                $summary['duplicate'],
                $summary['not_due'],
                $summary['errors'],
                number_format($summary['total_interest'], 2)
            ));

            Log::info('Automatic special-savings interest batch completed.', [
                'as_of_date' => $asOfDate->toDateString(),
                'cutoff_date' => $cutoffDate->toDateString(),
                'eligible_count' => $eligibleCount,
                'batch_size' => $selected->count(),
                'dry_run' => $dryRun,
                'summary' => $summary,
            ]);

            return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            Log::critical('Automatic special-savings interest command failed.', [
                'exception' => $e,
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Load accounts whose next anniversary-based interest date is due.
     *
     * Only one overdue cycle per account is returned per command invocation.
     * This deliberately spreads catch-up processing across the available runs.
     */
    private function loadDueAccounts(
        Carbon $asOfDate,
        Carbon $cutoffDate,
        ?int $accountId = null
    )
    {
        $rows = DB::table('sacco_special_saving_accounts as a')
            ->join(
                'sacco_special_saving_products as p',
                'p.special_saving_product_id',
                '=',
                'a.special_saving_account_product_id'
            )
            ->where('a.special_saving_account_deleted', 'N')
            ->where('a.special_saving_account_status', 'Active')
            ->where('p.special_saving_product_deleted', 'N')
            ->where('p.special_saving_product_status', 'Active')
            ->when($accountId !== null, function ($query) use ($accountId) {
                $query->where('a.special_saving_account_id', $accountId);
            })
            ->select([
                'a.*',
                'p.special_saving_product_id',
                'p.special_saving_product_category_id',
                'p.special_saving_product_name',
                'p.special_saving_product_code',
                'p.special_saving_product_rate_mode',
                'p.special_saving_product_annual_interest_rate',
                'p.special_saving_product_monthly_interest_rate',
                'p.special_saving_product_interest_method',
                'p.special_saving_product_interest_posting_frequency',
                'p.special_saving_product_require_full_month',
                'p.special_saving_product_member_minimum_days',
                'p.special_saving_product_deposit_minimum_days',
                'p.special_saving_product_interest_credit_policy',
                'p.special_saving_product_minimum_balance',
                'p.special_saving_product_status',
                'p.special_saving_product_deleted',
            ])
            ->orderByRaw('COALESCE(a.special_saving_account_last_interest_date, a.special_saving_account_opening_date) ASC')
            ->orderBy('a.special_saving_account_id')
            ->get();

        return $rows
            ->map(function ($row) use ($cutoffDate) {
                try {
                    $account = clone $row;
                    $product = clone $row;
                    $cycle = $this->determineFirstCycleOnOrAfterCutoff(
                        account: $account,
                        product: $product,
                        cutoffDate: $cutoffDate
                    );

                    return (object) [
                        'account' => $account,
                        'product' => $product,
                        'cycle_start' => $cycle['start'],
                        'cycle_end' => $cycle['end'],
                    ];
                } catch (Throwable $e) {
                    Log::error('Special-savings account has an unsupported interest configuration.', [
                        'account_id' => $row->special_saving_account_id ?? null,
                        'product_id' => $row->special_saving_product_id ?? null,
                        'exception' => $e,
                    ]);

                    return null;
                }
            })
            ->filter()
            ->filter(function ($candidate) use ($asOfDate) {
                return $candidate->cycle_end->lte($asOfDate);
            })
            ->sort(function ($left, $right) {
                $dateComparison = $left->cycle_end->timestamp <=> $right->cycle_end->timestamp;

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return (int) $left->account->special_saving_account_id
                    <=> (int) $right->account->special_saving_account_id;
            })
            ->values();
    }

    /**
     * Process one account inside a database transaction and account-row lock.
     */
    private function processAccount(
        int $accountId,
        Carbon $asOfDate,
        Carbon $cutoffDate
    ): array {
        return DB::transaction(function () use ($accountId, $asOfDate, $cutoffDate) {
            $account = DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                throw new RuntimeException("Special-savings account {$accountId} was not found.");
            }

            if (
                strtoupper((string) $account->special_saving_account_status) !== 'ACTIVE'
                || strtoupper((string) $account->special_saving_account_deleted) !== 'N'
            ) {
                return $this->basicResult($account, null, 'not_due', 'Account is not active.');
            }

            $product = DB::table('sacco_special_saving_products')
                ->where('special_saving_product_id', $account->special_saving_account_product_id)
                ->first();

            if (!$product) {
                throw new RuntimeException('The account product was not found.');
            }

            if (
                strtoupper((string) $product->special_saving_product_status) !== 'ACTIVE'
                || strtoupper((string) $product->special_saving_product_deleted) !== 'N'
            ) {
                return $this->basicResult($account, $product, 'not_due', 'Product is not active.');
            }

            $cycle = $this->determineFirstCycleOnOrAfterCutoff(
                account: $account,
                product: $product,
                cutoffDate: $cutoffDate
            );
            $cycleStart = $cycle['start'];
            $cycleEnd = $cycle['end'];

            if ($cycleEnd->gt($asOfDate)) {
                return $this->basicResult($account, $product, 'not_due', 'The next interest date has not arrived.', $cycleStart, $cycleEnd);
            }

            $existingTransaction = DB::table('sacco_special_saving_transactions')
                ->where('special_saving_transaction_account_id', $accountId)
                ->where('special_saving_transaction_type', 'INTEREST_ACCRUAL')
                ->whereDate('special_saving_transaction_date', $cycleEnd->toDateString())
                ->where('special_saving_transaction_reversed', 'N')
                ->where('special_saving_transaction_deleted', 'N')
                ->first();

            if ($existingTransaction) {
                $this->advanceAccountInterestDate($accountId, $cycleEnd);

                return $this->basicResult(
                    $account,
                    $product,
                    'duplicate',
                    'Interest already exists for this account and cycle.',
                    $cycleStart,
                    $cycleEnd,
                    (float) $existingTransaction->special_saving_transaction_interest_amount
                );
            }

            $calculation = $this->calculateAccrual($account, $product, $cycleStart, $cycleEnd);

            if ($calculation['qualified'] !== 'Y') {
                // A due cycle that earns no interest is still considered reviewed.
                // Advancing the date prevents the same skipped cycle from being
                // reconsidered every minute throughout the processing window.
                $this->advanceAccountInterestDate($accountId, $cycleEnd);

                Log::info('Special-savings interest cycle skipped.', [
                    'account_id' => $accountId,
                    'product_id' => $product->special_saving_product_id,
                    'cycle_start' => $cycleStart->toDateString(),
                    'cycle_end' => $cycleEnd->toDateString(),
                    'reason' => $calculation['reason'],
                    'calculation' => $calculation,
                ]);

                return $this->resultFromCalculation(
                    account: $account,
                    product: $product,
                    cycleStart: $cycleStart,
                    cycleEnd: $cycleEnd,
                    calculation: $calculation,
                    status: 'skipped'
                );
            }

            $creditPolicy = strtoupper(trim((string) $product->special_saving_product_interest_credit_policy));

            if (!in_array($creditPolicy, ['KEEP_SEPARATE', 'ACCRUE_SEPARATELY'], true)) {
                throw new RuntimeException(
                    "Unsupported interest credit policy [{$creditPolicy}]. "
                    . 'This command currently posts interest safely only to accrued interest.'
                );
            }

            $interestAmount = (float) $calculation['interest_amount'];
            $newAccrued = round(
                (float) $account->special_saving_account_accrued_interest_balance + $interestAmount,
                2
            );
            $newTotal = round(
                (float) $account->special_saving_account_principal_balance
                + $newAccrued
                + (float) $account->special_saving_account_available_interest_balance,
                2
            );

            DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_id', $accountId)
                ->update([
                    'special_saving_account_accrued_interest_balance' => $newAccrued,
                    'special_saving_account_total_balance' => $newTotal,
                    'special_saving_account_last_interest_date' => $cycleEnd->toDateString(),
                    'special_saving_account_by' => null,
                    'special_saving_account_ip' => self::SYSTEM_IP,
                    'special_saving_account_transdate' => now(self::TIMEZONE),
                ]);

            $transactionId = DB::table('sacco_special_saving_transactions')->insertGetId([
                'special_saving_transaction_account_id' => $accountId,
                'special_saving_transaction_member_id' => $account->special_saving_account_member_id,
                'special_saving_transaction_product_id' => $account->special_saving_account_product_id,

                'special_saving_transaction_type' => 'INTEREST_ACCRUAL',
                'special_saving_transaction_direction' => 'CREDIT',
                'special_saving_transaction_amount' => $interestAmount,
                'special_saving_transaction_principal_amount' => 0,
                'special_saving_transaction_interest_amount' => $interestAmount,
                'special_saving_transaction_penalty_amount' => 0,
                'special_saving_transaction_charge_amount' => 0,

                'special_saving_transaction_date' => $cycleEnd->toDateString(),
                'special_saving_transaction_period' => $cycleEnd->format('Ym'),
                'special_saving_transaction_doc_no' => $this->interestDocumentNumber($accountId, $cycleEnd),
                'special_saving_transaction_reference' => sprintf(
                    'AUTO-INTEREST:%s:%s',
                    $cycleStart->format('Ymd'),
                    $cycleEnd->format('Ymd')
                ),
                'special_saving_transaction_source' => self::SYSTEM_SOURCE,
                'special_saving_transaction_sub_account_id' => null,
                'special_saving_transaction_ledger_posted' => 'N',
                'special_saving_transaction_ledger_ref' => null,

                'special_saving_transaction_principal_balance_after' => round(
                    (float) $account->special_saving_account_principal_balance,
                    2
                ),
                'special_saving_transaction_accrued_interest_after' => $newAccrued,
                'special_saving_transaction_available_interest_after' => round(
                    (float) $account->special_saving_account_available_interest_balance,
                    2
                ),
                'special_saving_transaction_total_balance_after' => $newTotal,

                'special_saving_transaction_reversed' => 'N',
                'special_saving_transaction_description' => sprintf(
                    'Automatic %s special-savings interest for cycle %s to %s. '
                    . 'Method: %s; qualifying balance: KES %s; rate: %s%%.',
                    strtolower((string) $product->special_saving_product_interest_posting_frequency),
                    $cycleStart->toDateString(),
                    $cycleEnd->toDateString(),
                    $calculation['method'],
                    number_format((float) $calculation['qualifying_balance'], 2, '.', ''),
                    number_format((float) $calculation['periodic_rate'], 6, '.', '')
                ),

                'special_saving_transaction_by' => null,
                'special_saving_transaction_ip' => self::SYSTEM_IP,
                'special_saving_transaction_transdate' => now(self::TIMEZONE),
                'special_saving_transaction_deleted' => 'N',
            ]);

            $this->postSpecialSavingsInterestToLedger(
    (int) $transactionId
);

            Log::info('Special-savings interest posted automatically.', [
                'transaction_id' => $transactionId,
                'account_id' => $accountId,
                'member_id' => $account->special_saving_account_member_id,
                'product_id' => $product->special_saving_product_id,
                'cycle_start' => $cycleStart->toDateString(),
                'cycle_end' => $cycleEnd->toDateString(),
                'calculation' => $calculation,
            ]);

            

            $result = $this->resultFromCalculation(
                account: $account,
                product: $product,
                cycleStart: $cycleStart,
                cycleEnd: $cycleEnd,
                calculation: $calculation,
                status: 'posted'
            );
            $result['transaction_id'] = $transactionId;

            return $result;
        }, 3);
    }

    /**
     * Preview a due account without changing any records.
     */
    private function previewAccount($account, $product, Carbon $cycleStart, Carbon $cycleEnd): array
    {
        $calculation = $this->calculateAccrual($account, $product, $cycleStart, $cycleEnd);

        return $this->resultFromCalculation(
            account: $account,
            product: $product,
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd,
            calculation: $calculation,
            status: $calculation['qualified'] === 'Y' ? 'eligible' : 'skipped'
        );
    }

    /**
     * Calculate eligibility, qualifying principal, rate and interest.
     */
    private function calculateAccrual($account, $product, Carbon $cycleStart, Carbon $cycleEnd): array
    {
        $openingDate = Carbon::parse(
            $account->special_saving_account_opening_date,
            self::TIMEZONE
        )->startOfDay();

        $daysInProduct = $openingDate->diffInDays($cycleEnd) + 1;
        $cycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
        $balanceData = $this->calculateQualifyingBalance(
            accountId: (int) $account->special_saving_account_id,
            currentPrincipal: (float) $account->special_saving_account_principal_balance,
            product: $product,
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd
        );

        $rate = $this->resolvePeriodicRate(
            product: $product,
            qualifyingBalance: (float) $balanceData['qualifying_balance']
        );

        $interestAmount = round(
            (float) $balanceData['qualifying_balance'] * ((float) $rate['periodic_rate'] / 100),
            2
        );

        $qualified = 'Y';
        $reason = null;

        if ($openingDate->gt($cycleEnd)) {
            $qualified = 'N';
            $reason = 'Account had not opened by the cycle end date.';
        }

        if (
            $qualified === 'Y'
            && (int) $product->special_saving_product_member_minimum_days > 0
            && $daysInProduct < (int) $product->special_saving_product_member_minimum_days
        ) {
            $qualified = 'N';
            $reason = 'Account has not met the configured minimum days in the product.';
        }

        if ($balanceData['auditable'] !== true) {
            throw new RuntimeException(
                'Principal balance could not be reconstructed from the special-savings transaction ledger.'
            );
        }

        $minimumBalance = round((float) $product->special_saving_product_minimum_balance, 2);

        if (
            $qualified === 'Y'
            && $minimumBalance > 0
            && (float) $balanceData['qualifying_balance'] < $minimumBalance
        ) {
            $qualified = 'N';
            $reason = 'Qualifying balance is below the product minimum balance.';
        }

        if ($qualified === 'Y' && (float) $balanceData['qualifying_balance'] <= 0) {
            $qualified = 'N';
            $reason = 'No qualifying principal balance.';
        }

        if ($qualified === 'Y' && $interestAmount <= 0) {
            $qualified = 'N';
            $reason = 'Calculated interest rounds to zero.';
        }

        return array_merge($balanceData, [
            'qualified' => $qualified,
            'reason' => $reason,
            'annual_rate' => $rate['annual_rate'],
            'monthly_rate' => $rate['monthly_rate'],
            'periodic_rate' => $rate['periodic_rate'],
            'interest_amount' => $qualified === 'Y' ? $interestAmount : 0.00,
            'days_in_product' => $daysInProduct,
            'cycle_days' => $cycleDays,
            'method' => strtoupper(trim((string) $product->special_saving_product_interest_method)),
        ]);
    }

    /**
     * Reconstruct principal balances for the account-specific review cycle.
     *
     * Deposit minimum days are applied to every supported method by capping
     * the method result at the portion of closing principal old enough to earn.
     */
    private function calculateQualifyingBalance(
        int $accountId,
        float $currentPrincipal,
        $product,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): array {
        $startDate = $cycleStart->toDateString();
        $endDate = $cycleEnd->toDateString();

        $lastAtStart = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $accountId)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->whereDate('special_saving_transaction_date', '<=', $startDate)
            ->orderByDesc('special_saving_transaction_date')
            ->orderByDesc('special_saving_transaction_id')
            ->first();

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $accountId)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->whereDate('special_saving_transaction_date', '>', $startDate)
            ->whereDate('special_saving_transaction_date', '<=', $endDate)
            ->orderBy('special_saving_transaction_date')
            ->orderBy('special_saving_transaction_id')
            ->get();

        $openingBalance = $lastAtStart
            ? (float) $lastAtStart->special_saving_transaction_principal_balance_after
            : 0.00;

        $auditable = $lastAtStart !== null
            || $transactions->isNotEmpty()
            || round($currentPrincipal, 2) === 0.00;

        $closingBalance = $openingBalance;
        $minimumBalance = $openingBalance;

        foreach ($transactions as $transaction) {
            $closingBalance = (float) $transaction->special_saving_transaction_principal_balance_after;
            $minimumBalance = min($minimumBalance, $closingBalance);
        }

        $dailyAverageBalance = $this->calculateAverageDailyBalance(
            openingBalance: $openingBalance,
            transactions: $transactions,
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd
        );

        $depositMinimumDays = max(
            0,
            (int) $product->special_saving_product_deposit_minimum_days
        );
        $immaturePrincipal = 0.00;

        if ($depositMinimumDays > 0) {
            $maturityCutoff = $cycleEnd->copy()->subDays($depositMinimumDays);

            $immaturePrincipal = (float) DB::table('sacco_special_saving_transactions')
                ->where('special_saving_transaction_account_id', $accountId)
                ->where('special_saving_transaction_deleted', 'N')
                ->where('special_saving_transaction_reversed', 'N')
                ->where('special_saving_transaction_direction', 'CREDIT')
                ->where('special_saving_transaction_principal_amount', '>', 0)
                ->whereDate('special_saving_transaction_date', '>', $maturityCutoff->toDateString())
                ->whereDate('special_saving_transaction_date', '<=', $endDate)
                ->sum('special_saving_transaction_principal_amount');
        }

        $matureClosingBalance = max(0, $closingBalance - $immaturePrincipal);
        $method = strtoupper(trim((string) $product->special_saving_product_interest_method));

        $methodBalance = match ($method) {
            'CLOSING_BALANCE' => $closingBalance,
            'MINIMUM_MONTHLY_BALANCE', 'MINIMUM_BALANCE' => $minimumBalance,
            'DAILY_BALANCE', 'AVERAGE_DAILY_BALANCE' => $dailyAverageBalance,
            default => throw new RuntimeException(
                "Unsupported special-savings interest method [{$method}]."
            ),
        };

        $qualifyingBalance = $depositMinimumDays > 0
            ? min($methodBalance, $matureClosingBalance)
            : $methodBalance;

        return [
            'auditable' => $auditable,
            'opening_balance' => round(max(0, $openingBalance), 2),
            'closing_balance' => round(max(0, $closingBalance), 2),
            'minimum_balance' => round(max(0, $minimumBalance), 2),
            'daily_average_balance' => round(max(0, $dailyAverageBalance), 2),
            'immature_principal' => round(max(0, $immaturePrincipal), 2),
            'mature_closing_balance' => round(max(0, $matureClosingBalance), 2),
            'qualifying_balance' => round(max(0, $qualifyingBalance), 2),
        ];
    }

    /**
     * Calculate the average of each day's closing principal balance.
     * The cycle start is exclusive and the cycle end is inclusive.
     */
    private function calculateAverageDailyBalance(
        float $openingBalance,
        $transactions,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): float {
        $lastBalanceByDate = [];

        foreach ($transactions as $transaction) {
            $lastBalanceByDate[$transaction->special_saving_transaction_date] =
                (float) $transaction->special_saving_transaction_principal_balance_after;
        }

        $currentBalance = $openingBalance;
        $sumOfDailyBalances = 0.00;
        $days = 0;
        $date = $cycleStart->copy()->addDay();

        while ($date->lte($cycleEnd)) {
            $dateString = $date->toDateString();

            if (array_key_exists($dateString, $lastBalanceByDate)) {
                $currentBalance = $lastBalanceByDate[$dateString];
            }

            $sumOfDailyBalances += max(0, $currentBalance);
            $days++;
            $date->addDay();
        }

        return $days > 0 ? $sumOfDailyBalances / $days : max(0, $openingBalance);
    }

    /**
     * Resolve the product or tier rate and convert it to the configured period.
     */
    private function resolvePeriodicRate($product, float $qualifyingBalance): array
    {
        $months = $this->frequencyMonths(
            (string) $product->special_saving_product_interest_posting_frequency
        );

        $annualRate = (float) $product->special_saving_product_annual_interest_rate;
        $monthlyRate = (float) $product->special_saving_product_monthly_interest_rate;
        $rateMode = strtoupper(trim((string) $product->special_saving_product_rate_mode));

        if (in_array($rateMode, ['TIERED', 'TIERED_RATE'], true)) {
            $tier = DB::table('sacco_special_saving_product_rate_tiers')
                ->where('special_saving_rate_tier_product_id', $product->special_saving_product_id)
                ->where('special_saving_rate_tier_deleted', 'N')
                ->where('special_saving_rate_tier_status', 'Active')
                ->where('special_saving_rate_tier_min_amount', '<=', $qualifyingBalance)
                ->where(function ($query) use ($qualifyingBalance) {
                    $query->whereNull('special_saving_rate_tier_max_amount')
                        ->orWhere('special_saving_rate_tier_max_amount', '>=', $qualifyingBalance);
                })
                ->orderByDesc('special_saving_rate_tier_min_amount')
                ->first();

            if ($tier) {
                $annualRate = (float) $tier->special_saving_rate_tier_annual_rate;
                $monthlyRate = (float) $tier->special_saving_rate_tier_monthly_rate;
            }
        } elseif (!in_array($rateMode, ['FLAT', 'FLAT_RATE'], true)) {
            throw new RuntimeException(
                "Unsupported special-savings rate mode [{$rateMode}]."
            );
        }

        // Fall back to annual/12 if a SACCO has not persisted the monthly rate.
        if ($monthlyRate <= 0 && $annualRate > 0) {
            $monthlyRate = round($annualRate / 12, 6);
        }

        return [
            'annual_rate' => round($annualRate, 6),
            'monthly_rate' => round($monthlyRate, 6),
            'periodic_rate' => round($monthlyRate * $months, 6),
        ];
    }

    /**
     * Determine the next interest cycle using a stable account schedule anchor.
     *
     * The opening date provides the normal anniversary day. Where an imported
     * or manually maintained last-interest date clearly follows a different
     * schedule, that established schedule is retained.
     *
     * Examples:
     * - Opening on 25th: always 25th.
     * - Opening on 30th: February uses 28/29, then returns to 30th.
     * - Opening on 31st or an established month-end schedule: always month-end.
     */
    private function determineNextCycle($account, $product): array
    {
        $openingDate = Carbon::parse(
            $account->special_saving_account_opening_date,
            self::TIMEZONE
        )->startOfDay();

        $lastInterestDate = !empty($account->special_saving_account_last_interest_date)
            ? Carbon::parse(
                $account->special_saving_account_last_interest_date,
                self::TIMEZONE
            )->startOfDay()
            : null;

        $cycleStart = $lastInterestDate
            ? $lastInterestDate->copy()
            : $openingDate->copy();

        $months = $this->frequencyMonths(
            (string) $product->special_saving_product_interest_posting_frequency
        );

        $anchor = $this->resolveCycleAnchor(
            openingDate: $openingDate,
            lastInterestDate: $lastInterestDate
        );

        return $this->buildNextCycle(
            cycleStart: $cycleStart,
            months: $months,
            anchorDay: $anchor['day'],
            monthEndAnchor: $anchor['month_end']
        );
    }

    /**
     * Move forward virtually until the first cycle whose end date is on or
     * after the configured cutoff date.
     *
     * Excluded historical cycles are not posted and do not require database
     * updates. The first allowed cycle is then processed normally.
     */
    private function determineFirstCycleOnOrAfterCutoff(
        $account,
        $product,
        Carbon $cutoffDate
    ): array {
        $openingDate = Carbon::parse(
            $account->special_saving_account_opening_date,
            self::TIMEZONE
        )->startOfDay();

        $lastInterestDate = !empty($account->special_saving_account_last_interest_date)
            ? Carbon::parse(
                $account->special_saving_account_last_interest_date,
                self::TIMEZONE
            )->startOfDay()
            : null;

        $cycleStart = $lastInterestDate
            ? $lastInterestDate->copy()
            : $openingDate->copy();

        $months = $this->frequencyMonths(
            (string) $product->special_saving_product_interest_posting_frequency
        );

        $anchor = $this->resolveCycleAnchor(
            openingDate: $openingDate,
            lastInterestDate: $lastInterestDate
        );

        /*
         * The guard prevents malformed legacy data or unsupported date logic
         * from causing an infinite loop.
         */
        for ($i = 0; $i < 600; $i++) {
            $cycle = $this->buildNextCycle(
                cycleStart: $cycleStart,
                months: $months,
                anchorDay: $anchor['day'],
                monthEndAnchor: $anchor['month_end']
            );

            if ($cycle['end']->gte($cutoffDate)) {
                return $cycle;
            }

            $cycleStart = $cycle['end']->copy();
        }

        throw new RuntimeException(sprintf(
            'Unable to determine a processable interest cycle for account %s after cutoff %s.',
            $account->special_saving_account_id,
            $cutoffDate->toDateString()
        ));
    }

    /**
     * Resolve the fixed anniversary anchor for this account.
     */
    private function resolveCycleAnchor(
        Carbon $openingDate,
        ?Carbon $lastInterestDate
    ): array {
        $anchorDay = $openingDate->day;
        $monthEndAnchor = $anchorDay === 31;

        if ($lastInterestDate !== null) {
            $expectedDayFromOpeningAnchor = min(
                $openingDate->day,
                $lastInterestDate->daysInMonth
            );

            $lastDateFollowsOpeningAnchor =
                $lastInterestDate->day === $expectedDayFromOpeningAnchor;

            /*
             * A different imported/manual schedule becomes the established
             * schedule. If that date is month-end, retain month-end permanently.
             */
            if (!$lastDateFollowsOpeningAnchor) {
                $anchorDay = $lastInterestDate->day;
                $monthEndAnchor =
                    $lastInterestDate->day === $lastInterestDate->daysInMonth;
            }
        }

        return [
            'day' => $anchorDay,
            'month_end' => $monthEndAnchor,
        ];
    }

    /**
     * Build one cycle from a given start date and fixed schedule anchor.
     */
    private function buildNextCycle(
        Carbon $cycleStart,
        int $months,
        int $anchorDay,
        bool $monthEndAnchor
    ): array {
        $targetMonth = $cycleStart
            ->copy()
            ->startOfMonth()
            ->addMonthsNoOverflow($months);

        if ($monthEndAnchor) {
            $cycleEnd = $targetMonth->copy()->endOfMonth()->startOfDay();
        } else {
            $targetDay = min($anchorDay, $targetMonth->daysInMonth);
            $cycleEnd = $targetMonth->copy()->day($targetDay)->startOfDay();
        }

        if ($cycleEnd->lte($cycleStart)) {
            throw new RuntimeException(sprintf(
                'Invalid special-savings interest cycle generated: %s to %s.',
                $cycleStart->toDateString(),
                $cycleEnd->toDateString()
            ));
        }

        return [
            'start' => $cycleStart->copy(),
            'end' => $cycleEnd,
        ];
    }

    /**
     * Convert supported frequency settings into calendar months.
     */
    private function frequencyMonths(string $frequency): int
    {
        return match (strtoupper(trim($frequency))) {
            'MONTHLY' => 1,
            'QUARTERLY' => 3,
            'SEMI_ANNUAL', 'SEMIANNUAL', 'HALF_YEARLY' => 6,
            'ANNUAL', 'YEARLY' => 12,
            default => throw new RuntimeException(
                "Unsupported interest posting frequency [{$frequency}]."
            ),
        };
    }

    /**
     * Advance a reviewed account to the completed cycle date.
     */
    private function advanceAccountInterestDate(int $accountId, Carbon $cycleEnd): void
    {
        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $accountId)
            ->update([
                'special_saving_account_last_interest_date' => $cycleEnd->toDateString(),
                'special_saving_account_by' => null,
                'special_saving_account_ip' => self::SYSTEM_IP,
                'special_saving_account_transdate' => now(self::TIMEZONE),
            ]);
    }

    /**
     * Calculate a batch that spreads the eligible workload over the remaining
     * minute-runs in the 02:00-03:59 interest-processing window.
     */
    private function resolveBatchSize(
        int $eligibleCount,
        int $minBatch,
        int $maxBatch,
        ?int $accountId
    ): int {
        if ($accountId !== null) {
            return 1;
        }

        $limitOption = $this->option('limit');

        if ($limitOption !== null && $limitOption !== '') {
            return min($eligibleCount, max(1, (int) $limitOption));
        }

        $now = now(self::TIMEZONE);
        $windowStart = $now->copy()->setTime(2, 0, 0);
        $windowEnd = $now->copy()->setTime(4, 0, 0);

        $remainingRuns = $now->gte($windowStart) && $now->lt($windowEnd)
            ? max(1, $now->diffInMinutes($windowEnd))
            : 1;

        $calculated = (int) ceil($eligibleCount / $remainingRuns);
        $calculated = max($minBatch, $calculated);
        $calculated = min($maxBatch, $calculated);

        return min($eligibleCount, $calculated);
    }

    /**
     * Resolve the command processing date.
     */
    private function resolveProcessingDate(): Carbon
    {
        $date = $this->option('date');

        if ($date === null || trim((string) $date) === '') {
            return now(self::TIMEZONE)->startOfDay();
        }

        $parsed = Carbon::createFromFormat('Y-m-d', (string) $date, self::TIMEZONE);

        if (!$parsed || $parsed->format('Y-m-d') !== (string) $date) {
            throw new RuntimeException('The --date option must use YYYY-MM-DD format.');
        }

        return $parsed->startOfDay();
    }


    /**
     * Resolve the earliest cycle-end date that may be calculated.
     *
     * Precedence:
     * 1. Explicit --cutoff-date option
     * 2. config('special_savings.interest_cutoff_date')
     */
    private function resolveCutoffDate(): Carbon
    {
        $value = $this->option('cutoff-date');

        if ($value === null || trim((string) $value) === '') {
            $value = config('special_savings.interest_cutoff_date');
        }

        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException(
                'Special-savings interest cutoff date is not configured. '
                . 'Set SPECIAL_SAVINGS_INTEREST_CUTOFF_DATE or use --cutoff-date.'
            );
        }

        $value = trim((string) $value);
        $parsed = Carbon::createFromFormat('Y-m-d', $value, self::TIMEZONE);

        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            throw new RuntimeException(
                'The special-savings interest cutoff date must use YYYY-MM-DD format.'
            );
        }

        return $parsed->startOfDay();
    }

    /**
     * Generate a deterministic document number for idempotency and audit.
     */
    private function interestDocumentNumber(int $accountId, Carbon $cycleEnd): string
    {
        return sprintf('SSI-AUTO-%d-%s', $accountId, $cycleEnd->format('Ymd'));
    }

    /**
     * Build a result from a completed calculation.
     */
    private function resultFromCalculation(
        $account,
        $product,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        array $calculation,
        string $status
    ): array {
        return [
            'status' => $status,
            'account_id' => (int) $account->special_saving_account_id,
            'account_number' => $account->special_saving_account_number
                ?? (string) $account->special_saving_account_id,
            'product_id' => (int) $product->special_saving_product_id,
            'product_code' => $product->special_saving_product_code,
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'method' => $calculation['method'],
            'qualifying_balance' => (float) $calculation['qualifying_balance'],
            'periodic_rate' => (float) $calculation['periodic_rate'],
            'interest_amount' => (float) $calculation['interest_amount'],
            'reason' => $calculation['reason'],
        ];
    }

    /**
     * Build a simple result for duplicate/not-due cases.
     */
    private function basicResult(
        $account,
        $product,
        string $status,
        string $reason,
        ?Carbon $cycleStart = null,
        ?Carbon $cycleEnd = null,
        float $interestAmount = 0.00
    ): array {
        return [
            'status' => $status,
            'account_id' => (int) $account->special_saving_account_id,
            'account_number' => $account->special_saving_account_number
                ?? (string) $account->special_saving_account_id,
            'product_id' => $product?->special_saving_product_id,
            'product_code' => $product?->special_saving_product_code ?? '',
            'cycle_start' => $cycleStart?->toDateString() ?? '',
            'cycle_end' => $cycleEnd?->toDateString() ?? '',
            'method' => $product?->special_saving_product_interest_method ?? '',
            'qualifying_balance' => 0.00,
            'periodic_rate' => 0.00,
            'interest_amount' => $interestAmount,
            'reason' => $reason,
        ];
    }

/**
 * Post one completed special-savings interest accrual to the General Ledger.
 *
 * Accounting entry:
 *   Dr Special Savings Interest Expense
 *   Cr Special Savings Accrued Interest Payable
 *
 * Call this immediately after inserting the INTEREST_ACCRUAL transaction.
 *
 * @throws RuntimeException
 */
private function postSpecialSavingsInterestToLedger(
    int $specialSavingTransactionId
): string {
    return DB::transaction(function () use ($specialSavingTransactionId): string {
        /*
        |--------------------------------------------------------------------------
        | 1. Lock and validate the subsidiary transaction
        |--------------------------------------------------------------------------
        */
        $transaction = DB::table('sacco_special_saving_transactions')
            ->where(
                'special_saving_transaction_id',
                $specialSavingTransactionId
            )
            ->lockForUpdate()
            ->first();

        if (!$transaction) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'was not found.'
            );
        }

        if (
            strtoupper(trim((string) $transaction->special_saving_transaction_type))
            !== 'INTEREST_ACCRUAL'
        ) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'is not an interest accrual transaction.'
            );
        }

        if (
            strtoupper(trim((string) $transaction->special_saving_transaction_deleted))
            === 'Y'
            || strtoupper(trim((string) $transaction->special_saving_transaction_reversed))
            === 'Y'
        ) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'is deleted or reversed and cannot be posted.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Already posted: return safely without duplicating the ledger
        |--------------------------------------------------------------------------
        */
        if (
            strtoupper(trim(
                (string) $transaction->special_saving_transaction_ledger_posted
            )) === 'Y'
        ) {
            return (string) (
                $transaction->special_saving_transaction_ledger_ref
                ?? $transaction->special_saving_transaction_doc_no
            );
        }

        $interestAmount = round(
            (float) $transaction->special_saving_transaction_interest_amount,
            2
        );

        if ($interestAmount <= 0) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'has an invalid interest amount.'
            );
        }

        $documentNumber = trim(
            (string) $transaction->special_saving_transaction_doc_no
        );

        if ($documentNumber === '') {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'does not have a document number.'
            );
        }

        $period = trim(
            (string) $transaction->special_saving_transaction_period
        );

        if (!preg_match('/^\d{6}$/', $period)) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . "has an invalid accounting period [{$period}]."
            );
        }

        $postingDate = $transaction->special_saving_transaction_date;

        if (empty($postingDate)) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'does not have a posting date.'
            );
        }

        $description = trim(
            (string) $transaction->special_saving_transaction_description
        );

        if ($description === '') {
            $description = 'Automatic special-savings interest accrual.';
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Resolve required ledger defaults
        |--------------------------------------------------------------------------
        */
        $requiredDefaults = [
            'expense' =>
                'special_savings_ledger_interest_expense_account',

            'accrued_payable' =>
                'special_savings_ledger_accrued_interest_payable_account',
        ];

        $resolvedAccounts = [];

        foreach ($requiredDefaults as $key => $defaultName) {
            $defaultRows = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->lockForUpdate()
                ->get();

            if ($defaultRows->count() !== 1) {
                Log::error(
                    'Required special-savings ledger default is missing or duplicated.',
                    [
                        'default_name' => $defaultName,
                        'rows_found' => $defaultRows->count(),
                        'transaction_id' => $specialSavingTransactionId,
                    ]
                );

                throw new RuntimeException(
                    "Required ledger default [{$defaultName}] is missing "
                    . 'or duplicated. No ledger entry was posted.'
                );
            }

            $configuredValue = trim(
                (string) $defaultRows->first()->default_value
            );

            if (
                $configuredValue === ''
                || !ctype_digit($configuredValue)
                || (int) $configuredValue <= 0
            ) {
                Log::error(
                    'Required special-savings ledger default is invalid.',
                    [
                        'default_name' => $defaultName,
                        'default_value' => $configuredValue,
                        'transaction_id' => $specialSavingTransactionId,
                    ]
                );

                throw new RuntimeException(
                    "Ledger default [{$defaultName}] does not contain "
                    . 'a valid sub-account ID.'
                );
            }

            $resolvedAccounts[$key] = (int) $configuredValue;
        }

        if (
            $resolvedAccounts['expense']
            === $resolvedAccounts['accrued_payable']
        ) {
            throw new RuntimeException(
                'The interest expense account and accrued-interest payable '
                . 'account cannot be the same sub-account.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Validate the sub-accounts and their classifications
        |--------------------------------------------------------------------------
        */
        $accounts = DB::table('sacco_sub_account as sub')
            ->join(
                'sacco_main_account as main',
                'main.main_account_id',
                '=',
                'sub.sub_account_main_account'
            )
            ->whereIn('sub.sub_account_id', [
                $resolvedAccounts['expense'],
                $resolvedAccounts['accrued_payable'],
            ])
            ->whereRaw(
                "UPPER(COALESCE(sub.sub_account_deleted, 'N')) <> 'Y'"
            )
            ->whereRaw(
                "UPPER(COALESCE(main.main_account_deleted, 'N')) <> 'Y'"
            )
            ->lockForUpdate()
            ->select([
                'sub.sub_account_id',
                'sub.sub_account_name',
                'sub.sub_account_code',
                'sub.sub_account_main_account',
                'main.main_account_id',
                'main.main_account_code',
                'main.main_account_name',
            ])
            ->get()
            ->keyBy('sub_account_id');

        $expenseAccount = $accounts->get(
            $resolvedAccounts['expense']
        );

        $payableAccount = $accounts->get(
            $resolvedAccounts['accrued_payable']
        );

        if (!$expenseAccount) {
            throw new RuntimeException(
                'The configured special-savings interest expense '
                . 'sub-account does not exist or is deleted.'
            );
        }

        if (!$payableAccount) {
            throw new RuntimeException(
                'The configured special-savings accrued-interest payable '
                . 'sub-account does not exist or is deleted.'
            );
        }

        if (
            !preg_match(
                '/^E\d{3}$/',
                strtoupper(trim((string) $expenseAccount->main_account_code))
            )
        ) {
            throw new RuntimeException(
                'The configured interest expense account is not under '
                . 'a correctly classified E### expense main account.'
            );
        }

        if (
            !preg_match(
                '/^L\d{3}$/',
                strtoupper(trim((string) $payableAccount->main_account_code))
            )
        ) {
            throw new RuntimeException(
                'The configured accrued-interest payable account is not under '
                . 'a correctly classified L### liability main account.'
            );
        }

        if (
            !preg_match(
                '/^\d{3}$/',
                trim((string) $expenseAccount->sub_account_code)
            )
        ) {
            throw new RuntimeException(
                'The interest expense sub-account code must contain '
                . 'exactly three numeric digits.'
            );
        }

        if (
            !preg_match(
                '/^\d{3}$/',
                trim((string) $payableAccount->sub_account_code)
            )
        ) {
            throw new RuntimeException(
                'The accrued-interest payable sub-account code must contain '
                . 'exactly three numeric digits.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Prevent duplicate General Ledger posting
        |--------------------------------------------------------------------------
        */
        $existingLedgerRows = DB::table('sacco_accounts_trans')
            ->where('accounts_trans_doc_no', $documentNumber)
            ->where('accounts_trans_source', self::SYSTEM_SOURCE)
            ->lockForUpdate()
            ->get();

        $ledgerReference = 'SSI-GL-' . $specialSavingTransactionId;

        if ($existingLedgerRows->isNotEmpty()) {
            $expenseDebit = round(
                (float) $existingLedgerRows
                    ->where(
                        'accounts_trans_sub_account',
                        $resolvedAccounts['expense']
                    )
                    ->sum('accounts_trans_debit'),
                2
            );

            $payableCredit = round(
                (float) $existingLedgerRows
                    ->where(
                        'accounts_trans_sub_account',
                        $resolvedAccounts['accrued_payable']
                    )
                    ->sum('accounts_trans_credit'),
                2
            );

            $totalDebit = round(
                (float) $existingLedgerRows->sum('accounts_trans_debit'),
                2
            );

            $totalCredit = round(
                (float) $existingLedgerRows->sum('accounts_trans_credit'),
                2
            );

            $isCorrectExistingLedger =
                $existingLedgerRows->count() === 2
                && abs($expenseDebit - $interestAmount) < 0.005
                && abs($payableCredit - $interestAmount) < 0.005
                && abs($totalDebit - $totalCredit) < 0.005;

            if (!$isCorrectExistingLedger) {
                throw new RuntimeException(
                    "A conflicting or incomplete General Ledger entry already "
                    . "exists for document [{$documentNumber}]."
                );
            }

            DB::table('sacco_special_saving_transactions')
                ->where(
                    'special_saving_transaction_id',
                    $specialSavingTransactionId
                )
                ->update([
                    'special_saving_transaction_sub_account_id' =>
                        $resolvedAccounts['accrued_payable'],

                    'special_saving_transaction_ledger_posted' => 'Y',

                    'special_saving_transaction_ledger_ref' =>
                        $ledgerReference,
                ]);

            return $ledgerReference;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Build the balanced journal
        |--------------------------------------------------------------------------
        */
        $ledgerRows = [
            [
                'accounts_trans_sub_account' =>
                    $resolvedAccounts['expense'],

                'accounts_trans_period' => $period,

                'accounts_trans_debit' => $interestAmount,
                'accounts_trans_credit' => 0,

                'accounts_trans_doc_no' => $documentNumber,

                'accounts_trans_decription' =>
                    $description
                    . ' | Debit: '
                    . $expenseAccount->sub_account_name,

                'accounts_trans_dat_date' => $postingDate,

                'accounts_trans_user_id' => null,
                'accounts_trans_ip' => self::SYSTEM_IP,

                'accounts_trans_source' => self::SYSTEM_SOURCE,
                'accounts_trans_app_name' => 'iSacco',
            ],

            [
                'accounts_trans_sub_account' =>
                    $resolvedAccounts['accrued_payable'],

                'accounts_trans_period' => $period,

                'accounts_trans_debit' => 0,
                'accounts_trans_credit' => $interestAmount,

                'accounts_trans_doc_no' => $documentNumber,

                'accounts_trans_decription' =>
                    $description
                    . ' | Credit: '
                    . $payableAccount->sub_account_name,

                'accounts_trans_dat_date' => $postingDate,

                'accounts_trans_user_id' => null,
                'accounts_trans_ip' => self::SYSTEM_IP,

                'accounts_trans_source' => self::SYSTEM_SOURCE,
                'accounts_trans_app_name' => 'iSacco',
            ],
        ];

        $totalDebit = round(
            array_sum(array_column($ledgerRows, 'accounts_trans_debit')),
            2
        );

        $totalCredit = round(
            array_sum(array_column($ledgerRows, 'accounts_trans_credit')),
            2
        );

        if (abs($totalDebit - $totalCredit) >= 0.005) {
            throw new RuntimeException(
                'Special-savings interest ledger is not balanced. '
                . 'Debit: ' . number_format($totalDebit, 2)
                . ', Credit: ' . number_format($totalCredit, 2) . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Insert detailed General Ledger entries
        |--------------------------------------------------------------------------
        */
        DB::table('sacco_accounts_trans')->insert($ledgerRows);

        /*
        |--------------------------------------------------------------------------
        | 7. Update sub-account cached totals
        |--------------------------------------------------------------------------
        */
        $expenseUpdated = DB::table('sacco_sub_account')
            ->where(
                'sub_account_id',
                $resolvedAccounts['expense']
            )
            ->increment(
                'sub_account_debit',
                $interestAmount
            );

        $payableUpdated = DB::table('sacco_sub_account')
            ->where(
                'sub_account_id',
                $resolvedAccounts['accrued_payable']
            )
            ->increment(
                'sub_account_credit',
                $interestAmount
            );

        if ($expenseUpdated !== 1 || $payableUpdated !== 1) {
            throw new RuntimeException(
                'Failed to update the special-savings sub-account totals.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Update main-account cached totals
        |--------------------------------------------------------------------------
        */
        $expenseMainUpdated = DB::table('sacco_main_account')
            ->where(
                'main_account_id',
                $expenseAccount->main_account_id
            )
            ->increment(
                'main_account_debit',
                $interestAmount
            );

        $payableMainUpdated = DB::table('sacco_main_account')
            ->where(
                'main_account_id',
                $payableAccount->main_account_id
            )
            ->increment(
                'main_account_credit',
                $interestAmount
            );

        if (
            $expenseMainUpdated !== 1
            || $payableMainUpdated !== 1
        ) {
            throw new RuntimeException(
                'Failed to update the special-savings main-account totals.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Mark the subsidiary transaction as posted
        |--------------------------------------------------------------------------
        */
        $transactionUpdated =
            DB::table('sacco_special_saving_transactions')
                ->where(
                    'special_saving_transaction_id',
                    $specialSavingTransactionId
                )
                ->update([
                    'special_saving_transaction_sub_account_id' =>
                        $resolvedAccounts['accrued_payable'],

                    'special_saving_transaction_ledger_posted' => 'Y',

                    'special_saving_transaction_ledger_ref' =>
                        $ledgerReference,
                ]);

        if ($transactionUpdated !== 1) {
            throw new RuntimeException(
                'The General Ledger was created, but the special-savings '
                . 'transaction could not be marked as ledger-posted.'
            );
        }

        Log::info(
            'Special-savings interest posted to the General Ledger.',
            [
                'special_saving_transaction_id' =>
                    $specialSavingTransactionId,

                'member_id' =>
                    $transaction->special_saving_transaction_member_id,

                'document_number' => $documentNumber,
                'ledger_reference' => $ledgerReference,

                'interest_amount' => $interestAmount,

                'expense_sub_account_id' =>
                    $resolvedAccounts['expense'],

                'accrued_payable_sub_account_id' =>
                    $resolvedAccounts['accrued_payable'],

                'period' => $period,
                'posting_date' => $postingDate,
            ]
        );

        return $ledgerReference;
    }, 3);
}
    }
