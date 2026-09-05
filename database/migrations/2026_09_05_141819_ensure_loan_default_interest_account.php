<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Required configuration
    |--------------------------------------------------------------------------
    */

    private const DEFAULT_NAME =
        'loan_type_default_int_account';

    private const SUB_ACCOUNT_NAME =
        'INTEREST ON DEFAULTED LOANS';

    /*
     * Only used if we cannot derive an INCOME parent from the
     * existing loan_type_int_account configurations.
     */
    private const FALLBACK_MAIN_ACCOUNT_NAME =
        'LOAN INTEREST INCOME';

    private const SYSTEM_USER_ID = 0;

    private const SYSTEM_IP = '127.0.0.1';


    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Required tables
        |--------------------------------------------------------------------------
        |
        | Fail the migration rather than silently marking it as completed when
        | one of the core accounting tables is unavailable.
        |
        */

        foreach ([
            'sacco_defaults',
            'sacco_main_account',
            'sacco_sub_account',
            'sacco_loan_types',
        ] as $table) {

            if (!Schema::hasTable($table)) {
                throw new \RuntimeException(
                    "Cannot provision default-interest account: "
                    . "required table {$table} does not exist."
                );
            }
        }


        DB::transaction(function (): void {

            /*
            |--------------------------------------------------------------------------
            | STEP 1
            | Existing default already points to the correct account
            |--------------------------------------------------------------------------
            */

            $configuredAccountId = DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    self::DEFAULT_NAME
                )
                ->value('default_value');


            if (
                is_numeric($configuredAccountId)
                && (int) $configuredAccountId > 0
            ) {

                $configuredAccount = DB::table(
                    'sacco_sub_account as s'
                )
                    ->join(
                        'sacco_main_account as m',
                        's.sub_account_main_account',
                        '=',
                        'm.main_account_id'
                    )
                    ->where(
                        's.sub_account_id',
                        (int) $configuredAccountId
                    )
                    ->whereRaw(
                        "UPPER(TRIM(s.sub_account_name)) = ?",
                        [
                            self::SUB_ACCOUNT_NAME
                        ]
                    )
                    ->whereRaw(
                        "COALESCE(
                            s.sub_account_deleted,
                            'N'
                        ) <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(
                            m.main_account_deleted,
                            'N'
                        ) <> 'Y'"
                    )
                    ->whereRaw(
                        "UPPER(
                            TRIM(m.main_account_type)
                        ) = 'INCOME'"
                    )
                    ->whereRaw(
                        "UPPER(
                            TRIM(m.main_account_code)
                        ) LIKE 'I%'"
                    )
                    ->first();


                /*
                 * Everything is already correctly configured.
                 */
                if ($configuredAccount) {
                    return;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | STEP 2
            | Search for INTEREST ON DEFAULTED LOANS
            |--------------------------------------------------------------------------
            |
            | Search by NAME before creating anything.
            |
            | This prevents:
            |
            | - duplicate account names;
            | - a second account being created after a previous migration;
            | - duplicate configuration across SACCO databases.
            |
            */

            $namedAccounts = DB::table(
                'sacco_sub_account as s'
            )
                ->join(
                    'sacco_main_account as m',
                    's.sub_account_main_account',
                    '=',
                    'm.main_account_id'
                )
                ->whereRaw(
                    "UPPER(TRIM(s.sub_account_name)) = ?",
                    [
                        self::SUB_ACCOUNT_NAME
                    ]
                )
                ->select(
                    's.sub_account_id',
                    's.sub_account_code',
                    's.sub_account_main_account',
                    's.sub_account_deleted',

                    'm.main_account_code',
                    'm.main_account_type',
                    'm.main_account_deleted'
                )
                ->get();


            /*
             * Only accounts under a proper INCOME / Ixxx main account
             * are valid for default-interest income.
             */

            $validNamedAccounts = $namedAccounts
                ->filter(function ($row) {

                    $mainType = strtoupper(
                        trim(
                            (string) $row
                                ->main_account_type
                        )
                    );

                    $mainCode = strtoupper(
                        trim(
                            (string) $row
                                ->main_account_code
                        )
                    );

                    $mainDeleted = strtoupper(
                        trim(
                            (string) (
                                $row
                                    ->main_account_deleted
                                ?? 'N'
                            )
                        )
                    );


                    return $mainType === 'INCOME'
                        && str_starts_with(
                            $mainCode,
                            'I'
                        )
                        && $mainDeleted !== 'Y';
                })
                ->values();


            /*
             * Multiple matching accounts would be ambiguous.
             *
             * Never guess which financial account should be used.
             */

            if ($validNamedAccounts->count() > 1) {

                throw new \RuntimeException(
                    'Cannot provision default-interest account: '
                    . 'multiple INCOME sub-accounts named '
                    . self::SUB_ACCOUNT_NAME
                    . ' already exist.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Reuse existing matching account
            |--------------------------------------------------------------------------
            */

            if ($validNamedAccounts->count() === 1) {

                $existing =
                    $validNamedAccounts->first();

                $subAccountId =
                    (int) $existing
                        ->sub_account_id;

                $mainAccountId =
                    (int) $existing
                        ->sub_account_main_account;

                $currentCode = trim(
                    (string) $existing
                        ->sub_account_code
                );


                /*
                 * If the matching account exists but was deleted,
                 * reactivate it instead of creating a duplicate.
                 */

                $subDeleted = strtoupper(
                    trim(
                        (string) (
                            $existing
                                ->sub_account_deleted
                            ?? 'N'
                        )
                    )
                );


                if ($subDeleted === 'Y') {

                    /*
                     * Validate the historical sub-account code.
                     */

                    $codeIsValid =
                        preg_match(
                            '/^\d{3}$/',
                            $currentCode
                        ) === 1
                        && (int) $currentCode >= 1
                        && (int) $currentCode <= 999;


                    $codeInUse = false;


                    /*
                     * A deleted account's previous code might already
                     * have been reused by another active account.
                     */

                    if ($codeIsValid) {

                        $codeInUse =
                            DB::table(
                                'sacco_sub_account'
                            )
                                ->where(
                                    'sub_account_main_account',
                                    $mainAccountId
                                )
                                ->where(
                                    'sub_account_code',
                                    $currentCode
                                )
                                ->where(
                                    'sub_account_id',
                                    '<>',
                                    $subAccountId
                                )
                                ->whereRaw(
                                    "COALESCE(
                                        sub_account_deleted,
                                        'N'
                                    ) <> 'Y'"
                                )
                                ->exists();
                    }


                    /*
                     * If the old code cannot safely be reused,
                     * derive the next available code using the
                     * normal SACCO 001-999 sequence.
                     */

                    if (
                        !$codeIsValid
                        || $codeInUse
                    ) {

                        $currentCode =
                            $this
                                ->nextSubAccountCode(
                                    $mainAccountId
                                );
                    }


                    DB::table(
                        'sacco_sub_account'
                    )
                        ->where(
                            'sub_account_id',
                            $subAccountId
                        )
                        ->update([
                            'sub_account_name' =>
                                self::SUB_ACCOUNT_NAME,

                            'sub_account_code' =>
                                $currentCode,

                            'sub_account_deleted' =>
                                'N',

                            'sub_account_user_id' =>
                                self::SYSTEM_USER_ID,

                            'sub_account_ip' =>
                                self::SYSTEM_IP,
                        ]);
                } else {

                    /*
                     * Ensure exact canonical uppercase naming,
                     * but do not alter an already-valid live code.
                     */

                    DB::table(
                        'sacco_sub_account'
                    )
                        ->where(
                            'sub_account_id',
                            $subAccountId
                        )
                        ->update([
                            'sub_account_name' =>
                                self::SUB_ACCOUNT_NAME,
                        ]);
                }


                /*
                 * Save/recover the defaults pointer.
                 */

                $this->upsertDefault(
                    $subAccountId
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Same name exists but under wrong account class
            |--------------------------------------------------------------------------
            |
            | Do NOT create a duplicate name.
            | Do NOT automatically move an existing financial account.
            |
            */

            if ($namedAccounts->isNotEmpty()) {

                throw new \RuntimeException(
                    'Cannot provision default-interest account: '
                    . self::SUB_ACCOUNT_NAME
                    . ' already exists, but it is not under '
                    . 'a valid INCOME (Ixxx) main account.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | STEP 3
            | Determine the appropriate Income main account
            |--------------------------------------------------------------------------
            |
            | First preference:
            |
            | Use the same INCOME main-account grouping already being used by
            | normal loan-interest accounts configured through:
            |
            | sacco_loan_types.loan_type_int_account
            |
            | If several Income parents are being used, select the one used by
            | the greatest number of active loan types.
            |
            | If tied:
            |
            | use the lowest main_account_code.
            |
            */

            $mainAccount = DB::table(
                'sacco_loan_types as lt'
            )
                ->join(
                    'sacco_sub_account as s',
                    'lt.loan_type_int_account',
                    '=',
                    's.sub_account_id'
                )
                ->join(
                    'sacco_main_account as m',
                    's.sub_account_main_account',
                    '=',
                    'm.main_account_id'
                )
                ->whereRaw(
                    "COALESCE(
                        lt.loan_type_deleted,
                        'N'
                    ) <> 'Y'"
                )
                ->whereRaw(
                    "COALESCE(
                        s.sub_account_deleted,
                        'N'
                    ) <> 'Y'"
                )
                ->whereRaw(
                    "COALESCE(
                        m.main_account_deleted,
                        'N'
                    ) <> 'Y'"
                )
                ->whereRaw(
                    "UPPER(
                        TRIM(m.main_account_type)
                    ) = 'INCOME'"
                )
                ->whereRaw(
                    "UPPER(
                        TRIM(m.main_account_code)
                    ) LIKE 'I%'"
                )
                ->select(
                    'm.main_account_id',
                    'm.main_account_name',
                    'm.main_account_code',

                    DB::raw(
                        'COUNT(*) AS usage_count'
                    )
                )
                ->groupBy(
                    'm.main_account_id',
                    'm.main_account_name',
                    'm.main_account_code'
                )
                ->orderByDesc(
                    'usage_count'
                )
                ->orderBy(
                    'm.main_account_code'
                )
                ->first();


            if ($mainAccount) {

                $mainAccountId =
                    (int) $mainAccount
                        ->main_account_id;

            } else {

                /*
                 * No usable existing loan-interest parent exists.
                 *
                 * Create/reuse a dedicated Income main account.
                 */

                $mainAccountId =
                    $this
                        ->resolveOrCreateFallbackIncomeMainAccount();
            }


            /*
            |--------------------------------------------------------------------------
            | STEP 4
            | Generate sub-account code
            |--------------------------------------------------------------------------
            |
            | Existing application rule:
            |
            | 001
            | 002
            | 003
            | ...
            | 999
            |
            | First available number under THIS main account.
            |
            */

            $subAccountCode =
                $this->nextSubAccountCode(
                    $mainAccountId
                );


            /*
            |--------------------------------------------------------------------------
            | STEP 5
            | Create INTEREST ON DEFAULTED LOANS
            |--------------------------------------------------------------------------
            */

            $subAccountId =
                DB::table(
                    'sacco_sub_account'
                )
                    ->insertGetId([
                        'sub_account_name' =>
                            self::SUB_ACCOUNT_NAME,

                        'sub_account_code' =>
                            $subAccountCode,

                        'sub_account_main_account' =>
                            $mainAccountId,

                        'sub_account_debit' =>
                            0,

                        'sub_account_credit' =>
                            0,

                        'sub_account_deleted' =>
                            'N',

                        'sub_account_user_id' =>
                            self::SYSTEM_USER_ID,

                        'sub_account_ip' =>
                            self::SYSTEM_IP,
                    ]);


            /*
            |--------------------------------------------------------------------------
            | STEP 6
            | Save account ID to SACCO defaults
            |--------------------------------------------------------------------------
            |
            | default_name:
            |
            | loan_type_default_int_account
            |
            | default_value:
            |
            | sacco_sub_account.sub_account_id
            |
            */

            $this->upsertDefault(
                (int) $subAccountId
            );
        }, 3);
    }


    public function down(): void
    {
        if (
            !Schema::hasTable(
                'sacco_defaults'
            )
        ) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Conservative rollback
        |--------------------------------------------------------------------------
        |
        | Remove ONLY the configuration pointer.
        |
        | Do NOT delete:
        |
        | INTEREST ON DEFAULTED LOANS
        |
        | because financial transactions may already have been posted against
        | that account by the time somebody runs a rollback.
        |
        | Re-running the migration later will simply rediscover and reuse the
        | existing account.
        |
        */

        DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                self::DEFAULT_NAME
            )
            ->delete();
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve/create fallback Income parent
    |--------------------------------------------------------------------------
    */

    private function resolveOrCreateFallbackIncomeMainAccount(): int
    {
        $rows = DB::table(
            'sacco_main_account'
        )
            ->whereRaw(
                "UPPER(TRIM(main_account_name)) = ?",
                [
                    self::FALLBACK_MAIN_ACCOUNT_NAME
                ]
            )
            ->get();


        if ($rows->count() > 1) {

            throw new \RuntimeException(
                'Cannot provision default-interest account: '
                . 'multiple main accounts named '
                . self::FALLBACK_MAIN_ACCOUNT_NAME
                . ' already exist.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Existing fallback parent
        |--------------------------------------------------------------------------
        */

        if ($rows->count() === 1) {

            $main = $rows->first();


            if (
                strtoupper(
                    trim(
                        (string) $main
                            ->main_account_type
                    )
                ) !== 'INCOME'
            ) {

                throw new \RuntimeException(
                    self::FALLBACK_MAIN_ACCOUNT_NAME
                    . ' already exists but is not '
                    . 'an INCOME main account.'
                );
            }


            $mainAccountId =
                (int) $main
                    ->main_account_id;

            $mainCode =
                strtoupper(
                    trim(
                        (string) $main
                            ->main_account_code
                    )
                );

            $deleted =
                strtoupper(
                    trim(
                        (string) (
                            $main
                                ->main_account_deleted
                            ?? 'N'
                        )
                    )
                ) === 'Y';


            /*
             * Income code must be:
             *
             * I400 ... I999
             */

            $codeIsValid =
                preg_match(
                    '/^I\d{3}$/',
                    $mainCode
                ) === 1
                && (int) substr(
                    $mainCode,
                    1
                ) >= 400
                && (int) substr(
                    $mainCode,
                    1
                ) <= 999;


            $codeInUse = false;


            if ($codeIsValid) {

                $codeInUse =
                    DB::table(
                        'sacco_main_account'
                    )
                        ->where(
                            'main_account_code',
                            $mainCode
                        )
                        ->where(
                            'main_account_id',
                            '<>',
                            $mainAccountId
                        )
                        ->whereRaw(
                            "COALESCE(
                                main_account_deleted,
                                'N'
                            ) <> 'Y'"
                        )
                        ->exists();
            }


            /*
             * Historical/deleted account may have lost its usable code.
             */

            if (
                !$codeIsValid
                || $codeInUse
            ) {

                $mainCode =
                    $this
                        ->nextIncomeMainAccountCode();
            }


            /*
             * Reactivate/repair only when necessary.
             */

            if (
                $deleted
                || !$codeIsValid
                || $codeInUse
            ) {

                DB::table(
                    'sacco_main_account'
                )
                    ->where(
                        'main_account_id',
                        $mainAccountId
                    )
                    ->update([
                        'main_account_name' =>
                            self::FALLBACK_MAIN_ACCOUNT_NAME,

                        'main_account_code' =>
                            $mainCode,

                        'main_account_type' =>
                            'INCOME',

                        'main_account_deleted' =>
                            'N',

                        'main_account_user_id' =>
                            self::SYSTEM_USER_ID,

                        'main_account_ip' =>
                            self::SYSTEM_IP,
                    ]);
            }


            return $mainAccountId;
        }


        /*
        |--------------------------------------------------------------------------
        | Create new fallback Income main account
        |--------------------------------------------------------------------------
        */

        $mainAccountCode =
            $this
                ->nextIncomeMainAccountCode();


        return (int) DB::table(
            'sacco_main_account'
        )
            ->insertGetId([
                'main_account_name' =>
                    self::FALLBACK_MAIN_ACCOUNT_NAME,

                'main_account_code' =>
                    $mainAccountCode,

                'main_account_type' =>
                    'INCOME',

                'main_account_debit' =>
                    0,

                'main_account_credit' =>
                    0,

                'main_account_deleted' =>
                    'N',

                'main_account_user_id' =>
                    self::SYSTEM_USER_ID,

                'main_account_ip' =>
                    self::SYSTEM_IP,
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Generate next INCOME main account code
    |--------------------------------------------------------------------------
    |
    | Mirrors your existing HomeController:
    |
    | INCOME:
    |
    | prefix = I
    | base   = 400
    |
    | Finds FIRST unused code.
    |
    */

    private function nextIncomeMainAccountCode(): string
    {
        $existingCodes =
            DB::table(
                'sacco_main_account'
            )
                ->whereRaw(
                    "COALESCE(
                        main_account_deleted,
                        'N'
                    ) <> 'Y'"
                )
                ->where(
                    'main_account_code',
                    'LIKE',
                    'I%'
                )
                ->pluck(
                    'main_account_code'
                )
                ->map(function ($code) {

                    return (int) substr(
                        strtoupper(
                            trim(
                                (string) $code
                            )
                        ),
                        1
                    );
                })
                ->filter(function ($number) {

                    return $number >= 400
                        && $number <= 999;
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();


        $used =
            array_flip(
                $existingCodes
            );


        for (
            $i = 400;
            $i <= 999;
            $i++
        ) {

            if (!isset($used[$i])) {

                return 'I'
                    . str_pad(
                        (string) $i,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );
            }
        }


        throw new \RuntimeException(
            'No available INCOME main account codes '
            . 'remain between I400 and I999.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate next sub-account code
    |--------------------------------------------------------------------------
    |
    | Mirrors your existing HomeController:
    |
    | 001 - 999
    |
    | FIRST unused code within the selected main account.
    |
    */

    private function nextSubAccountCode(
        int $mainAccountId
    ): string {

        $usedCodes =
            DB::table(
                'sacco_sub_account'
            )
                ->where(
                    'sub_account_main_account',
                    $mainAccountId
                )
                ->whereRaw(
                    "COALESCE(
                        sub_account_deleted,
                        'N'
                    ) <> 'Y'"
                )
                ->pluck(
                    'sub_account_code'
                )
                ->map(function ($code) {

                    return (int) $code;
                })
                ->filter(function ($code) {

                    return $code >= 1
                        && $code <= 999;
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();


        $used =
            array_flip(
                $usedCodes
            );


        for (
            $i = 1;
            $i <= 999;
            $i++
        ) {

            if (!isset($used[$i])) {

                return str_pad(
                    (string) $i,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
            }
        }


        throw new \RuntimeException(
            "No available sub-account codes remain "
            . "under main account {$mainAccountId}. "
            . 'Maximum allowed is 999.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert/update SACCO default
    |--------------------------------------------------------------------------
    */

    private function upsertDefault(
        int $subAccountId
    ): void {

        $exists =
            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    self::DEFAULT_NAME
                )
                ->exists();


        $values = [
            /*
             * Stores:
             *
             * sacco_sub_account.sub_account_id
             */
            'default_value' =>
                $subAccountId,

            'default_transdate' =>
                now(),

            'default_userid' =>
                self::SYSTEM_USER_ID,

            'default_ip' =>
                self::SYSTEM_IP,
        ];


        if ($exists) {

            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    self::DEFAULT_NAME
                )
                ->update(
                    $values
                );

            return;
        }


        DB::table(
            'sacco_defaults'
        )
            ->insert(
                array_merge(
                    [
                        'default_name' =>
                            self::DEFAULT_NAME,
                    ],
                    $values
                )
            );
    }
};