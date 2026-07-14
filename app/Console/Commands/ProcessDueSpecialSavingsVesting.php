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
            $minBatch = max(1, (int) $this->option('min-batch'));
            $maxBatch = max($minBatch, (int) $this->option('max-batch'));
            $accountId = $this->positiveIntegerOption('account');
            $productId = $this->positiveIntegerOption('product');
            $dryRun = (bool) $this->option('dry-run');

            $dueAccounts = $this->loadDueAccounts(
                asOfDate: $asOfDate,
                accountId: $accountId,
                productId: $productId
            );

            $eligibleCount = $dueAccounts->count();

            if ($eligibleCount === 0) {
                $this->info(
                    'No special-savings accounts are due for interest vesting as at '
                    . $asOfDate->toDateString()
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
                'Due accounts: %d | Batch: %d | Date: %s | Mode: %s',
                $eligibleCount,
                $selected->count(),
                $asOfDate->toDateString(),
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
                            asOfDate: $asOfDate
                        )
                        : $this->processAccount(
                            accountId: (int) $candidate->account->special_saving_account_id,
                            asOfDate: $asOfDate
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
            ->map(function ($row) use ($asOfDate) {
                try {
                    $account = clone $row;
                    $product = clone $row;
                    $schedule = $this->determineVestingSchedule(
                        account: $account,
                        product: $product,
                        asOfDate: $asOfDate
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
    private function processAccount(int $accountId, Carbon $asOfDate): array
    {
        return DB::transaction(function () use ($accountId, $asOfDate) {
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
                asOfDate: $asOfDate
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
                dueDate: $dueDate
            );
            $nextVestingDate = $this->determineNextVestingDate(
                account: $account,
                product: $product,
                policy: $schedule['policy'],
                dueDate: $dueDate
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

            $vestingAmount = $this->calculateVestableInterest(
                account: $account,
                policy: $schedule['policy'],
                cycleStart: $cycleStart,
                cycleEnd: $dueDate
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

            Log::info('Special-savings interest vested automatically.', [
                'transaction_id' => $transactionId,
                'account_id' => $accountId,
                'member_id' => $account->special_saving_account_member_id,
                'product_id' => $product->special_saving_product_id,
                'policy' => $schedule['policy'],
                'cycle_start' => $cycleStart?->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'processing_date' => $asOfDate->toDateString(),
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
    private function previewAccount($account, $product, Carbon $asOfDate): array
    {
        $schedule = $this->determineVestingSchedule(
            account: $account,
            product: $product,
            asOfDate: $asOfDate
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
            dueDate: $dueDate
        );
        $vestingAmount = $this->calculateVestableInterest(
            account: $account,
            policy: $schedule['policy'],
            cycleStart: $cycleStart,
            cycleEnd: $dueDate
        );
        $nextVestingDate = $this->determineNextVestingDate(
            account: $account,
            product: $product,
            policy: $schedule['policy'],
            dueDate: $dueDate
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
    private function determineVestingSchedule($account, $product, Carbon $asOfDate): array
    {
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

        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            $dueDate = $account->special_saving_account_last_interest_date
                ? Carbon::parse($account->special_saving_account_last_interest_date, self::TIMEZONE)->startOfDay()
                : $asOfDate->copy();

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

            return [
                'policy' => $policy,
                'automatic' => true,
                'is_due' => (float) $account->special_saving_account_accrued_interest_balance > 0
                    && $dueDate->lte($asOfDate),
                'due_date' => $dueDate,
                'reason' => null,
            ];
        }

        $dueDate = $account->special_saving_account_next_free_withdrawal_date
            ? Carbon::parse(
                $account->special_saving_account_next_free_withdrawal_date,
                self::TIMEZONE
            )->startOfDay()
            : $this->initialCycleDueDate(
                account: $account,
                cycleMonths: $cycleMonths,
                asOfDate: $asOfDate
            );

        return [
            'policy' => $policy,
            'automatic' => true,
            'is_due' => $dueDate->lte($asOfDate),
            'due_date' => $dueDate,
            'reason' => null,
        ];
    }

    /**
     * Derive the first vesting date when an account does not already have one.
     */
    private function initialCycleDueDate($account, int $cycleMonths, Carbon $asOfDate): Carbon
    {
        $anchor = $this->vestingAnchorDate($account);

        if ($cycleMonths <= 0) {
            return $asOfDate->copy();
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
        Carbon $dueDate
    ): ?Carbon {
        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            return null;
        }

        $cycleMonths = max(
            0,
            (int) $product->special_saving_product_withdrawal_cycle_months
        );

        if ($cycleMonths <= 0) {
            return null;
        }

        $anchor = $this->vestingAnchorDate($account);
        $initialDueDate = $this->addMonthsPreservingAnchor(
            fromDate: $anchor,
            months: $cycleMonths,
            anchorDay: $anchor->day
        );

        if ($dueDate->equalTo($initialDueDate)) {
            return $anchor;
        }

        $previousMonth = $dueDate->copy()
            ->startOfMonth()
            ->subMonthsNoOverflow($cycleMonths);

        return $previousMonth
            ->copy()
            ->day(min($anchor->day, $previousMonth->daysInMonth))
            ->startOfDay();
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
        Carbon $cycleEnd
    ): float {
        $currentAccrued = round(
            max(0, (float) $account->special_saving_account_accrued_interest_balance),
            2
        );

        if ($currentAccrued <= 0) {
            return 0.00;
        }

        if (in_array($policy, ['IMMEDIATE', 'IMMEDIATE_ON_ACCRUAL', 'AVAILABLE_IMMEDIATELY'], true)) {
            return $currentAccrued;
        }

        if ($cycleStart === null) {
            return $currentAccrued;
        }

        $isInitialCycle = $cycleStart->equalTo($this->vestingAnchorDate($account));

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $account->special_saving_account_id)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->when(
                $isInitialCycle,
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
        Carbon $dueDate
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

        return $this->addMonthsPreservingAnchor(
            fromDate: $dueDate,
            months: $cycleMonths,
            anchorDay: $this->vestingAnchorDate($account)->day
        );
    }

    /**
     * A paid principal withdrawal resets the cycle; otherwise opening date anchors it.
     */
    private function vestingAnchorDate($account): Carbon
    {
        $anchorDate = $account->special_saving_account_last_withdrawal_date
            ?: $account->special_saving_account_opening_date;

        if (!$anchorDate) {
            throw new RuntimeException('The account has no opening or withdrawal date for vesting.');
        }

        return Carbon::parse($anchorDate, self::TIMEZONE)->startOfDay();
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
}
