<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $migrationIp = 'MIGRATION';

    public function up(): void
    {
        DB::transaction(function () {
            /*
            |--------------------------------------------------------------------------
            | 1. Ensure required main accounts exist
            |--------------------------------------------------------------------------
            */
            $mainAccounts = [
                'PAYABLES & ACCRUALS' => [
                    'type'   => 'LIABILITIES - SHORT',
                    'prefix' => 'L',
                ],
                'INTEREST INCOME (LOANS)' => [
                    'type'   => 'INCOME',
                    'prefix' => 'I',
                ],
                'FEES, COMMISSIONS & PENALTIES' => [
                    'type'   => 'INCOME',
                    'prefix' => 'I',
                ],
            ];

            $mainAccountIds = [];

            foreach ($mainAccounts as $name => $config) {
                $mainAccountIds[$name] = $this->getOrCreateMainAccount(
                    $name,
                    $config['type'],
                    $config['prefix']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Ensure required sub accounts exist
            |--------------------------------------------------------------------------
            |
            | Mapping agreed earlier:
            | - Income accounts under FEES, COMMISSIONS & PENALTIES
            | - Liability / clearing accounts under PAYABLES & ACCRUALS
            |
            */
            $deductionAccountMap = [
                'CRB' => [
                    'sub_account_name'  => 'CRB CHECK FEES',
                    'main_account_name' => 'FEES, COMMISSIONS & PENALTIES',
                ],
                'PROCESSING' => [
                    'sub_account_name'  => 'LOAN PROCESSING FEES',
                    'main_account_name' => 'FEES, COMMISSIONS & PENALTIES',
                ],
                'ADMIN' => [
                    'sub_account_name'  => 'LOAN ADMIN FEES',
                    'main_account_name' => 'FEES, COMMISSIONS & PENALTIES',
                ],
                'INSURANCE' => [
                    'sub_account_name'  => 'LOAN INSURANCE PAYABLE',
                    'main_account_name' => 'PAYABLES & ACCRUALS',
                ],
                'COMMISSION' => [
                    'sub_account_name'  => 'LOAN COMMISSIONS',
                    'main_account_name' => 'FEES, COMMISSIONS & PENALTIES',
                ],
                'LEGAL' => [
                    'sub_account_name'  => 'LEGAL FEES PAYABLE',
                    'main_account_name' => 'PAYABLES & ACCRUALS',
                ],
                'VALUATION' => [
                    'sub_account_name'  => 'VALUATION FEES PAYABLE',
                    'main_account_name' => 'PAYABLES & ACCRUALS',
                ],
                'APPRAISAL' => [
                    'sub_account_name'  => 'LOAN APPRAISAL FEES',
                    'main_account_name' => 'FEES, COMMISSIONS & PENALTIES',
                ],
                'TOPUP_OFFSET' => [
                    'sub_account_name'  => 'TOP UP OFFSET CLEARING',
                    'main_account_name' => 'PAYABLES & ACCRUALS',
                ],
                'OTHER' => [
                    'sub_account_name'  => 'OTHER LOAN DEDUCTIONS PAYABLE',
                    'main_account_name' => 'PAYABLES & ACCRUALS',
                ],
            ];

            $subAccountIdsByDeductionCode = [];

            foreach ($deductionAccountMap as $deductionCode => $config) {
                $parentMainAccountId = $mainAccountIds[$config['main_account_name']];

                $subAccountIdsByDeductionCode[$deductionCode] = $this->getOrCreateSubAccount(
                    $config['sub_account_name'],
                    $parentMainAccountId
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Update deduction types with the resolved sub account ids
            |--------------------------------------------------------------------------
            */
            foreach ($subAccountIdsByDeductionCode as $deductionCode => $subAccountId) {
                DB::table('sacco_loan_deductions_types')
                    ->whereRaw(
                        'UPPER(TRIM(COALESCE(deduction_type_code, ""))) = ?',
                        [$this->normalize($deductionCode)]
                    )
                    ->update([
                        'deduction_type_account' => $subAccountId,
                    ]);
            }
        }, 5);
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Intentionally left blank
        |--------------------------------------------------------------------------
        |
        | This migration updates live accounting mappings and may be referenced
        | by real transactions after deployment. A destructive rollback here
        | would be unsafe in production.
        |
        */
    }

    private function getOrCreateMainAccount(string $name, string $type, string $prefix): int
    {
        $normalizedName = $this->normalize($name);

        $existing = DB::table('sacco_main_account')
            ->whereRaw(
                'UPPER(TRIM(COALESCE(main_account_name, ""))) = ?',
                [$normalizedName]
            )
            ->first();

        if ($existing) {
            $updates = [];

            if (($existing->main_account_deleted ?? 'N') === 'Y') {
                $updates['main_account_deleted'] = 'N';
                $updates['main_account_deleted_by'] = null;
                $updates['main_account_deleted_on'] = null;
                $updates['main_account_deleted_ip'] = null;
            }

            if (empty($existing->main_account_type)) {
                $updates['main_account_type'] = $type;
            }

            if (empty($existing->main_account_code)) {
                $updates['main_account_code'] = $this->nextMainAccountCode($prefix);
            }

            if (!empty($updates)) {
                DB::table('sacco_main_account')
                    ->where('main_account_id', $existing->main_account_id)
                    ->update($updates);
            }

            return (int) $existing->main_account_id;
        }

        $code = $this->nextMainAccountCode($prefix);

        return (int) DB::table('sacco_main_account')->insertGetId([
            'main_account_name'       => $normalizedName,
            'main_account_code'       => $code,
            'main_account_type'       => $type,
            'main_account_debit'      => 0,
            'main_account_credit'     => 0,
            'main_account_user_id'    => null,
            'main_account_ip'         => $this->migrationIp,
            'main_account_deleted'    => 'N',
            'main_account_deleted_by' => null,
            'main_account_deleted_on' => null,
            'main_account_deleted_ip' => null,
        ]);
    }

    private function getOrCreateSubAccount(string $name, int $mainAccountId): int
    {
        $normalizedName = $this->normalize($name);

        $existingUnderParent = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $mainAccountId)
            ->whereRaw(
                'UPPER(TRIM(COALESCE(sub_account_name, ""))) = ?',
                [$normalizedName]
            )
            ->first();

        if ($existingUnderParent) {
            $updates = [];

            if (($existingUnderParent->sub_account_deleted ?? 'N') === 'Y') {
                $updates['sub_account_deleted'] = 'N';
                $updates['sub_account_deleted_by'] = null;
                $updates['sub_account_deleted_on'] = null;
                $updates['sub_account_deleted_ip'] = null;
            }

            if (empty($existingUnderParent->sub_account_code)) {
                $updates['sub_account_code'] = $this->nextSubAccountCode($mainAccountId);
            }

            if (!empty($updates)) {
                DB::table('sacco_sub_account')
                    ->where('sub_account_id', $existingUnderParent->sub_account_id)
                    ->update($updates);
            }

            return (int) $existingUnderParent->sub_account_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Live-safe duplicate guard
        |--------------------------------------------------------------------------
        |
        | If the same sub account name already exists under a different parent,
        | stop instead of silently creating a conflicting setup.
        |
        */
        $sameNameElsewhere = DB::table('sacco_sub_account')
            ->whereRaw(
                'UPPER(TRIM(COALESCE(sub_account_name, ""))) = ?',
                [$normalizedName]
            )
            ->first();

        if ($sameNameElsewhere) {
            throw new RuntimeException(
                "Sub account [{$normalizedName}] already exists under main account ID "
                . $sameNameElsewhere->sub_account_main_account
                . ". Migration stopped to avoid incorrect cross-parent duplication."
            );
        }

        $code = $this->nextSubAccountCode($mainAccountId);

        return (int) DB::table('sacco_sub_account')->insertGetId([
            'sub_account_name'           => $normalizedName,
            'sub_account_code'           => $code,
            'sub_account_main_account'   => $mainAccountId,
            'sub_account_debit'          => 0,
            'sub_account_credit'         => 0,
            'sub_account_user_id'        => null,
            'sub_account_ip'             => $this->migrationIp,
            'sub_account_deleted'        => 'N',
            'sub_account_deleted_by'     => null,
            'sub_account_deleted_on'     => null,
            'sub_account_deleted_ip'     => null,
        ]);
    }

    private function nextMainAccountCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));

        $defaultStarts = [
            'A' => 100,
            'L' => 200,
            'C' => 300,
            'I' => 400,
            'E' => 500,
        ];

        $max = ($defaultStarts[$prefix] ?? 100) - 1;

        $codes = DB::table('sacco_main_account')->pluck('main_account_code');

        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));

            if (!preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $matches)) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        do {
            $max++;
            $candidate = $prefix . str_pad((string) $max, 3, '0', STR_PAD_LEFT);
        } while (
            DB::table('sacco_main_account')
                ->whereRaw(
                    'UPPER(TRIM(COALESCE(main_account_code, ""))) = ?',
                    [$candidate]
                )
                ->exists()
        );

        return $candidate;
    }

    private function nextSubAccountCode(int $mainAccountId): string
    {
        $max = 0;

        $codes = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $mainAccountId)
            ->pluck('sub_account_code');

        foreach ($codes as $code) {
            $code = trim((string) $code);

            if (!preg_match('/^\d+$/', $code)) {
                continue;
            }

            $numeric = (int) $code;

            // Ignore reserved/special bucket codes like 999
            if ($numeric === 999) {
                continue;
            }

            $max = max($max, $numeric);
        }

        $next = max(1, $max + 1);

        do {
            $candidate = str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (
            DB::table('sacco_sub_account')
                ->where('sub_account_main_account', $mainAccountId)
                ->whereRaw(
                    'UPPER(TRIM(COALESCE(sub_account_code, ""))) = ?',
                    [$candidate]
                )
                ->exists()
        );

        return $candidate;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtoupper((string) $value);
    }
};