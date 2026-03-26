<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $migrationIp = 'MIGRATION';

    public function up(): void
    {
        DB::transaction(function () {
            $defaults = $this->repairLoanDefaults();
            $this->repairExistingLoanTypes($defaults);
            $this->seedLoanTypesIfEmpty($defaults);
        }, 5);
    }

    public function down(): void
    {
        // Intentionally non-destructive.
        // This migration repairs live account mappings and may seed live loan types.
    }

    private function repairLoanDefaults(): array
    {
        $principalMainId = $this->resolveOrCreateMainAccount(
            preferredName: 'LOANS & ADVANCES',
            preferredType: 'ASSETS - CURRENT',
            codePrefix: 'A',
            purpose: 'principal'
        );

        $interestMainId = $this->resolveOrCreateMainAccount(
            preferredName: 'INTEREST INCOME (LOANS)',
            preferredType: 'INCOME',
            codePrefix: 'I',
            purpose: 'interest'
        );

        $commissionMainId = $this->resolveOrCreateMainAccount(
            preferredName: 'FEES, COMMISSIONS & PENALTIES',
            preferredType: 'INCOME',
            codePrefix: 'I',
            purpose: 'commission'
        );

        $insuranceMainId = $this->resolveOrCreateMainAccount(
            preferredName: 'PAYABLES & ACCRUALS',
            preferredType: 'LIABILITIES - SHORT',
            codePrefix: 'L',
            purpose: 'insurance'
        );

        $defaultLoanAccountId = $this->resolveDefaultSubAccount(
            defaultName: 'default_loan_account',
            purpose: 'principal',
            preferredSubAccountName: 'LOANS TO MEMBERS - CONTROL',
            preferredMainAccountId: $principalMainId,
            fallbackKeywords: ['loan', 'advance', 'control']
        );

        $defaultInterestAccountId = $this->resolveDefaultSubAccount(
            defaultName: 'default_interest_account',
            purpose: 'interest',
            preferredSubAccountName: 'INTEREST ON LOANS - GENERAL',
            preferredMainAccountId: $interestMainId,
            fallbackKeywords: ['interest']
        );

        $defaultCommissionAccountId = $this->resolveDefaultSubAccount(
            defaultName: 'default_loan_commission_account',
            purpose: 'commission',
            preferredSubAccountName: 'LOAN COMMISSIONS',
            preferredMainAccountId: $commissionMainId,
            fallbackKeywords: ['commission', 'fee', 'processing', 'appraisal', 'penalty']
        );

        $defaultInsuranceAccountId = $this->resolveDefaultSubAccount(
            defaultName: 'default_insurance_account',
            purpose: 'insurance',
            preferredSubAccountName: 'LOAN INSURANCE PAYABLE',
            preferredMainAccountId: $insuranceMainId,
            fallbackKeywords: ['insurance', 'payable']
        );

        return [
            'principal_main_account_id' => $principalMainId,
            'interest_main_account_id' => $interestMainId,
            'commission_main_account_id' => $commissionMainId,
            'insurance_main_account_id' => $insuranceMainId,

            'default_loan_account' => $defaultLoanAccountId,
            'default_interest_account' => $defaultInterestAccountId,
            'default_loan_commission_account' => $defaultCommissionAccountId,
            'default_insurance_account' => $defaultInsuranceAccountId,

            'default_share_factor' => $this->getNumericDefault('loan_factor_or_shares', 3),
        ];
    }

    private function repairExistingLoanTypes(array $defaults): void
    {
        $loanTypes = DB::table('sacco_loan_types')
            ->where(function ($q) {
                $q->whereNull('loan_type_deleted')
                    ->orWhere('loan_type_deleted', '<>', 'Y');
            })
            ->get();

        foreach ($loanTypes as $loanType) {
            $desiredPrincipalId = $this->isValidLinkedSubAccount((int) ($loanType->loan_type_acount ?? 0), 'principal')
                ? (int) $loanType->loan_type_acount
                : $this->resolveLoanProductAccount('principal', $loanType, $defaults);

            $desiredInterestId = $this->isValidLinkedSubAccount((int) ($loanType->loan_type_int_account ?? 0), 'interest')
                ? (int) $loanType->loan_type_int_account
                : $this->resolveLoanProductAccount('interest', $loanType, $defaults);

            $desiredCommissionId = $this->isValidLinkedSubAccount((int) ($loanType->loan_type_comm_account ?? 0), 'commission')
                ? (int) $loanType->loan_type_comm_account
                : $this->resolveLoanProductAccount('commission', $loanType, $defaults);

            $updates = [];

            if ((int) ($loanType->loan_type_acount ?? 0) !== $desiredPrincipalId) {
                $updates['loan_type_acount'] = $desiredPrincipalId;
            }

            if ((int) ($loanType->loan_type_int_account ?? 0) !== $desiredInterestId) {
                $updates['loan_type_int_account'] = $desiredInterestId;
            }

            if ((int) ($loanType->loan_type_comm_account ?? 0) !== $desiredCommissionId) {
                $updates['loan_type_comm_account'] = $desiredCommissionId;
            }

            if (!empty($updates)) {
                $updates['loan_type_ip'] = $this->migrationIp;
                $updates['loan_type_transdate'] = now();

                DB::table('sacco_loan_types')
                    ->where('loan_type_id', $loanType->loan_type_id)
                    ->update($updates);
            }
        }
    }

    private function seedLoanTypesIfEmpty(array $defaults): void
    {
        $activeLoanTypesCount = DB::table('sacco_loan_types')
            ->where(function ($q) {
                $q->whereNull('loan_type_deleted')
                    ->orWhere('loan_type_deleted', '<>', 'Y');
            })
            ->count();

        if ($activeLoanTypesCount > 0) {
            return;
        }

        $shareFactor = max(1, (int) ($defaults['default_share_factor'] ?? 3));

        $seed = [
            [
                'name' => 'NORMAL LOAN',
                'code' => 'NORM',
                'interest' => 12,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 36,
                'guaranteable_percent' => 100,
                'max_amount' => 2000000,
                'qualification_period' => 6,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'EMERGENCY LOAN',
                'code' => 'EMER',
                'interest' => 12,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 12,
                'guaranteable_percent' => 100,
                'max_amount' => 200000,
                'qualification_period' => 3,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'SCHOOL FEES LOAN',
                'code' => 'SCHF',
                'interest' => 12,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 12,
                'guaranteable_percent' => 100,
                'max_amount' => 500000,
                'qualification_period' => 3,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'SHORT TERM LOAN',
                'code' => 'SHRT',
                'interest' => 10,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 6,
                'guaranteable_percent' => 100,
                'max_amount' => 100000,
                'qualification_period' => 3,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'DEVELOPMENT LOAN',
                'code' => 'DVLP',
                'interest' => 13,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 48,
                'guaranteable_percent' => 100,
                'max_amount' => 3000000,
                'qualification_period' => 6,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'SACCO ADVANCE',
                'code' => 'SADV',
                'interest' => 10,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 3,
                'guaranteable_percent' => 100,
                'max_amount' => 50000,
                'qualification_period' => 1,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'ASSET FINANCING LOAN',
                'code' => 'ASTF',
                'interest' => 14,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 48,
                'guaranteable_percent' => 100,
                'max_amount' => 5000000,
                'qualification_period' => 6,
                'max_qualification_period' => null,
            ],
            [
                'name' => 'CASH BAIL LOAN',
                'code' => 'CBAI',
                'interest' => 12,
                'interest_type' => 'REDUCING BALANCE',
                'duration' => 6,
                'guaranteable_percent' => 100,
                'max_amount' => 150000,
                'qualification_period' => 1,
                'max_qualification_period' => null,
            ],
        ];

        foreach ($seed as $item) {
            $principalId = $this->resolveLoanProductAccount(
                purpose: 'principal',
                loanType: (object) ['loan_type_name' => $item['name'], 'loan_type_code' => $item['code']],
                defaults: $defaults
            );

            $interestId = $this->resolveLoanProductAccount(
                purpose: 'interest',
                loanType: (object) ['loan_type_name' => $item['name'], 'loan_type_code' => $item['code']],
                defaults: $defaults
            );

            $commissionId = $this->resolveLoanProductAccount(
                purpose: 'commission',
                loanType: (object) ['loan_type_name' => $item['name'], 'loan_type_code' => $item['code']],
                defaults: $defaults
            );

            DB::table('sacco_loan_types')->insert([
                'loan_type_name' => $item['name'],
                'loan_type_interest' => $item['interest'],
                'loan_type_interest_type' => $item['interest_type'],
                'loan_type_duration' => $item['duration'],
                'loan_type_guaranteable_percent' => $item['guaranteable_percent'],
                'loan_type_code' => $item['code'],
                'loan_type_max_amount' => $item['max_amount'],
                'loan_type_qualification_period' => $item['qualification_period'],
                'loan_type_max_qualification_period' => $item['max_qualification_period'],

                'loan_type_crb_required' => 'N',
                'loan_type_crb_charge' => 0,
                'loan_type_crb_effect' => null,

                'loan_type_commission_required' => 'N',
                'loan_type_commission_type' => null,
                'loan_type_commission_value' => 0,
                'loan_type_commission_effect' => 'ADD_TO_LOAN',

                'loan_type_acount' => $principalId,
                'loan_type_int_account' => $interestId,
                'loan_type_comm_account' => $commissionId,

                'loan_type_insurable' => 'N',
                'loan_type_insurance_effect' => 'ADD_TO_LOAN',
                'loan_type_share_factor' => $shareFactor,
                'loan_type_instant_qualification' => 0,

                'loan_type_by' => null,
                'loan_type_transdate' => now(),
                'loan_type_ip' => $this->migrationIp,
                'loan_type_deleted' => 'N',
                'loan_type_active' => 1,
                'loan_type_deleted_by' => null,
                'loan_type_deleted_on' => null,
                'loan_type_deleted_ip' => null,
            ]);
        }
    }

    private function resolveLoanProductAccount(string $purpose, object $loanType, array $defaults): int
    {
        $name = strtoupper(trim((string) ($loanType->loan_type_name ?? '')));
        $code = strtoupper(trim((string) ($loanType->loan_type_code ?? '')));
        $haystack = $name . ' ' . $code;

        $map = [
            'NORMAL' => [
                'principal' => 'NORMAL LOANS ISSUED',
                'interest' => 'INTEREST ON NORMAL LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'EMERGENCY' => [
                'principal' => 'EMERGENCY LOANS ISSUED',
                'interest' => 'INTEREST ON EMERGENCY LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'SCHOOL' => [
                'principal' => 'SCHOOL FEES LOANS ISSUED',
                'interest' => 'INTEREST ON SCHOOL FEES LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'SHORT' => [
                'principal' => 'SHORT TERM LOANS ISSUED',
                'interest' => 'INTEREST ON SHORT TERM LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'INVESTMENT' => [
                'principal' => 'INVESTMENT LOANS ISSUED',
                'interest' => 'INTEREST ON INVESTMENT LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'DEVELOPMENT' => [
                'principal' => 'INVESTMENT LOANS ISSUED',
                'interest' => 'INTEREST ON INVESTMENT LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'ADVANCE' => [
                'principal' => 'SACCO ADVANCE ISSUED',
                'interest' => 'INTEREST ON SACCO ADVANCE',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'ASSET' => [
                'principal' => 'APPLIANCE / ASSET FINANCING LOANS',
                'interest' => 'INTEREST ON ASSET FINANCING',
                'commission' => 'COMMISSION - ASSET FINANCING',
            ],
            'APPLIANCE' => [
                'principal' => 'APPLIANCE / ASSET FINANCING LOANS',
                'interest' => 'INTEREST ON ASSET FINANCING',
                'commission' => 'COMMISSION - ASSET FINANCING',
            ],
            'INSURANCE' => [
                'principal' => 'INSURANCE LOANS ISSUED',
                'interest' => 'INTEREST ON INSURANCE LOANS',
                'commission' => 'LOAN COMMISSIONS',
            ],
            'CASH BAIL' => [
                'principal' => 'CASH BAIL LOANS ISSUED',
                'interest' => 'INTEREST ON CASH BAIL LOANS',
                'commission' => 'COMMISSION - CASH BAIL',
            ],
            'BAIL' => [
                'principal' => 'CASH BAIL LOANS ISSUED',
                'interest' => 'INTEREST ON CASH BAIL LOANS',
                'commission' => 'COMMISSION - CASH BAIL',
            ],
        ];

        $matchedKey = null;

        foreach (array_keys($map) as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $matchedKey = $keyword;
                break;
            }
        }

        if ($matchedKey === null) {
            return match ($purpose) {
                'principal' => (int) $defaults['default_loan_account'],
                'interest' => (int) $defaults['default_interest_account'],
                'commission' => (int) $defaults['default_loan_commission_account'],
                default => throw new \RuntimeException("Unsupported purpose [{$purpose}]"),
            };
        }

        $preferredSubName = $map[$matchedKey][$purpose];
        $preferredMainId = match ($purpose) {
            'principal' => (int) $defaults['principal_main_account_id'],
            'interest' => (int) $defaults['interest_main_account_id'],
            'commission' => (int) $defaults['commission_main_account_id'],
            default => throw new \RuntimeException("Unsupported purpose [{$purpose}]"),
        };

        return $this->resolveOrCreateSubAccount(
            preferredName: $preferredSubName,
            preferredMainAccountId: $preferredMainId,
            purpose: $purpose,
            keywords: preg_split('/\s+/', strtolower($matchedKey)) ?: []
        );
    }

    private function resolveDefaultSubAccount(
        string $defaultName,
        string $purpose,
        string $preferredSubAccountName,
        int $preferredMainAccountId,
        array $fallbackKeywords = []
    ): int {
        $existingDefault = DB::table('sacco_defaults')
            ->where('default_name', $defaultName)
            ->first();

        $existingId = $existingDefault ? (int) ($existingDefault->default_value ?? 0) : 0;

        if ($this->isValidLinkedSubAccount($existingId, $purpose)) {
            return $existingId;
        }

        $resolvedId = $this->resolveOrCreateSubAccount(
            preferredName: $preferredSubAccountName,
            preferredMainAccountId: $preferredMainAccountId,
            purpose: $purpose,
            keywords: $fallbackKeywords
        );

        if ($existingDefault) {
            DB::table('sacco_defaults')
                ->where('default_id', $existingDefault->default_id)
                ->update([
                    'default_value' => (string) $resolvedId,
                    'default_transdate' => now(),
                    'default_ip' => $this->migrationIp,
                ]);
        } else {
            DB::table('sacco_defaults')->insert([
                'default_name' => $defaultName,
                'default_value' => (string) $resolvedId,
                'default_transdate' => now(),
                'default_userid' => null,
                'default_ip' => $this->migrationIp,
            ]);
        }

        return $resolvedId;
    }

    private function resolveOrCreateMainAccount(
        string $preferredName,
        string $preferredType,
        string $codePrefix,
        string $purpose
    ): int {
        $normalizedPreferredName = $this->normalize($preferredName);

        $activeExact = DB::table('sacco_main_account')
            ->whereRaw('UPPER(TRIM(COALESCE(main_account_name, ""))) = ?', [$normalizedPreferredName])
            ->where(function ($q) {
                $q->whereNull('main_account_deleted')->orWhere('main_account_deleted', '<>', 'Y');
            })
            ->first();

        if ($activeExact) {
            return (int) $activeExact->main_account_id;
        }

        $bestActive = $this->findBestActiveMainAccount($purpose);
        if ($bestActive) {
            return (int) $bestActive->main_account_id;
        }

        $hasDeletedExact = DB::table('sacco_main_account')
            ->whereRaw('UPPER(TRIM(COALESCE(main_account_name, ""))) = ?', [$normalizedPreferredName])
            ->where('main_account_deleted', 'Y')
            ->exists();

        $finalName = $hasDeletedExact
            ? $this->generateFreshName('sacco_main_account', 'main_account_name', $preferredName)
            : $normalizedPreferredName;

        return (int) DB::table('sacco_main_account')->insertGetId([
            'main_account_name' => $finalName,
            'main_account_code' => $this->nextMainAccountCode($codePrefix),
            'main_account_type' => $preferredType,
            'main_account_debit' => 0,
            'main_account_credit' => 0,
            'main_account_user_id' => null,
            'main_account_transdate' => now(),
            'main_account_ip' => $this->migrationIp,
            'main_account_deleted' => 'N',
            'main_account_deleted_by' => null,
            'main_account_deleted_on' => null,
            'main_account_deleted_ip' => null,
        ]);
    }

    private function resolveOrCreateSubAccount(
        string $preferredName,
        int $preferredMainAccountId,
        string $purpose,
        array $keywords = []
    ): int {
        $normalizedPreferredName = $this->normalize($preferredName);

        $activeExactSameMain = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $preferredMainAccountId)
            ->whereRaw('UPPER(TRIM(COALESCE(sub_account_name, ""))) = ?', [$normalizedPreferredName])
            ->where(function ($q) {
                $q->whereNull('sub_account_deleted')->orWhere('sub_account_deleted', '<>', 'Y');
            })
            ->first();

        if ($activeExactSameMain) {
            return (int) $activeExactSameMain->sub_account_id;
        }

        $bestActive = $this->findBestActiveSubAccount($purpose, $preferredName, $keywords);
        if ($bestActive) {
            return (int) $bestActive->sub_account_id;
        }

        $hasDeletedExact = DB::table('sacco_sub_account')
            ->whereRaw('UPPER(TRIM(COALESCE(sub_account_name, ""))) = ?', [$normalizedPreferredName])
            ->where('sub_account_deleted', 'Y')
            ->exists();

        $finalName = $hasDeletedExact
            ? $this->generateFreshName('sacco_sub_account', 'sub_account_name', $preferredName)
            : $normalizedPreferredName;

        return (int) DB::table('sacco_sub_account')->insertGetId([
            'sub_account_name' => $finalName,
            'sub_account_code' => $this->nextSubAccountCode($preferredMainAccountId),
            'sub_account_main_account' => $preferredMainAccountId,
            'sub_account_debit' => 0,
            'sub_account_credit' => 0,
            'sub_account_user_id' => null,
            'sub_account_transdate' => now(),
            'sub_account_ip' => $this->migrationIp,
            'sub_account_deleted' => 'N',
            'sub_account_deleted_by' => null,
            'sub_account_deleted_on' => null,
            'sub_account_deleted_ip' => null,
        ]);
    }

    private function isValidLinkedSubAccount(int $subAccountId, string $purpose): bool
    {
        if ($subAccountId <= 0) {
            return false;
        }

        $row = $this->getLinkedSubAccountRow($subAccountId);

        if (!$row) {
            return false;
        }

        return match ($purpose) {
            'principal' => $this->isPrincipalAccountRow($row),
            'interest' => $this->isInterestAccountRow($row),
            'commission' => $this->isCommissionAccountRow($row),
            'insurance' => $this->isInsuranceAccountRow($row),
            default => false,
        };
    }

    private function getLinkedSubAccountRow(int $subAccountId): ?object
    {
        return DB::table('sacco_sub_account as s')
            ->leftJoin('sacco_main_account as m', 'm.main_account_id', '=', 's.sub_account_main_account')
            ->select(
                's.sub_account_id',
                's.sub_account_name',
                's.sub_account_code',
                's.sub_account_main_account',
                's.sub_account_deleted',
                'm.main_account_id',
                'm.main_account_name',
                'm.main_account_code',
                'm.main_account_type',
                'm.main_account_deleted'
            )
            ->where('s.sub_account_id', $subAccountId)
            ->first();
    }

    private function findBestActiveMainAccount(string $purpose): ?object
    {
        $rows = DB::table('sacco_main_account')
            ->where(function ($q) {
                $q->whereNull('main_account_deleted')->orWhere('main_account_deleted', '<>', 'Y');
            })
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($rows as $row) {
            $score = $this->scoreMainAccount($row, $purpose);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function findBestActiveSubAccount(string $purpose, string $preferredName, array $keywords = []): ?object
    {
        $rows = DB::table('sacco_sub_account as s')
            ->join('sacco_main_account as m', 'm.main_account_id', '=', 's.sub_account_main_account')
            ->select(
                's.sub_account_id',
                's.sub_account_name',
                's.sub_account_code',
                's.sub_account_main_account',
                'm.main_account_name',
                'm.main_account_code',
                'm.main_account_type'
            )
            ->where(function ($q) {
                $q->whereNull('s.sub_account_deleted')->orWhere('s.sub_account_deleted', '<>', 'Y');
            })
            ->where(function ($q) {
                $q->whereNull('m.main_account_deleted')->orWhere('m.main_account_deleted', '<>', 'Y');
            })
            ->get();

        $normalizedPreferredName = $this->normalize($preferredName);
        $best = null;
        $bestScore = 0;

        foreach ($rows as $row) {
            $valid = match ($purpose) {
                'principal' => $this->isPrincipalAccountRow($row),
                'interest' => $this->isInterestAccountRow($row),
                'commission' => $this->isCommissionAccountRow($row),
                'insurance' => $this->isInsuranceAccountRow($row),
                default => false,
            };

            if (!$valid) {
                continue;
            }

            $score = 10;

            if ($this->normalize((string) $row->sub_account_name) === $normalizedPreferredName) {
                $score += 100;
            }

            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim((string) $keyword));
                if ($keyword !== '' && str_contains(strtolower((string) $row->sub_account_name), $keyword)) {
                    $score += 10;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function scoreMainAccount(object $row, string $purpose): int
    {
        $name = strtoupper((string) ($row->main_account_name ?? ''));
        $code = strtoupper((string) ($row->main_account_code ?? ''));
        $type = strtoupper((string) ($row->main_account_type ?? ''));

        return match ($purpose) {
            'principal' => (
                (str_starts_with($code, 'A') ? 50 : 0) +
                ($this->containsAny($name, ['LOAN', 'ADVANCE', 'LEND']) ? 40 : 0) +
                ($this->containsAny($type, ['ASSET']) ? 20 : 0)
            ),
            'interest' => (
                (str_starts_with($code, 'I') ? 50 : 0) +
                ($this->containsAny($name, ['INTEREST']) ? 50 : 0) +
                ($this->containsAny($name, ['INCOME']) ? 20 : 0) +
                ($this->containsAny($type, ['INCOME']) ? 20 : 0)
            ),
            'commission' => (
                (str_starts_with($code, 'I') ? 50 : 0) +
                ($this->containsAny($name, ['COMMISSION', 'FEE', 'PENALT']) ? 50 : 0) +
                ($this->containsAny($name, ['INCOME']) ? 20 : 0) +
                ($this->containsAny($type, ['INCOME']) ? 20 : 0)
            ),
            'insurance' => (
                (str_starts_with($code, 'L') ? 50 : 0) +
                ($this->containsAny($name, ['PAYABLE', 'ACCRUAL', 'INSURANCE']) ? 50 : 0) +
                ($this->containsAny($type, ['LIABILIT']) ? 20 : 0)
            ),
            default => 0,
        };
    }

    private function isPrincipalAccountRow(object $row): bool
    {
        $mainName = strtoupper((string) ($row->main_account_name ?? ''));
        $mainCode = strtoupper((string) ($row->main_account_code ?? ''));
        $mainType = strtoupper((string) ($row->main_account_type ?? ''));
        $subName = strtoupper((string) ($row->sub_account_name ?? ''));

        $assetLike = str_starts_with($mainCode, 'A') || $this->containsAny($mainType, ['ASSET']);
        $loanLike = $this->containsAny($mainName . ' ' . $subName, ['LOAN', 'ADVANCE', 'ISSUED', 'CONTROL']);

        return $assetLike && $loanLike && !$this->containsAny($mainName . ' ' . $subName, ['INTEREST', 'COMMISSION', 'FEE', 'PENALT']);
    }

    private function isInterestAccountRow(object $row): bool
    {
        $mainName = strtoupper((string) ($row->main_account_name ?? ''));
        $mainCode = strtoupper((string) ($row->main_account_code ?? ''));
        $mainType = strtoupper((string) ($row->main_account_type ?? ''));
        $subName = strtoupper((string) ($row->sub_account_name ?? ''));

        $incomeLike = str_starts_with($mainCode, 'I') || $this->containsAny($mainType, ['INCOME']);
        $interestLike = $this->containsAny($mainName . ' ' . $subName, ['INTEREST']);

        return $incomeLike && $interestLike;
    }

    private function isCommissionAccountRow(object $row): bool
    {
        $mainName = strtoupper((string) ($row->main_account_name ?? ''));
        $mainCode = strtoupper((string) ($row->main_account_code ?? ''));
        $mainType = strtoupper((string) ($row->main_account_type ?? ''));
        $subName = strtoupper((string) ($row->sub_account_name ?? ''));

        $incomeLike = str_starts_with($mainCode, 'I') || $this->containsAny($mainType, ['INCOME']);
        $feeLike = $this->containsAny($mainName . ' ' . $subName, ['COMMISSION', 'FEE', 'PENALT', 'APPRAISAL', 'PROCESSING']);

        return $incomeLike && $feeLike;
    }

    private function isInsuranceAccountRow(object $row): bool
    {
        $mainName = strtoupper((string) ($row->main_account_name ?? ''));
        $mainCode = strtoupper((string) ($row->main_account_code ?? ''));
        $mainType = strtoupper((string) ($row->main_account_type ?? ''));
        $subName = strtoupper((string) ($row->sub_account_name ?? ''));

        $liabilityLike = str_starts_with($mainCode, 'L') || $this->containsAny($mainType, ['LIABILIT']);
        $insuranceLike = $this->containsAny($mainName . ' ' . $subName, ['INSURANCE', 'PAYABLE', 'ACCRUAL']);

        return $liabilityLike && $insuranceLike;
    }

    private function getNumericDefault(string $name, int $fallback): int
    {
        $row = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->first();

        if (!$row) {
            return $fallback;
        }

        $value = (int) preg_replace('/[^0-9\-]/', '', (string) ($row->default_value ?? ''));

        return $value > 0 ? $value : $fallback;
    }

    private function generateFreshName(string $table, string $column, string $baseName): string
    {
        $period = now()->format('Ym');
        $base = $this->normalize($baseName . ' - NEW ' . $period);

        $exists = DB::table($table)
            ->whereRaw("UPPER(TRIM(COALESCE({$column}, ''))) = ?", [$base])
            ->exists();

        if (!$exists) {
            return $base;
        }

        $counter = 2;

        do {
            $candidate = $this->normalize($baseName . ' - NEW ' . $period . ' - ' . $counter);

            $exists = DB::table($table)
                ->whereRaw("UPPER(TRIM(COALESCE({$column}, ''))) = ?", [$candidate])
                ->exists();

            if (!$exists) {
                return $candidate;
            }

            $counter++;
        } while (true);
    }

    private function nextMainAccountCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $starts = [
            'A' => 100,
            'L' => 200,
            'C' => 300,
            'I' => 400,
            'E' => 500,
        ];

        $max = ($starts[$prefix] ?? 100) - 1;

        $codes = DB::table('sacco_main_account')->pluck('main_account_code');

        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));

            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        do {
            $max++;
            $candidate = $prefix . str_pad((string) $max, 3, '0', STR_PAD_LEFT);
        } while (
            DB::table('sacco_main_account')
                ->whereRaw('UPPER(TRIM(COALESCE(main_account_code, ""))) = ?', [$candidate])
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

            if (preg_match('/^\d+$/', $code)) {
                $numeric = (int) $code;

                if ($numeric !== 999) {
                    $max = max($max, $numeric);
                }
            }
        }

        $next = max(1, $max + 1);

        do {
            $candidate = str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (
            DB::table('sacco_sub_account')
                ->where('sub_account_main_account', $mainAccountId)
                ->whereRaw('UPPER(TRIM(COALESCE(sub_account_code, ""))) = ?', [$candidate])
                ->exists()
        );

        return $candidate;
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }
};