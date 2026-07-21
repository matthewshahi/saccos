<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SYSTEM_MARKER = 'SPECIAL_SAVINGS_LEDGER_SETUP';

    /*
     * Prefer a high, normally unused three-digit area, but never assume it is free.
     * The allocator checks the relevant series and moves to the next available code.
     */
    private const PREFERRED_MAIN_NUMBER = 880;
    private const PREFERRED_SUB_NUMBER = 880;

    public function up(): void
    {
        $this->assertRequiredSchema();

        DB::transaction(function (): void {
            /*
             * Reuse a correctly classified existing parent account.
             * If none exists, create a dedicated parent using the next free:
             *
             *   E### for expense
             *   L### for liability
             */
            $expenseMainAccountId = $this->findOrCreateMainAccount(
                existingNames: [
                    'SPECIAL SAVINGS INTEREST EXPENSES',
                    'FINANCE COSTS',
                ],
                createName: 'SPECIAL SAVINGS INTEREST EXPENSES',
                requiredPrefix: 'E',
                requiredTypeKeyword: 'EXPENSE',
                createType: 'EXPENSE'
            );

            $liabilityMainAccountId = $this->findOrCreateMainAccount(
                existingNames: [
                    'SPECIAL SAVINGS INTEREST PAYABLES',
                    'MEMBER DEPOSITS & SAVINGS',
                ],
                createName: 'SPECIAL SAVINGS INTEREST PAYABLES',
                requiredPrefix: 'L',
                requiredTypeKeyword: 'LIABIL',
                createType: 'LIABILITIES - SHORT'
            );

            /*
             * Sub-account codes are always exactly three numeric characters.
             * They are unique within their parent main account, which makes the
             * complete account code MAIN/SUB unique.
             */
            $interestExpenseAccountId = $this->findOrCreateSubAccount(
                mainAccountId: $expenseMainAccountId,
                accountName: 'SPECIAL SAVINGS - INTEREST EXPENSE',
                preferredNumber: self::PREFERRED_SUB_NUMBER
            );

            $accruedInterestPayableAccountId = $this->findOrCreateSubAccount(
                mainAccountId: $liabilityMainAccountId,
                accountName: 'SPECIAL SAVINGS - ACCRUED INTEREST PAYABLE',
                preferredNumber: self::PREFERRED_SUB_NUMBER
            );

            $availableInterestPayableAccountId = $this->findOrCreateSubAccount(
                mainAccountId: $liabilityMainAccountId,
                accountName: 'SPECIAL SAVINGS - AVAILABLE INTEREST PAYABLE',
                preferredNumber: self::PREFERRED_SUB_NUMBER + 1
            );

            $this->setDefault(
                'special_savings_ledger_interest_expense_account',
                $interestExpenseAccountId
            );

            $this->setDefault(
                'special_savings_ledger_accrued_interest_payable_account',
                $accruedInterestPayableAccountId
            );

            $this->setDefault(
                'special_savings_ledger_available_interest_payable_account',
                $availableInterestPayableAccountId
            );
        }, 3);
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * These accounts may contain posted financial transactions after the
         * migration runs. Deleting or unmapping them during rollback would
         * damage the accounting audit trail.
         */
    }

    private function assertRequiredSchema(): void
    {
        $requirements = [
            'sacco_main_account' => [
                'main_account_id',
                'main_account_name',
                'main_account_code',
                'main_account_type',
                'main_account_debit',
                'main_account_credit',
                'main_account_user_id',
                'main_account_transdate',
                'main_account_ip',
                'main_account_deleted',
                'main_account_deleted_by',
                'main_account_deleted_on',
                'main_account_deleted_ip',
            ],
            'sacco_sub_account' => [
                'sub_account_id',
                'sub_account_name',
                'sub_account_code',
                'sub_account_main_account',
                'sub_account_debit',
                'sub_account_credit',
                'sub_account_user_id',
                'sub_account_transdate',
                'sub_account_ip',
                'sub_account_deleted',
                'sub_account_deleted_by',
                'sub_account_deleted_on',
                'sub_account_deleted_ip',
            ],
            'sacco_defaults' => [
                'default_id',
                'default_name',
                'default_value',
                'default_transdate',
                'default_userid',
                'default_ip',
            ],
        ];

        foreach ($requirements as $table => $columns) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Required table [{$table}] does not exist.");
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "Required column [{$table}.{$column}] does not exist."
                    );
                }
            }
        }
    }

    /**
     * Reuse a correctly classified parent main account or create one.
     */
    private function findOrCreateMainAccount(
        array $existingNames,
        string $createName,
        string $requiredPrefix,
        string $requiredTypeKeyword,
        string $createType
    ): int {
        $requiredPrefix = strtoupper(trim($requiredPrefix));

        /*
         * First prefer an active account.
         */
        foreach ($existingNames as $name) {
            $matches = $this->mainAccountsByName($name, activeOnly: true);

            if ($matches->count() > 1) {
                throw new RuntimeException(
                    "Multiple active main accounts named [{$name}] exist."
                );
            }

            if ($matches->count() === 1) {
                $account = $matches->first();

                $this->assertMainAccountClassification(
                    account: $account,
                    requiredPrefix: $requiredPrefix,
                    requiredTypeKeyword: $requiredTypeKeyword
                );

                return (int) $account->main_account_id;
            }
        }

        /*
         * If no active match exists, reactivate one exact deleted match instead
         * of creating a duplicate.
         */
        foreach ($existingNames as $name) {
            $matches = $this->mainAccountsByName($name, deletedOnly: true);

            if ($matches->count() > 1) {
                throw new RuntimeException(
                    "Multiple deleted main accounts named [{$name}] exist. "
                    . 'Automatic reactivation is ambiguous.'
                );
            }

            if ($matches->count() === 1) {
                $account = $matches->first();

                $this->assertMainAccountClassification(
                    account: $account,
                    requiredPrefix: $requiredPrefix,
                    requiredTypeKeyword: $requiredTypeKeyword
                );

                DB::table('sacco_main_account')
                    ->where('main_account_id', $account->main_account_id)
                    ->update([
                        'main_account_deleted' => 'N',
                        'main_account_deleted_by' => null,
                        'main_account_deleted_on' => null,
                        'main_account_deleted_ip' => null,
                        'main_account_transdate' => now(),
                        'main_account_ip' => self::SYSTEM_MARKER,
                    ]);

                return (int) $account->main_account_id;
            }
        }

        $mainAccountCode = $this->nextFreeMainAccountCode(
            prefix: $requiredPrefix,
            preferredNumber: self::PREFERRED_MAIN_NUMBER
        );

        return (int) DB::table('sacco_main_account')->insertGetId([
            'main_account_name' => $createName,
            'main_account_code' => $mainAccountCode,
            'main_account_type' => $createType,
            'main_account_debit' => 0,
            'main_account_credit' => 0,
            'main_account_user_id' => null,
            'main_account_transdate' => now(),
            'main_account_ip' => self::SYSTEM_MARKER,
            'main_account_deleted' => 'N',
            'main_account_deleted_by' => null,
            'main_account_deleted_on' => null,
            'main_account_deleted_ip' => null,
        ]);
    }

    private function mainAccountsByName(
        string $name,
        bool $activeOnly = false,
        bool $deletedOnly = false
    ) {
        $query = DB::table('sacco_main_account')
            ->whereRaw(
                'UPPER(TRIM(main_account_name)) = ?',
                [strtoupper(trim($name))]
            )
            ->lockForUpdate();

        if ($activeOnly) {
            $query->whereRaw("UPPER(COALESCE(main_account_deleted, 'N')) <> 'Y'");
        }

        if ($deletedOnly) {
            $query->whereRaw("UPPER(COALESCE(main_account_deleted, 'N')) = 'Y'");
        }

        return $query->get();
    }

    /**
     * Verify that an existing main account is correctly classified.
     */
    private function assertMainAccountClassification(
        object $account,
        string $requiredPrefix,
        string $requiredTypeKeyword
    ): void {
        $code = strtoupper(trim((string) $account->main_account_code));
        $type = strtoupper(trim((string) $account->main_account_type));

        if (!preg_match(
            '/^' . preg_quote($requiredPrefix, '/') . '\d{3}$/',
            $code
        )) {
            throw new RuntimeException(
                "Main account [{$account->main_account_name}] has invalid code "
                . "[{$code}]. Expected {$requiredPrefix} followed by exactly "
                . 'three numeric digits.'
            );
        }

        if (!str_contains($type, strtoupper($requiredTypeKeyword))) {
            throw new RuntimeException(
                "Main account [{$account->main_account_name}] is classified as "
                . "[{$account->main_account_type}], but the required classification "
                . "is [{$requiredTypeKeyword}]."
            );
        }

        $duplicateCodeExists = DB::table('sacco_main_account')
            ->whereRaw('UPPER(TRIM(main_account_code)) = ?', [$code])
            ->where('main_account_id', '<>', $account->main_account_id)
            ->exists();

        if ($duplicateCodeExists) {
            throw new RuntimeException(
                "Main account code [{$code}] is not unique."
            );
        }
    }

    /**
     * Return the next free PREFIX### main-account code.
     *
     * It searches from the preferred number to 999, then wraps to 100.
     * Deleted codes are treated as occupied and are never silently reused.
     */
    private function nextFreeMainAccountCode(
        string $prefix,
        int $preferredNumber
    ): string {
        foreach ($this->threeDigitNumbers($preferredNumber, 100) as $number) {
            $candidate = strtoupper($prefix) . sprintf('%03d', $number);

            $exists = DB::table('sacco_main_account')
                ->whereRaw(
                    'UPPER(TRIM(main_account_code)) = ?',
                    [$candidate]
                )
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "No free three-digit main-account code remains in the "
            . strtoupper($prefix)
            . ' series.'
        );
    }

    /**
     * Reuse, reactivate or create a sub-account.
     */
    private function findOrCreateSubAccount(
        int $mainAccountId,
        string $accountName,
        int $preferredNumber
    ): int {
        $matches = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $mainAccountId)
            ->whereRaw(
                'UPPER(TRIM(sub_account_name)) = ?',
                [strtoupper(trim($accountName))]
            )
            ->lockForUpdate()
            ->get();

        $activeMatches = $matches
            ->filter(
                fn (object $row): bool =>
                    strtoupper(trim((string) ($row->sub_account_deleted ?? 'N'))) !== 'Y'
            )
            ->values();

        if ($activeMatches->count() > 1) {
            throw new RuntimeException(
                "Multiple active sub-accounts named [{$accountName}] exist "
                . "under main account ID [{$mainAccountId}]."
            );
        }

        if ($activeMatches->count() === 1) {
            $account = $activeMatches->first();
            $this->assertSubAccountCode($account, $mainAccountId);

            return (int) $account->sub_account_id;
        }

        if ($matches->count() > 1) {
            throw new RuntimeException(
                "Multiple deleted sub-accounts named [{$accountName}] exist "
                . "under main account ID [{$mainAccountId}]."
            );
        }

        if ($matches->count() === 1) {
            $account = $matches->first();
            $this->assertSubAccountCode($account, $mainAccountId);

            DB::table('sacco_sub_account')
                ->where('sub_account_id', $account->sub_account_id)
                ->update([
                    'sub_account_deleted' => 'N',
                    'sub_account_deleted_by' => null,
                    'sub_account_deleted_on' => null,
                    'sub_account_deleted_ip' => null,
                    'sub_account_transdate' => now(),
                    'sub_account_ip' => self::SYSTEM_MARKER,
                ]);

            return (int) $account->sub_account_id;
        }

        $subAccountCode = $this->nextFreeSubAccountCode(
            mainAccountId: $mainAccountId,
            preferredNumber: $preferredNumber
        );

        return (int) DB::table('sacco_sub_account')->insertGetId([
            'sub_account_name' => $accountName,
            'sub_account_code' => $subAccountCode,
            'sub_account_main_account' => $mainAccountId,
            'sub_account_debit' => 0,
            'sub_account_credit' => 0,
            'sub_account_user_id' => null,
            'sub_account_transdate' => now(),
            'sub_account_ip' => self::SYSTEM_MARKER,
            'sub_account_deleted' => 'N',
            'sub_account_deleted_by' => null,
            'sub_account_deleted_on' => null,
            'sub_account_deleted_ip' => null,
        ]);
    }

    /**
     * Existing sub-account codes must be exactly three digits and unique inside
     * the parent account. The full code MAIN/SUB is therefore unique.
     */
    private function assertSubAccountCode(
        object $account,
        int $mainAccountId
    ): void {
        $code = trim((string) $account->sub_account_code);

        if (!preg_match('/^\d{3}$/', $code)) {
            throw new RuntimeException(
                "Sub-account [{$account->sub_account_name}] has invalid code "
                . "[{$code}]. Expected exactly three numeric digits."
            );
        }

        $duplicateExists = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $mainAccountId)
            ->whereRaw('TRIM(sub_account_code) = ?', [$code])
            ->where('sub_account_id', '<>', $account->sub_account_id)
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException(
                "Sub-account code [{$code}] is duplicated under main account "
                . "ID [{$mainAccountId}]."
            );
        }
    }

    /**
     * Return the next free three-digit numeric code within one parent account.
     *
     * Deleted codes are treated as occupied and are not silently recycled.
     */
    private function nextFreeSubAccountCode(
        int $mainAccountId,
        int $preferredNumber
    ): string {
        foreach ($this->threeDigitNumbers($preferredNumber, 1) as $number) {
            $candidate = sprintf('%03d', $number);

            $exists = DB::table('sacco_sub_account')
                ->where('sub_account_main_account', $mainAccountId)
                ->whereRaw('TRIM(sub_account_code) = ?', [$candidate])
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "No free three-digit sub-account code remains under main account "
            . "ID [{$mainAccountId}]."
        );
    }

    /**
     * Generate a preferred-to-999 sequence, then wrap to the configured minimum.
     */
    private function threeDigitNumbers(
        int $preferredNumber,
        int $minimum
    ): array {
        $preferredNumber = max($minimum, min(999, $preferredNumber));

        $numbers = range($preferredNumber, 999);

        if ($preferredNumber > $minimum) {
            $numbers = array_merge(
                $numbers,
                range($minimum, $preferredNumber - 1)
            );
        }

        return $numbers;
    }

    /**
     * Store one canonical default mapping.
     */
    private function setDefault(
        string $defaultName,
        int $subAccountId
    ): void {
        $rows = DB::table('sacco_defaults')
            ->where('default_name', $defaultName)
            ->orderBy('default_id')
            ->lockForUpdate()
            ->get();

        $values = [
            'default_value' => (string) $subAccountId,
            'default_transdate' => now(),
            'default_userid' => null,
            'default_ip' => self::SYSTEM_MARKER,
        ];

        if ($rows->isEmpty()) {
            DB::table('sacco_defaults')->insert(array_merge(
                ['default_name' => $defaultName],
                $values
            ));

            return;
        }

        $canonicalId = (int) $rows->first()->default_id;

        DB::table('sacco_defaults')
            ->where('default_id', $canonicalId)
            ->update($values);

        $duplicateIds = $rows
            ->skip(1)
            ->pluck('default_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($duplicateIds !== []) {
            DB::table('sacco_defaults')
                ->whereIn('default_id', $duplicateIds)
                ->delete();
        }
    }
};
