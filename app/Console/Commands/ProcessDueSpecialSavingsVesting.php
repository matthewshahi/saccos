<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessDueSpecialSavingsVesting extends Command
{
    private const TIMEZONE = 'Africa/Nairobi';
    private const SYSTEM_SOURCE = 'AUTO_VESTING';
    private const SYSTEM_IP = 'SYSTEM';

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'special-savings:process-due-vesting
        {--date= : Processing date in YYYY-MM-DD format. Defaults to today in Africa/Nairobi}
        {--start-date= : Earliest vesting review date. Defaults to config special_savings.vesting_start_date}
        {--limit= : Override the dynamically calculated batch size}
        {--min-batch=10 : Minimum automatic batch size}
        {--max-batch=100 : Maximum automatic batch size}
        {--account= : Process one special-savings account only}
        {--product= : Process accounts belonging to one special-savings product only}
        {--dry-run : Preview vesting without changing balances or creating transactions}';

    /**
     * The console command description.
     */
    protected $description = 'Vest due special-savings interest according to each product\'s configured vesting policy.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $asOfDate = $this->resolveProcessingDate();
            $startDate = $this->resolveVestingStartDate();

            if ($startDate->gt($asOfDate)) {
                throw new RuntimeException(
                    'The vesting start date cannot be later than the processing date.'
                );
            }

            $minBatch = max(1, (int) $this->option('min-batch'));
            $maxBatch = max($minBatch, (int) $this->option('max-batch'));
            $accountId = $this->positiveIntegerOption('account');
            $productId = $this->positiveIntegerOption('product');
            $dryRun = (bool) $this->option('dry-run');

            $dueAccounts = $this->loadDueAccounts(
                asOfDate: $asOfDate,
                startDate: $startDate,
                accountId: $accountId,
                productId: $productId
            );

            $eligibleCount = $dueAccounts->count();

            if ($eligibleCount === 0) {
                $this->info(
                    'No special-savings accounts are due for interest vesting as at '
                    . $asOfDate->toDateString()
                    . ' using vesting start '
                    . $startDate->toDateString()
                    . '.'
                );

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
                'Due accounts: %d | Batch: %d | Date: %s | Start: %s | Mode: %s',
                $eligibleCount,
                $selected->count(),
                $asOfDate->toDateString(),
                $startDate->toDateString(),
                $dryRun ? 'DRY RUN' : 'LIVE'
            ));

            $summary = [
                'vested' => 0,
                'eligible' => 0,
                'advanced_without_interest' => 0,
                'duplicate' => 0,
                'not_due' => 0,
                'skipped' => 0,
                'errors' => 0,
                'total_vested' => 0.00,
            ];

            $previewRows = [];

            foreach ($selected as $candidate) {
                try {
                    $result = $dryRun
                        ? $this->previewAccount(
                            account: $candidate->account,
                            product: $candidate->product,
                            asOfDate: $asOfDate,
                            startDate: $startDate
                        )
                        : $this->processAccount(
                            accountId: (int) $candidate->account->special_saving_account_id,
                            asOfDate: $asOfDate,
                            startDate: $startDate
                        );

                    $status = $result['status'];

                    if (array_key_exists($status, $summary)) {
                        $summary[$status]++;
                    }

                    if (in_array($status, ['vested', 'eligible'], true)) {
                        $summary['total_vested'] += (float) $result['vesting_amount'];
                    }

                    if ($dryRun) {
                        $previewRows[] = [
                            $result['account_number'],
                            $result['product_code'],
                            $result['policy'],
                            $result['due_date'] ?? '',
                            number_format((float) $result['vesting_amount'], 2),
                            number_format((float) $result['available_after'], 2),
                            $result['next_vesting_date'] ?? '',
                            strtoupper($result['status']),
                            $result['reason'] ?? '',
                        ];
                    }
                } catch (Throwable $e) {
                    $summary['errors']++;

                    Log::error('Automatic special-savings vesting failed for an account.', [
                        'account_id' => $candidate->account->special_saving_account_id ?? null,
                        'product_id' => $candidate->product->special_saving_product_id ?? null,
                        'as_of_date' => $asOfDate->toDateString(),
                        'vesting_start_date' => $startDate->toDateString(),
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
                    'Vesting Policy',
                    'Due Date',
                    'Amount',
                    'Available After',
                    'Next Vesting',
                    'Status',
                    'Reason',
                ], $previewRows);
            }

            $this->newLine();
            $this->info(sprintf(
                'Completed. Vested: %d | Eligible preview: %d | Empty cycles advanced: %d | Duplicate: %d | Not due: %d | Skipped: %d | Errors: %d | Total vested: KES %s',
                $summary['vested'],
                $summary['eligible'],
                $summary['advanced_without_interest'],
                $summary['duplicate'],
                $summary['not_due'],
                $summary['skipped'],
                $summary['errors'],
                number_format($summary['total_vested'], 2)
            ));

            Log::info('Automatic special-savings vesting batch completed.', [
                'as_of_date' => $asOfDate->toDateString(),
                'vesting_start_date' => $startDate->toDateString(),
                'eligible_count' => $eligibleCount,
                'batch_size' => $selected->count(),
                'dry_run' => $dryRun,
                'summary' => $summary,
            ]);

            return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            Log::critical('Automatic special-savings vesting command failed.', [
                'exception' => $e,
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Load due accounts in oldest-due-date order.
     *
     * Product settings remain authoritative. Products configured for manual or
     * disabled vesting are not processed automatically.
     */
    private function loadDueAccounts(
        Carbon $asOfDate,
        Carbon $startDate,
        ?int $accountId = null,
        ?int $productId = null
    ) {
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
            ->when($productId !== null, function ($query) use ($productId) {
                $query->where('a.special_saving_account_product_id', $productId);
            })
            ->select([
                'a.*',
                'p.special_saving_product_id',
                'p.special_saving_product_name',
                'p.special_saving_product_code',
                'p.special_saving_product_withdrawal_cycle_months',
                'p.special_saving_product_interest_vesting_policy',
                'p.special_saving_product_interest_credit_policy',
                'p.special_saving_product_status',
                'p.special_saving_product_deleted',
            ])
            ->orderByRaw(
                'COALESCE(a.special_saving_account_next_free_withdrawal_date, a.special_saving_account_opening_date) ASC'
            )
            ->orderBy('a.special_saving_account_id')
            ->get();

        return $rows
            ->map(function ($row) use ($asOfDate, $startDate) {
                try {
                    $account = clone $row;
                    $product = clone $row;
                    $schedule = $this->determineVestingSchedule(
                        account: $account,
                        product: $product,
                        asOfDate: $asOfDate,
                        startDate: $startDate
                    );

                    return (object) [
                        'account' => $account,
                        'product' => $product,
                        'due_date' => $schedule['due_date'],
                        'policy' => $schedule['policy'],
                        'is_due' => $schedule['is_due'],
                    ];
                } catch (Throwable $e) {
                    Log::error('Special-savings account has an unsupported vesting configuration.', [
                        'account_id' => $row->special_saving_account_id ?? null,
                        'product_id' => $row->special_saving_product_id ?? null,
                        'exception' => $e,
                    ]);

                    return null;
                }
            })
            ->filter()
            ->filter(function ($candidate) use ($accountId) {
                // When a single account is requested, return it so the command
                // can explain why it is not due or cannot be auto-vested.
                return $accountId !== null || $candidate->is_due === true;
            })
            ->sort(function ($left, $right) {
                $leftTimestamp = $left->due_date?->timestamp ?? PHP_INT_MAX;
                $rightTimestamp = $right->due_date?->timestamp ?? PHP_INT_MAX;

                $dateComparison = $leftTimestamp <=> $rightTimestamp;

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return (int) $left->account->special_saving_account_id
                    <=> (int) $right->account->special_saving_account_id;
            })
            ->values();
    }

    /**
     * Process one account inside a database transaction and row lock.
     */
    private function processAccount(
        int $accountId,
        Carbon $asOfDate,
        Carbon $startDate
    ): array {
        return DB::transaction(function () use ($accountId, $asOfDate, $startDate) {
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
                return $this->basicResult(
                    account: $account,
                    product: null,
                    status: 'not_due',
                    reason: 'Account is not active.'
                );
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
                return $this->basicResult(
                    account: $account,
                    product: $product,
                    status: 'not_due',
                    reason: 'Product is not active.'
                );
            }

            $schedule = $this->determineVestingSchedule(
                account: $account,
                product: $product,
                asOfDate: $asOfDate,
                startDate: $startDate
            );

            if ($schedule['automatic'] !== true) {
                return $this->result(
                    account: $account,
                    product: $product,
                    status: 'skipped',
                    reason: $schedule['reason'],
                    policy: $schedule['policy'],
                    dueDate: $schedule['due_date'],
                    vestingAmount: 0.00,
                    availableAfter: (float) $account->special_saving_account_available_interest_balance,
                    nextVestingDate: $account->special_saving_account_next_free_withdrawal_date
                        ? Carbon::parse($account->special_saving_account_next_free_withdrawal_date, self::TIMEZONE)
                        : null
                );
            }

            if ($schedule['is_due'] !== true) {
                return $this->result(
                    account: $account,
                    product: $product,
                    status: 'not_due',
                    reason: 'The next vesting date has not arrived.',
                    policy: $schedule['policy'],
                    dueDate: $schedule['due_date'],
                    vestingAmount: 0.00,
                    availableAfter: (float) $account->special_saving_account_available_interest_balance,
                    nextVestingDate: $schedule['due_date']
                );
            }

            $dueDate = $schedule['due_date'] ?? $asOfDate->copy();
            $reference = $this->vestingReference($schedule['policy'], $dueDate);
            $cycleStart = $this->determineVestingCycleStart(
                account: $account,
                product: $product,
                policy: $schedule['policy'],
                dueDate: $dueDate,
                startDate: $startDate
            );
            $nextVestingDate = $this->determineNextVestingDate(
                account: $account,
                product: $product,
                policy: $schedule['policy'],
                dueDate: $dueDate,
                startDate: $startDate
            );

            $existingTransaction = DB::table('sacco_special_saving_transactions')
                ->where('special_saving_transaction_account_id', $accountId)
                ->where('special_saving_transaction_type', 'INTEREST_VESTING')
                ->where('special_saving_transaction_reference', $reference)
                ->where('special_saving_transaction_reversed', 'N')
                ->where('special_saving_transaction_deleted', 'N')
                ->first();

            if ($existingTransaction) {
                $this->advanceVestingDate(
                    accountId: $accountId,
                    nextVestingDate: $nextVestingDate
                );

                return $this->result(
                    account: $account,
                    product: $product,
                    status: 'duplicate',
                    reason: 'This vesting cycle has already been posted.',
                    policy: $schedule['policy'],
                    dueDate: $dueDate,
                    vestingAmount: (float) $existingTransaction->special_saving_transaction_interest_amount,
                    availableAfter: (float) $account->special_saving_account_available_interest_balance,
                    nextVestingDate: $nextVestingDate
                );
            }

            $includeCycleStart = $cycleStart !== null
                && $cycleStart->equalTo(
                    $this->effectiveVestingAnchorDate(
                        account: $account,
                        startDate: $startDate
                    )
                );

            $vestingAmount = $this->calculateVestableInterest(
                account: $account,
                policy: $schedule['policy'],
                cycleStart: $cycleStart,
                cycleEnd: $dueDate,
                includeCycleStart: $includeCycleStart
            );

            if ($vestingAmount <= 0) {
                $this->advanceVestingDate(
                    accountId: $accountId,
                    nextVestingDate: $nextVestingDate
                );

                Log::info('Special-savings vesting cycle advanced without interest.', [
                    'account_id' => $accountId,
                    'product_id' => $product->special_saving_product_id,
                    'policy' => $schedule['policy'],
                    'cycle_start' => $cycleStart?->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'next_vesting_date' => $nextVestingDate?->toDateString(),
                ]);

                return $this->result(
                    account: $account,
                    product: $product,
                    status: 'advanced_without_interest',
                    reason: 'No accrued interest was available to vest.',
                    policy: $schedule['policy'],
                    dueDate: $dueDate,
                    vestingAmount: 0.00,
                    availableAfter: (float) $account->special_saving_account_available_interest_balance,
                    nextVestingDate: $nextVestingDate
                );
            }

            $newAccrued = round(
                (float) $account->special_saving_account_accrued_interest_balance - $vestingAmount,
                2
            );
            $newAvailable = round(
                (float) $account->special_saving_account_available_interest_balance + $vestingAmount,
                2
            );
            $newTotal = round(
                (float) $account->special_saving_account_principal_balance
                + $newAccrued
                + $newAvailable,
                2
            );

            DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_id', $accountId)
                ->update([
                    'special_saving_account_accrued_interest_balance' => max(0, $newAccrued),
                    'special_saving_account_available_interest_balance' => max(0, $newAvailable),
                    'special_saving_account_total_balance' => max(0, $newTotal),
                    'special_saving_account_next_free_withdrawal_date' => $nextVestingDate?->toDateString(),
                    'special_saving_account_by' => null,
                    'special_saving_account_ip' => self::SYSTEM_IP,
                    'special_saving_account_transdate' => now(self::TIMEZONE),
                ]);

            $transactionId = DB::table('sacco_special_saving_transactions')->insertGetId([
                'special_saving_transaction_account_id' => $accountId,
                'special_saving_transaction_member_id' => $account->special_saving_account_member_id,
                'special_saving_transaction_product_id' => $account->special_saving_account_product_id,

                'special_saving_transaction_type' => 'INTEREST_VESTING',
                'special_saving_transaction_direction' => 'CREDIT',
                'special_saving_transaction_amount' => $vestingAmount,
                'special_saving_transaction_principal_amount' => 0,
                'special_saving_transaction_interest_amount' => $vestingAmount,
                'special_saving_transaction_penalty_amount' => 0,
                'special_saving_transaction_charge_amount' => 0,

                // Use the actual processing date. In normal scheduled operation,
                // this is the same date as the vesting due date. For an overdue
                // first deployment, this avoids silently backdating a transaction.
                'special_saving_transaction_date' => $asOfDate->toDateString(),
                'special_saving_transaction_period' => $asOfDate->format('Ym'),
                'special_saving_transaction_doc_no' => $this->vestingDocumentNumber(
                    accountId: $accountId,
                    dueDate: $dueDate
                ),
                'special_saving_transaction_reference' => $reference,
                'special_saving_transaction_source' => self::SYSTEM_SOURCE,
                'special_saving_transaction_sub_account_id' => null,
                'special_saving_transaction_ledger_posted' => 'N',
                'special_saving_transaction_ledger_ref' => null,

                'special_saving_transaction_principal_balance_after' => round(
                    (float) $account->special_saving_account_principal_balance,
                    2
                ),
                'special_saving_transaction_accrued_interest_after' => max(0, $newAccrued),
                'special_saving_transaction_available_interest_after' => max(0, $newAvailable),
                'special_saving_transaction_total_balance_after' => max(0, $newTotal),

                'special_saving_transaction_reversed' => 'N',
                'special_saving_transaction_description' => sprintf(
                    'Automatic special-savings interest vesting. Policy: %s; cycle: %s to %s; vested amount: KES %s; next vesting date: %s.',
                    $schedule['policy'],
                    $cycleStart?->toDateString() ?? 'N/A',
                    $dueDate->toDateString(),
                    number_format($vestingAmount, 2, '.', ''),
                    $nextVestingDate?->toDateString() ?? 'N/A'
                ),

                'special_saving_transaction_by' => null,
                'special_saving_transaction_ip' => self::SYSTEM_IP,
                'special_saving_transaction_transdate' => now(self::TIMEZONE),
                'special_saving_transaction_deleted' => 'N',
            ]);

            $this->postSpecialSavingsVestingToLedger(
    (int) $transactionId
);

            Log::info('Special-savings interest vested automatically.', [
                'transaction_id' => $transactionId,
                'account_id' => $accountId,
                'member_id' => $account->special_saving_account_member_id,
                'product_id' => $product->special_saving_product_id,
                'policy' => $schedule['policy'],
                'cycle_start' => $cycleStart?->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'processing_date' => $asOfDate->toDateString(),
                'vesting_start_date' => $startDate->toDateString(),
                'vesting_amount' => $vestingAmount,
                'next_vesting_date' => $nextVestingDate?->toDateString(),
            ]);

            $result = $this->result(
                account: $account,
                product: $product,
                status: 'vested',
                reason: null,
                policy: $schedule['policy'],
                dueDate: $dueDate,
                vestingAmount: $vestingAmount,
                availableAfter: $newAvailable,
                nextVestingDate: $nextVestingDate
            );
            $result['transaction_id'] = $transactionId;

            return $result;
        }, 3);
    }

    /**
     * Preview one account without changing any records.
     */
    private function previewAccount(
        $account,
        $product,
        Carbon $asOfDate,
        Carbon $startDate
    ): array
    {
        $schedule = $this->determineVestingSchedule(
            account: $account,
            product: $product,
            asOfDate: $asOfDate,
            startDate: $startDate
        );

        if ($schedule['automatic'] !== true) {
            return $this->result(
                account: $account,
                product: $product,
                status: 'skipped',
                reason: $schedule['reason'],
                policy: $schedule['policy'],
                dueDate: $schedule['due_date'],
                vestingAmount: 0.00,
                availableAfter: (float) $account->special_saving_account_available_interest_balance,
                nextVestingDate: $account->special_saving_account_next_free_withdrawal_date
                    ? Carbon::parse($account->special_saving_account_next_free_withdrawal_date, self::TIMEZONE)
                    : null
            );
        }

        if ($schedule['is_due'] !== true) {
            return $this->result(
                account: $account,
                product: $product,
                status: 'not_due',
                reason: 'The next vesting date has not arrived.',
                policy: $schedule['policy'],
                dueDate: $schedule['due_date'],
                vestingAmount: 0.00,
                availableAfter: (float) $account->special_saving_account_available_interest_balance,
                nextVestingDate: $schedule['due_date']
            );
        }

        $dueDate = $schedule['due_date'] ?? $asOfDate->copy();
        $cycleStart = $this->determineVestingCycleStart(
            account: $account,
            product: $product,
            policy: $schedule['policy'],
            dueDate: $dueDate,
            startDate: $startDate
        );

        $includeCycleStart = $cycleStart !== null
            && $cycleStart->equalTo(
                $this->effectiveVestingAnchorDate(
                    account: $account,
                    startDate: $startDate
                )
            );

        $vestingAmount = $this->calculateVestableInterest(
            account: $account,
            policy: $schedule['policy'],
            cycleStart: $cycleStart,
            cycleEnd: $dueDate,
            includeCycleStart: $includeCycleStart
        );
        $nextVestingDate = $this->determineNextVestingDate(
            account: $account,
            product: $product,
            policy: $schedule['policy'],
            dueDate: $dueDate,
            startDate: $startDate
        );

        return $this->result(
            account: $account,
            product: $product,
            status: $vestingAmount > 0 ? 'eligible' : 'advanced_without_interest',
            reason: $vestingAmount > 0 ? null : 'No accrued interest is available to vest.',
            policy: $schedule['policy'],
            dueDate: $dueDate,
            vestingAmount: $vestingAmount,
            availableAfter: round(
                (float) $account->special_saving_account_available_interest_balance + $vestingAmount,
                2
            ),
            nextVestingDate: $nextVestingDate
        );
    }

    /**
     * Resolve whether and when the product allows automatic vesting.
     */
    private function determineVestingSchedule(
        $account,
        $product,
        Carbon $asOfDate,
        Carbon $startDate
    ): array {
        $policy = $this->normalizePolicy(
            (string) $product->special_saving_product_interest_vesting_policy
        );
        $creditPolicy = strtoupper(trim(
            (string) $product->special_saving_product_interest_credit_policy
        ));

        if (!in_array($creditPolicy, ['KEEP_SEPARATE', 'ACCRUE_SEPARATELY'], true)) {
            return [
                'policy' => $policy,
                'automatic' => false,
                'is_due' => false,
                'due_date' => null,
                'reason' => "Interest credit policy [{$creditPolicy}] does not use the separate accrued-interest vesting workflow.",
            ];
        }

        if (in_array($policy, ['MANUAL', 'MANUAL_VESTING', 'NO_AUTOMATIC_VESTING', 'NEVER'], true)) {
            return [
                'policy' => $policy,
                'automatic' => false,
                'is_due' => false,
                'due_date' => null,
                'reason' => 'The product is configured for manual or disabled vesting.',
            ];
        }

        $effectiveAnchor = $this->effectiveVestingAnchorDate(
            account: $account,
            startDate: $startDate
        );

        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            $lastInterestDate = $account->special_saving_account_last_interest_date
                ? Carbon::parse(
                    $account->special_saving_account_last_interest_date,
                    self::TIMEZONE
                )->startOfDay()
                : $asOfDate->copy();

            $dueDate = $lastInterestDate->lt($effectiveAnchor)
                ? $effectiveAnchor->copy()
                : $lastInterestDate;

            return [
                'policy' => $policy,
                'automatic' => true,
                'is_due' => (float) $account->special_saving_account_accrued_interest_balance > 0
                    && $dueDate->lte($asOfDate),
                'due_date' => $dueDate,
                'reason' => null,
            ];
        }

        if (!in_array($policy, [
            'AFTER_WITHDRAWAL_CYCLE',
            'AFTER_CYCLE',
            'WITHDRAWAL_CYCLE',
        ], true)) {
            return [
                'policy' => $policy,
                'automatic' => false,
                'is_due' => false,
                'due_date' => null,
                'reason' => "Unsupported automatic vesting policy [{$policy}].",
            ];
        }

        $cycleMonths = max(
            0,
            (int) $product->special_saving_product_withdrawal_cycle_months
        );

        if ($cycleMonths <= 0) {
            $dueDate = $account->special_saving_account_last_interest_date
                ? Carbon::parse(
                    $account->special_saving_account_last_interest_date,
                    self::TIMEZONE
                )->startOfDay()
                : $asOfDate->copy();

            if ($dueDate->lt($effectiveAnchor)) {
                $dueDate = $effectiveAnchor->copy();
            }

            return [
                'policy' => $policy,
                'automatic' => true,
                'is_due' => (float) $account->special_saving_account_accrued_interest_balance > 0
                    && $dueDate->lte($asOfDate),
                'due_date' => $dueDate,
                'reason' => null,
            ];
        }

        $firstDueDate = $this->addMonthsPreservingAnchor(
            fromDate: $effectiveAnchor,
            months: $cycleMonths,
            anchorDay: $effectiveAnchor->day
        );

        $storedDueDate = $account->special_saving_account_next_free_withdrawal_date
            ? Carbon::parse(
                $account->special_saving_account_next_free_withdrawal_date,
                self::TIMEZONE
            )->startOfDay()
            : null;

        /*
         * Historical stored dates before the configured review baseline are
         * ignored. Once processing begins, a later stored date remains
         * authoritative so a completed cycle is never reopened.
         */
        $dueDate = $storedDueDate !== null && $storedDueDate->gte($firstDueDate)
            ? $storedDueDate
            : $firstDueDate;

        return [
            'policy' => $policy,
            'automatic' => true,
            'is_due' => $dueDate->lte($asOfDate),
            'due_date' => $dueDate,
            'reason' => null,
        ];
    }

    /**
     * Derive the first vesting date from the effective review anchor.
     */
    private function initialCycleDueDate(
        $account,
        int $cycleMonths,
        Carbon $startDate
    ): Carbon {
        $anchor = $this->effectiveVestingAnchorDate(
            account: $account,
            startDate: $startDate
        );

        if ($cycleMonths <= 0) {
            return $anchor;
        }

        return $this->addMonthsPreservingAnchor(
            fromDate: $anchor,
            months: $cycleMonths,
            anchorDay: $anchor->day
        );
    }

    /**
     * Determine the start of the vesting cycle represented by a due date.
     */
    private function determineVestingCycleStart(
        $account,
        $product,
        string $policy,
        Carbon $dueDate,
        Carbon $startDate
    ): ?Carbon {
        $effectiveAnchor = $this->effectiveVestingAnchorDate(
            account: $account,
            startDate: $startDate
        );

        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            return $effectiveAnchor;
        }

        $cycleMonths = max(
            0,
            (int) $product->special_saving_product_withdrawal_cycle_months
        );

        if ($cycleMonths <= 0) {
            return $effectiveAnchor;
        }

        $initialDueDate = $this->addMonthsPreservingAnchor(
            fromDate: $effectiveAnchor,
            months: $cycleMonths,
            anchorDay: $effectiveAnchor->day
        );

        if ($dueDate->equalTo($initialDueDate)) {
            return $effectiveAnchor;
        }

        $previousMonth = $dueDate->copy()
            ->startOfMonth()
            ->subMonthsNoOverflow($cycleMonths);

        $cycleStart = $previousMonth
            ->copy()
            ->day(min($effectiveAnchor->day, $previousMonth->daysInMonth))
            ->startOfDay();

        return $cycleStart->lt($effectiveAnchor)
            ? $effectiveAnchor
            : $cycleStart;
    }

    /**
     * Calculate only the accrued-interest movement belonging to the completed
     * vesting cycle. This is essential during first deployment when an account
     * may have several overdue vesting cycles and later-cycle interest must stay
     * unvested until its own due date.
     */
    private function calculateVestableInterest(
        $account,
        string $policy,
        ?Carbon $cycleStart,
        Carbon $cycleEnd,
        bool $includeCycleStart
    ): float {
        $currentAccrued = round(
            max(0, (float) $account->special_saving_account_accrued_interest_balance),
            2
        );

        if ($currentAccrued <= 0) {
            return 0.00;
        }

        if ($cycleStart === null) {
            return 0.00;
        }

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $account->special_saving_account_id)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->when(
                $includeCycleStart,
                fn($query) => $query->whereDate(
                    'special_saving_transaction_date',
                    '>=',
                    $cycleStart->toDateString()
                ),
                fn($query) => $query->whereDate(
                    'special_saving_transaction_date',
                    '>',
                    $cycleStart->toDateString()
                )
            )
            ->whereDate('special_saving_transaction_date', '<=', $cycleEnd->toDateString())
            ->where('special_saving_transaction_interest_amount', '>', 0)
            ->orderBy('special_saving_transaction_date')
            ->orderBy('special_saving_transaction_id')
            ->get([
                'special_saving_transaction_type',
                'special_saving_transaction_direction',
                'special_saving_transaction_interest_amount',
            ]);

        $cycleNetAccrued = 0.00;

        foreach ($transactions as $transaction) {
            $type = strtoupper(trim((string) $transaction->special_saving_transaction_type));
            $direction = strtoupper(trim((string) $transaction->special_saving_transaction_direction));
            $amount = (float) $transaction->special_saving_transaction_interest_amount;

            if (in_array($type, ['INTEREST_VESTING', 'INTEREST_FORFEITURE'], true)) {
                $cycleNetAccrued -= $amount;
                continue;
            }

            if (!in_array($type, [
                'INTEREST_ACCRUAL',
                'IMPORT_INTEREST_CUMULATIVE_ADJUSTMENT',
                'INTEREST_ADJUSTMENT',
            ], true)) {
                continue;
            }

            $cycleNetAccrued += $direction === 'DEBIT' ? -$amount : $amount;
        }

        $cycleNetAccrued = round(max(0, $cycleNetAccrued), 2);

        return min($currentAccrued, $cycleNetAccrued);
    }

    /**
     * Advance by exactly one configured vesting cycle. If several cycles are
     * overdue, later minute-runs process them one at a time in chronological
     * order instead of combining them and risking premature vesting.
     */
    private function determineNextVestingDate(
        $account,
        $product,
        string $policy,
        Carbon $dueDate,
        Carbon $startDate
    ): ?Carbon {
        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            return $account->special_saving_account_next_free_withdrawal_date
                ? Carbon::parse(
                    $account->special_saving_account_next_free_withdrawal_date,
                    self::TIMEZONE
                )->startOfDay()
                : null;
        }

        $cycleMonths = max(
            0,
            (int) $product->special_saving_product_withdrawal_cycle_months
        );

        if ($cycleMonths <= 0) {
            return null;
        }

        $effectiveAnchor = $this->effectiveVestingAnchorDate(
            account: $account,
            startDate: $startDate
        );

        return $this->addMonthsPreservingAnchor(
            fromDate: $dueDate,
            months: $cycleMonths,
            anchorDay: $effectiveAnchor->day
        );
    }

    /**
     * Resolve the first date from which automatic vesting may review interest.
     *
     * For legacy accounts, the configured start date becomes the baseline.
     * A later account opening or principal withdrawal date moves the baseline
     * forward because a withdrawal resets the vesting cycle.
     */
    private function effectiveVestingAnchorDate(
        $account,
        Carbon $startDate
    ): Carbon {
        $candidates = [$startDate->copy()->startOfDay()];

        if (!empty($account->special_saving_account_opening_date)) {
            $candidates[] = Carbon::parse(
                $account->special_saving_account_opening_date,
                self::TIMEZONE
            )->startOfDay();
        }

        if (!empty($account->special_saving_account_last_withdrawal_date)) {
            $candidates[] = Carbon::parse(
                $account->special_saving_account_last_withdrawal_date,
                self::TIMEZONE
            )->startOfDay();
        }

        usort(
            $candidates,
            fn(Carbon $left, Carbon $right) => $left->timestamp <=> $right->timestamp
        );

        return end($candidates)->copy();
    }

    /**
     * Add calendar months without permanently drifting 29th/30th/31st anchors.
     */
    private function addMonthsPreservingAnchor(
        Carbon $fromDate,
        int $months,
        int $anchorDay
    ): Carbon {
        $targetMonth = $fromDate->copy()
            ->startOfMonth()
            ->addMonthsNoOverflow($months);

        return $targetMonth
            ->copy()
            ->day(min($anchorDay, $targetMonth->daysInMonth))
            ->startOfDay();
    }

    /**
     * Update the account's next vesting date.
     */
    private function advanceVestingDate(int $accountId, ?Carbon $nextVestingDate): void
    {
        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $accountId)
            ->update([
                'special_saving_account_next_free_withdrawal_date' => $nextVestingDate?->toDateString(),
                'special_saving_account_by' => null,
                'special_saving_account_ip' => self::SYSTEM_IP,
                'special_saving_account_transdate' => now(self::TIMEZONE),
            ]);
    }

    /**
     * Calculate a batch that spreads work over the remaining minute-runs in the
     * 04:00-07:59 Africa/Nairobi vesting window.
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
        $windowStart = $now->copy()->setTime(4, 0, 0);
        $windowEnd = $now->copy()->setTime(8, 0, 0);

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
     * Resolve the earliest date whose interest records may be reviewed for
     * automatic vesting.
     *
     * Precedence:
     * 1. Explicit --start-date option
     * 2. config('special_savings.vesting_start_date')
     */
    private function resolveVestingStartDate(): Carbon
    {
        $value = $this->option('start-date');

        if ($value === null || trim((string) $value) === '') {
            $value = config('special_savings.vesting_start_date');
        }

        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException(
                'Special-savings vesting start date is not configured. '
                . 'Set SPECIAL_SAVINGS_VESTING_START_DATE or use --start-date.'
            );
        }

        $value = trim((string) $value);
        $parsed = Carbon::createFromFormat('Y-m-d', $value, self::TIMEZONE);

        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            throw new RuntimeException(
                'The special-savings vesting start date must use YYYY-MM-DD format.'
            );
        }

        return $parsed->startOfDay();
    }

    /**
     * Read an optional positive integer command option.
     */
    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        if ($integer <= 0) {
            throw new RuntimeException("The --{$name} option must be a positive integer.");
        }

        return $integer;
    }

    /**
     * Normalize product policy values entered by different SACCO deployments.
     */
    private function normalizePolicy(string $policy): string
    {
        $normalized = strtoupper(trim($policy));
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized) ?: '';

        return trim($normalized, '_') ?: 'AFTER_WITHDRAWAL_CYCLE';
    }

    /**
     * Deterministic document number for audit and idempotency.
     */
    private function vestingDocumentNumber(int $accountId, Carbon $dueDate): string
    {
        return sprintf('SSV-AUTO-%d-%s', $accountId, $dueDate->format('Ymd'));
    }

    /**
     * Deterministic transaction reference for one account vesting cycle.
     */
    private function vestingReference(string $policy, Carbon $dueDate): string
    {
        return sprintf('AUTO-VESTING:%s:%s', $policy, $dueDate->format('Ymd'));
    }

    /**
     * Build a standard result payload.
     */
    private function result(
        $account,
        $product,
        string $status,
        ?string $reason,
        string $policy,
        ?Carbon $dueDate,
        float $vestingAmount,
        float $availableAfter,
        ?Carbon $nextVestingDate
    ): array {
        return [
            'status' => $status,
            'account_id' => (int) $account->special_saving_account_id,
            'account_number' => $account->special_saving_account_number
                ?? (string) $account->special_saving_account_id,
            'product_id' => $product
                ? (int) $product->special_saving_product_id
                : (int) $account->special_saving_account_product_id,
            'product_code' => $product->special_saving_product_code ?? 'N/A',
            'policy' => $policy,
            'due_date' => $dueDate?->toDateString(),
            'vesting_amount' => round($vestingAmount, 2),
            'available_after' => round($availableAfter, 2),
            'next_vesting_date' => $nextVestingDate?->toDateString(),
            'reason' => $reason,
        ];
    }

    /**
     * Build a simple result when the product is unavailable or account inactive.
     */
    private function basicResult(
        $account,
        $product,
        string $status,
        string $reason
    ): array {
        return $this->result(
            account: $account,
            product: $product,
            status: $status,
            reason: $reason,
            policy: $product
                ? $this->normalizePolicy(
                    (string) $product->special_saving_product_interest_vesting_policy
                )
                : 'N/A',
            dueDate: null,
            vestingAmount: 0.00,
            availableAfter: (float) ($account->special_saving_account_available_interest_balance ?? 0),
            nextVestingDate: null
        );
    }

    /**
 * Post one completed special-savings interest vesting transaction
 * to the General Ledger.
 *
 * Accounting entry:
 *   Dr Special Savings Accrued Interest Payable
 *   Cr Special Savings Available Interest Payable
 *
 * @throws RuntimeException
 */
private function postSpecialSavingsVestingToLedger(
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
            strtoupper(trim(
                (string) $transaction->special_saving_transaction_type
            )) !== 'INTEREST_VESTING'
        ) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'is not an interest vesting transaction.'
            );
        }

        if (
            strtoupper(trim(
                (string) $transaction->special_saving_transaction_deleted
            )) === 'Y'
            ||
            strtoupper(trim(
                (string) $transaction->special_saving_transaction_reversed
            )) === 'Y'
        ) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'is deleted or reversed and cannot be posted.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Already posted
        |--------------------------------------------------------------------------
        */
        if (
            strtoupper(trim(
                (string) $transaction
                    ->special_saving_transaction_ledger_posted
            )) === 'Y'
        ) {
            return (string) (
                $transaction->special_saving_transaction_ledger_ref
                ?? $transaction->special_saving_transaction_doc_no
            );
        }

        $vestingAmount = round(
            (float) $transaction
                ->special_saving_transaction_interest_amount,
            2
        );

        if ($vestingAmount <= 0) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'has an invalid vesting amount.'
            );
        }

        $documentNumber = trim(
            (string) $transaction
                ->special_saving_transaction_doc_no
        );

        if ($documentNumber === '') {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'does not have a document number.'
            );
        }

        $period = trim(
            (string) $transaction
                ->special_saving_transaction_period
        );

        if (!preg_match('/^\d{6}$/', $period)) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . "has an invalid accounting period [{$period}]."
            );
        }

        $postingDate =
            $transaction->special_saving_transaction_date;

        if (empty($postingDate)) {
            throw new RuntimeException(
                "Special-savings transaction {$specialSavingTransactionId} "
                . 'does not have a posting date.'
            );
        }

        $description = trim(
            (string) $transaction
                ->special_saving_transaction_description
        );

        if ($description === '') {
            $description =
                'Automatic special-savings interest vesting.';
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Resolve the two required ledger defaults
        |--------------------------------------------------------------------------
        */
        $requiredDefaults = [
            'accrued_payable' =>
                'special_savings_ledger_accrued_interest_payable_account',

            'available_payable' =>
                'special_savings_ledger_available_interest_payable_account',
        ];

        $resolvedAccounts = [];

        foreach ($requiredDefaults as $key => $defaultName) {
            $defaultRows = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->lockForUpdate()
                ->get();

            if ($defaultRows->count() !== 1) {
                Log::error(
                    'Required special-savings vesting ledger default is missing or duplicated.',
                    [
                        'default_name' => $defaultName,
                        'rows_found' => $defaultRows->count(),
                        'special_saving_transaction_id' =>
                            $specialSavingTransactionId,
                    ]
                );

                throw new RuntimeException(
                    "Required ledger default [{$defaultName}] is missing "
                    . 'or duplicated. No vesting ledger entry was posted.'
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
                    'Required special-savings vesting ledger default is invalid.',
                    [
                        'default_name' => $defaultName,
                        'default_value' => $configuredValue,
                        'special_saving_transaction_id' =>
                            $specialSavingTransactionId,
                    ]
                );

                throw new RuntimeException(
                    "Ledger default [{$defaultName}] does not contain "
                    . 'a valid sub-account ID.'
                );
            }

            $resolvedAccounts[$key] =
                (int) $configuredValue;
        }

        if (
            $resolvedAccounts['accrued_payable']
            === $resolvedAccounts['available_payable']
        ) {
            throw new RuntimeException(
                'The accrued-interest payable account and available-interest '
                . 'payable account cannot be the same sub-account.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Validate both liability sub-accounts
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
                $resolvedAccounts['accrued_payable'],
                $resolvedAccounts['available_payable'],
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

        $accruedPayableAccount = $accounts->get(
            $resolvedAccounts['accrued_payable']
        );

        $availablePayableAccount = $accounts->get(
            $resolvedAccounts['available_payable']
        );

        if (!$accruedPayableAccount) {
            throw new RuntimeException(
                'The configured accrued-interest payable sub-account '
                . 'does not exist or is deleted.'
            );
        }

        if (!$availablePayableAccount) {
            throw new RuntimeException(
                'The configured available-interest payable sub-account '
                . 'does not exist or is deleted.'
            );
        }

        foreach (
            [
                'accrued payable' => $accruedPayableAccount,
                'available payable' => $availablePayableAccount,
            ] as $label => $account
        ) {
            $mainCode = strtoupper(trim(
                (string) $account->main_account_code
            ));

            if (!preg_match('/^L\d{3}$/', $mainCode)) {
                throw new RuntimeException(
                    "The configured {$label} account is not under "
                    . 'a correctly classified L### liability main account.'
                );
            }

            $subCode = trim(
                (string) $account->sub_account_code
            );

            if (!preg_match('/^\d{3}$/', $subCode)) {
                throw new RuntimeException(
                    "The configured {$label} sub-account code must "
                    . 'contain exactly three numeric digits.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Prevent duplicate ledger posting
        |--------------------------------------------------------------------------
        */
        $existingLedgerRows = DB::table('sacco_accounts_trans')
            ->where(
                'accounts_trans_doc_no',
                $documentNumber
            )
            ->where(
                'accounts_trans_source',
                self::SYSTEM_SOURCE
            )
            ->lockForUpdate()
            ->get();

        $ledgerReference =
            'SSV-GL-' . $specialSavingTransactionId;

        if ($existingLedgerRows->isNotEmpty()) {
            $accruedDebit = round(
                (float) $existingLedgerRows
                    ->where(
                        'accounts_trans_sub_account',
                        $resolvedAccounts['accrued_payable']
                    )
                    ->sum('accounts_trans_debit'),
                2
            );

            $availableCredit = round(
                (float) $existingLedgerRows
                    ->where(
                        'accounts_trans_sub_account',
                        $resolvedAccounts['available_payable']
                    )
                    ->sum('accounts_trans_credit'),
                2
            );

            $totalDebit = round(
                (float) $existingLedgerRows
                    ->sum('accounts_trans_debit'),
                2
            );

            $totalCredit = round(
                (float) $existingLedgerRows
                    ->sum('accounts_trans_credit'),
                2
            );

            $isCorrectExistingLedger =
                $existingLedgerRows->count() === 2
                && abs($accruedDebit - $vestingAmount) < 0.005
                && abs($availableCredit - $vestingAmount) < 0.005
                && abs($totalDebit - $totalCredit) < 0.005;

            if (!$isCorrectExistingLedger) {
                throw new RuntimeException(
                    'A conflicting or incomplete General Ledger entry '
                    . "already exists for document [{$documentNumber}]."
                );
            }

            DB::table('sacco_special_saving_transactions')
                ->where(
                    'special_saving_transaction_id',
                    $specialSavingTransactionId
                )
                ->update([
                    'special_saving_transaction_sub_account_id' =>
                        $resolvedAccounts['available_payable'],

                    'special_saving_transaction_ledger_posted' =>
                        'Y',

                    'special_saving_transaction_ledger_ref' =>
                        $ledgerReference,
                ]);

            return $ledgerReference;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Create the balanced General Ledger journal
        |--------------------------------------------------------------------------
        */
        $ledgerRows = [
            /*
             * Debit accrued interest payable.
             *
             * This reduces the accrued-interest liability.
             */
            [
                'accounts_trans_sub_account' =>
                    $resolvedAccounts['accrued_payable'],

                'accounts_trans_period' => $period,

                'accounts_trans_debit' => $vestingAmount,
                'accounts_trans_credit' => 0,

                'accounts_trans_doc_no' =>
                    $documentNumber,

                'accounts_trans_decription' =>
                    $description
                    . ' | Debit: '
                    . $accruedPayableAccount->sub_account_name,

                'accounts_trans_dat_date' =>
                    $postingDate,

                'accounts_trans_user_id' => null,

                'accounts_trans_member_id' =>
                    $transaction
                        ->special_saving_transaction_member_id,

                'accounts_trans_ip' => self::SYSTEM_IP,
                'accounts_trans_source' => self::SYSTEM_SOURCE,
                'accounts_trans_app_name' => 'iSacco',
            ],

            /*
             * Credit available interest payable.
             *
             * This creates the member-withdrawable interest liability.
             */
            [
                'accounts_trans_sub_account' =>
                    $resolvedAccounts['available_payable'],

                'accounts_trans_period' => $period,

                'accounts_trans_debit' => 0,
                'accounts_trans_credit' => $vestingAmount,

                'accounts_trans_doc_no' =>
                    $documentNumber,

                'accounts_trans_decription' =>
                    $description
                    . ' | Credit: '
                    . $availablePayableAccount->sub_account_name,

                'accounts_trans_dat_date' =>
                    $postingDate,

                'accounts_trans_user_id' => null,

                'accounts_trans_member_id' =>
                    $transaction
                        ->special_saving_transaction_member_id,

                'accounts_trans_ip' => self::SYSTEM_IP,
                'accounts_trans_source' => self::SYSTEM_SOURCE,
                'accounts_trans_app_name' => 'iSacco',
            ],
        ];

        $totalDebit = round(
            array_sum(
                array_column(
                    $ledgerRows,
                    'accounts_trans_debit'
                )
            ),
            2
        );

        $totalCredit = round(
            array_sum(
                array_column(
                    $ledgerRows,
                    'accounts_trans_credit'
                )
            ),
            2
        );

        if (abs($totalDebit - $totalCredit) >= 0.005) {
            throw new RuntimeException(
                'Special-savings vesting ledger is not balanced. '
                . 'Debit: '
                . number_format($totalDebit, 2)
                . ', Credit: '
                . number_format($totalCredit, 2)
                . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Insert the authoritative General Ledger rows
        |--------------------------------------------------------------------------
        */
        DB::table('sacco_accounts_trans')
            ->insert($ledgerRows);

        /*
        |--------------------------------------------------------------------------
        | 7. Update the sub-account cached totals
        |--------------------------------------------------------------------------
        */
        $accruedUpdated = DB::table('sacco_sub_account')
            ->where(
                'sub_account_id',
                $resolvedAccounts['accrued_payable']
            )
            ->increment(
                'sub_account_debit',
                $vestingAmount
            );

        $availableUpdated = DB::table('sacco_sub_account')
            ->where(
                'sub_account_id',
                $resolvedAccounts['available_payable']
            )
            ->increment(
                'sub_account_credit',
                $vestingAmount
            );

        if (
            $accruedUpdated !== 1
            || $availableUpdated !== 1
        ) {
            throw new RuntimeException(
                'Failed to update the special-savings vesting '
                . 'sub-account totals.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Update main-account cached totals
        |--------------------------------------------------------------------------
        */
        $accruedMainUpdated = DB::table('sacco_main_account')
            ->where(
                'main_account_id',
                $accruedPayableAccount->main_account_id
            )
            ->increment(
                'main_account_debit',
                $vestingAmount
            );

        $availableMainUpdated = DB::table('sacco_main_account')
            ->where(
                'main_account_id',
                $availablePayableAccount->main_account_id
            )
            ->increment(
                'main_account_credit',
                $vestingAmount
            );

        if (
            $accruedMainUpdated !== 1
            || $availableMainUpdated !== 1
        ) {
            throw new RuntimeException(
                'Failed to update the special-savings vesting '
                . 'main-account totals.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Mark the subsidiary transaction as ledger-posted
        |--------------------------------------------------------------------------
        */
        $transactionUpdated =
            DB::table('sacco_special_saving_transactions')
                ->where(
                    'special_saving_transaction_id',
                    $specialSavingTransactionId
                )
                ->update([
                    /*
                     * Link vesting to the resulting available-interest
                     * liability account.
                     */
                    'special_saving_transaction_sub_account_id' =>
                        $resolvedAccounts['available_payable'],

                    'special_saving_transaction_ledger_posted' =>
                        'Y',

                    'special_saving_transaction_ledger_ref' =>
                        $ledgerReference,
                ]);

        if ($transactionUpdated !== 1) {
            throw new RuntimeException(
                'The vesting ledger was created, but the '
                . 'special-savings transaction could not be marked '
                . 'as ledger-posted.'
            );
        }

        Log::info(
            'Special-savings interest vesting posted to the General Ledger.',
            [
                'special_saving_transaction_id' =>
                    $specialSavingTransactionId,

                'member_id' =>
                    $transaction
                        ->special_saving_transaction_member_id,

                'document_number' =>
                    $documentNumber,

                'ledger_reference' =>
                    $ledgerReference,

                'vesting_amount' =>
                    $vestingAmount,

                'accrued_payable_sub_account_id' =>
                    $resolvedAccounts['accrued_payable'],

                'available_payable_sub_account_id' =>
                    $resolvedAccounts['available_payable'],

                'period' => $period,
                'posting_date' => $postingDate,
            ]
        );

        return $ledgerReference;
    }, 3);
}
}
