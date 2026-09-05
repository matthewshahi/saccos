<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HashMissingMemberPhoneNumbers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'members:hash-missing-phone-numbers
                            {--chunk=500 : Number of members processed per batch}';

    /**
     * The console command description.
     */
    protected $description =
        'Generate SHA-256 phone hashes for members with valid Kenyan mobile numbers and missing phone hashes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        /*
        |--------------------------------------------------------------------------
        | Protect against unreasonable chunk sizes
        |--------------------------------------------------------------------------
        */

        if ($chunkSize < 50) {
            $chunkSize = 50;
        }

        if ($chunkSize > 5000) {
            $chunkSize = 5000;
        }

        $checked = 0;
        $hashed = 0;
        $invalid = 0;
        $skipped = 0;

        /*
        |--------------------------------------------------------------------------
        | Find only members whose hash is currently missing
        |--------------------------------------------------------------------------
        |
        | We deliberately do NOT modify member_phone_no.
        |
        | The existing number is normalized only in memory so that different
        | Kenyan representations produce exactly the same SHA-256 hash.
        |--------------------------------------------------------------------------
        */

        DB::table('sacco_members')
            ->select(
                'member_id',
                'member_phone_no'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('member_phone_hash')
                    ->orWhere('member_phone_hash', '');
            })
            ->whereNotNull('member_phone_no')
            ->where('member_phone_no', '<>', '')
            ->chunkById(
                $chunkSize,
                function ($members) use (
                    &$checked,
                    &$hashed,
                    &$invalid,
                    &$skipped
                ) {
                    foreach ($members as $member) {
                        $checked++;

                        /*
                         * Convert whatever valid Kenyan representation exists
                         * into the exact value used for M-PESA hashing:
                         *
                         * 254722400737
                         */
                        $normalizedPhone =
                            $this->normalizeKenyanMobileForHash(
                                $member->member_phone_no
                            );

                        /*
                         * Invalid numbers remain NULL.
                         *
                         * We do not guess, truncate or try to repair numbers
                         * that cannot be confidently identified as valid
                         * Kenyan mobile numbers.
                         */
                        if ($normalizedPhone === null) {
                            $invalid++;
                            continue;
                        }

                        /*
                         * IMPORTANT:
                         *
                         * Do NOT use:
                         *
                         * Hash::make(...)
                         * password_hash(...)
                         * bcrypt(...)
                         *
                         * M-PESA matching requires deterministic SHA-256.
                         */
                        $phoneHash = hash(
                            'sha256',
                            $normalizedPhone
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Race-safe update
                        |--------------------------------------------------------------------------
                        |
                        | A member could theoretically be edited while this command
                        | is running.
                        |
                        | Therefore we update ONLY if the hash is still missing at
                        | the exact moment of update.
                        |--------------------------------------------------------------------------
                        */

                        $updated = DB::table('sacco_members')
                            ->where(
                                'member_id',
                                $member->member_id
                            )
                            ->where(function ($query) {
                                $query
                                    ->whereNull('member_phone_hash')
                                    ->orWhere(
                                        'member_phone_hash',
                                        ''
                                    );
                            })
                            ->update([
                                'member_phone_hash' =>
                                    $phoneHash,
                            ]);

                        if ($updated === 1) {
                            $hashed++;
                        } else {
                            $skipped++;
                        }
                    }
                },
                'member_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Safe command summary
        |--------------------------------------------------------------------------
        |
        | Do not print member phone numbers or hashes to logs/output.
        |--------------------------------------------------------------------------
        */

        $this->info(
            "Member phone hashing completed. "
            . "Checked: {$checked}; "
            . "Hashed: {$hashed}; "
            . "Invalid: {$invalid}; "
            . "Skipped: {$skipped}."
        );

        return self::SUCCESS;
    }

    /**
     * Convert a Kenyan mobile number into the exact representation
     * expected before SHA-256 hashing.
     *
     * Successful result:
     *
     * 254722400737
     *
     * NOT:
     *
     * +254722400737
     */
    private function normalizeKenyanMobileForHash(
        ?string $phone
    ): ?string {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        /*
         * Never silently remove alphabetic characters.
         *
         * Examples such as:
         *
         * 0722400737ABC
         * 254722400737_DUP
         *
         * must remain invalid.
         */
        if (preg_match('/[a-z]/i', $phone)) {
            return null;
        }

        /*
         * Remove harmless formatting:
         *
         * spaces
         * +
         * -
         * brackets
         * etc.
         *
         * Examples:
         *
         * 722 400 737
         * +254 722 400 737
         */
        $digits = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (
            $digits === null
            || $digits === ''
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 00254 format
        |--------------------------------------------------------------------------
        |
        | 00254722400737
        |
        | becomes:
        |
        | 254722400737
        |--------------------------------------------------------------------------
        */

        if (str_starts_with($digits, '00254')) {
            $digits = substr(
                $digits,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Kenyan country code plus accidental local trunk zero
        |--------------------------------------------------------------------------
        |
        | 2540722400737
        |
        | becomes:
        |
        | 254722400737
        |--------------------------------------------------------------------------
        */

        if (str_starts_with($digits, '2540')) {
            $digits =
                '254'
                . substr(
                    $digits,
                    4
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Local Kenyan format
        |--------------------------------------------------------------------------
        |
        | 0722400737
        | becomes
        | 254722400737
        |
        | 0112345678
        | becomes
        | 254112345678
        |--------------------------------------------------------------------------
        */
        elseif (
            strlen($digits) === 10
            && str_starts_with(
                $digits,
                '0'
            )
        ) {
            $digits =
                '254'
                . substr(
                    $digits,
                    1
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Kenyan number without 0 or 254
        |--------------------------------------------------------------------------
        |
        | 722400737
        | becomes
        | 254722400737
        |
        | 112345678
        | becomes
        | 254112345678
        |--------------------------------------------------------------------------
        */
        elseif (
            strlen($digits) === 9
            && in_array(
                substr($digits, 0, 1),
                ['7', '1'],
                true
            )
        ) {
            $digits =
                '254'
                . $digits;
        }

        /*
        |--------------------------------------------------------------------------
        | Final validation
        |--------------------------------------------------------------------------
        |
        | Only Kenyan mobile numbers are accepted:
        |
        | 2547XXXXXXXX
        | 2541XXXXXXXX
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/^254(?:7\d{8}|1\d{8})$/',
                $digits
            )
        ) {
            return null;
        }

        return $digits;
    }
}