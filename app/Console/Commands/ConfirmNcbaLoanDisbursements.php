<?php

namespace App\Console\Commands;

use App\Services\NcbaOpenBankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConfirmNcbaLoanDisbursements extends Command
{
    protected $signature = 'ncba:confirm-loan-disbursements {--limit=10}';

    protected $description = 'Confirm NCBA loan disbursements already sent to bank';

    public function handle(NcbaOpenBankingService $ncba): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::table('sacco_loan_disbursements')
            ->whereIn('status', ['SENT_TO_BANK', 'BANK_PENDING'])
            ->whereNotNull('transaction_ref')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No sent/pending NCBA disbursements to confirm.');
            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            try {
                $result = $ncba->queryTransactionStatus($row->transaction_ref);
                $response = $result['response'] ?? [];

                $errorCode = $response['ErrorCode']
                    ?? $response['errorCode']
                    ?? null;

                $errorMessage = $response['ErrorMessage']
                    ?? $response['errorMessage']
                    ?? null;

                $coreReference = $response['CoreReference']
                    ?? $response['coreReference']
                    ?? null;

                $success = (string) $errorCode === '000';

                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'status' => $success ? 'BANK_SUCCESS' : 'BANK_PENDING',
                        'bank_status_code' => $errorCode,
                        'bank_status_message' => $errorMessage,
                        'core_reference' => $coreReference ?: $row->core_reference,
                        'bank_reference' => $coreReference ?: $row->bank_reference,
                        'confirmed_at' => $success ? now() : null,
                        'response_payload' => json_encode($response),
                        'last_error' => $success ? null : json_encode($response),
                        'updated_at' => now(),
                    ]);

                if ($success) {
                    $this->queueSuccessfulDisbursementNotifications(
                        $row,
                        $coreReference
                    );
                }

                if ($success) {
                    $this->info("CONFIRMED: {$row->transaction_ref} - {$coreReference}");
                } else {
                    $this->warn("PENDING/NOT SUCCESS: {$row->transaction_ref} - {$errorCode} {$errorMessage}");
                }
            } catch (Throwable $e) {
                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'last_error' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);

                $this->error("CONFIRM FAILED: {$row->transaction_ref} - {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function queueSuccessfulDisbursementNotifications(
        object $row,
        ?string $coreReference = null
    ): void {
        /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | NCBA has already confirmed this transaction successfully.
    |
    | This function ONLY queues notifications.
    | It must NEVER change the NCBA transaction status.
    |
    */

        try {

            /*
        |--------------------------------------------------------------------------
        | Load Loan + Member + Loan Type
        |--------------------------------------------------------------------------
        */

            $details = DB::table('sacco_loans')
                ->join(
                    'sacco_members',
                    'sacco_loans.loan_member',
                    '=',
                    'sacco_members.member_id'
                )
                ->leftJoin(
                    'sacco_loan_types',
                    'sacco_loans.loan_loan_type',
                    '=',
                    'sacco_loan_types.loan_type_id'
                )
                ->where(
                    'sacco_loans.loan_id',
                    $row->loan_id
                )
                ->select(
                    'sacco_loans.loan_id',
                    'sacco_members.member_id',
                    'sacco_members.member_name',
                    'sacco_members.member_sacco_id',
                    'sacco_members.member_phone_no',
                    'sacco_members.member_email',
                    'sacco_loan_types.loan_type_name'
                )
                ->first();

            if (!$details) {
                Log::warning(
                    'NCBA success notification skipped: loan/member details not found.',
                    [
                        'disbursement_id' => $row->id ?? null,
                        'loan_id' => $row->loan_id ?? null,
                        'transaction_ref' => $row->transaction_ref ?? null,
                    ]
                );

                return;
            }

            /*
        |--------------------------------------------------------------------------
        | Transaction Details
        |--------------------------------------------------------------------------
        */

            $amount = (float) ($row->amount ?? 0);

            $formattedAmount = number_format(
                $amount,
                2
            );

            $loanName = trim(
                (string) ($details->loan_type_name ?? '')
            );

            if ($loanName === '') {
                $loanName = 'Loan';
            }

            $memberName = trim(
                (string) ($details->member_name ?? '')
            );

            if ($memberName === '') {
                $memberName = 'Member';
            }

            $memberAccount = trim(
                (string) ($details->member_sacco_id ?? '')
            );

            $transactionReference = trim(
                (string) ($row->transaction_ref ?? '')
            );

            /*
         * Prefer the final NCBA/core reference.
         */
            $bankReference = trim(
                (string) (
                    $coreReference
                    ?: ($row->core_reference ?? null)
                    ?: ($row->bank_reference ?? null)
                    ?: $transactionReference
                )
            );

            $relatedDoc = 'NCBA-' . (
                $transactionReference !== ''
                ? $transactionReference
                : ($row->id ?? 'UNKNOWN')
            );

            /*
        |--------------------------------------------------------------------------
        | Escape Display Values For Email HTML
        |--------------------------------------------------------------------------
        */

            $safeLoanName = e($loanName);
            $safeMemberName = e($memberName);
            $safeMemberAccount = e($memberAccount);
            $safeFormattedAmount = e($formattedAmount);
            $safeBankReference = e($bankReference);

            /*
        |--------------------------------------------------------------------------
        | Kenyan Phone Normalizer
        |--------------------------------------------------------------------------
        |
        | Examples:
        |
        | 0722400737
        | -> 254722400737
        |
        | +254 722 400737
        | -> 254722400737
        |
        | 722400737
        | -> 254722400737
        |
        */

            $normalizePhone = function (
                ?string $phone
            ): ?string {
                $phone = trim((string) $phone);

                if ($phone === '') {
                    return null;
                }

                /*
             * Reject alphabetic garbage.
             */
                if (preg_match('/[a-z]/i', $phone)) {
                    return null;
                }

                /*
             * Remove spaces, +, -, brackets, etc.
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
             * 00254722400737
             * ->
             * 254722400737
             */
                if (str_starts_with($digits, '00254')) {
                    $digits = substr(
                        $digits,
                        2
                    );
                }

                /*
             * 2540722400737
             * ->
             * 254722400737
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
             * 0722400737
             * ->
             * 254722400737
             */ elseif (
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
             * 722400737
             * ->
             * 254722400737
             */ elseif (
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
             * Kenyan mobile numbers only.
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
            };

            /*
        |--------------------------------------------------------------------------
        | Common Notification Metadata
        |--------------------------------------------------------------------------
        */

            $meta = [
                'source' =>
                'ncba_loan_disbursement',

                'status' =>
                'BANK_SUCCESS',

                'disbursement_id' =>
                $row->id ?? null,

                'loan_id' =>
                $row->loan_id ?? null,

                'member_id' =>
                $details->member_id ?? null,

                'member_account' =>
                $memberAccount !== ''
                    ? $memberAccount
                    : null,

                'transaction_ref' =>
                $transactionReference !== ''
                    ? $transactionReference
                    : null,

                'bank_reference' =>
                $bankReference !== ''
                    ? $bankReference
                    : null,

                'amount' =>
                $amount,
            ];

            /*
        |--------------------------------------------------------------------------
        | Member SMS
        |--------------------------------------------------------------------------
        */

            $memberSms =
                "{$loanName} loan of KES {$formattedAmount} "
                . "has been successfully disbursed to your M-PESA.";

            if ($bankReference !== '') {
                $memberSms .=
                    " Ref {$bankReference}.";
            }

            /*
        |--------------------------------------------------------------------------
        | Member Phone Numbers
        |--------------------------------------------------------------------------
        |
        | Supports:
        |
        | 0722400737
        |
        | OR
        |
        | 0722400737,0724275446
        |
        | Every valid number gets its OWN SMS row.
        |--------------------------------------------------------------------------
        */

            $memberPhones = collect(
                preg_split(
                    '/[;,]+/',
                    (string) ($details->member_phone_no ?? '')
                )
            )
                ->map(function ($phone) use ($normalizePhone) {
                    return $normalizePhone(
                        trim((string) $phone)
                    );
                })
                ->filter()
                ->unique()
                ->values();

            foreach (
                $memberPhones
                as $index => $phone
            ) {
                try {
                    app(
                        \App\Services\BulkSms\BulkSmsOutboxService::class
                    )->queue([
                        'member_id' =>
                        (int) $details->member_id,

                        'recipient_name' =>
                        $memberName,

                        'phone' =>
                        $phone,

                        'subject' =>
                        'Loan Disbursement Successful',

                        'message' =>
                        $memberSms,

                        'request_reference' =>
                        'NCBA-MEMBER-SUCCESS-'
                            . $row->id
                            . '-'
                            . ($index + 1),

                        'meta' =>
                        $meta,
                    ]);
                } catch (Throwable $e) {
                    Log::error(
                        'Failed to queue NCBA member SMS.',
                        [
                            'disbursement_id' =>
                            $row->id ?? null,

                            'member_id' =>
                            $details->member_id ?? null,

                            'phone' =>
                            $phone,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Member Email
        |--------------------------------------------------------------------------
        |
        | Keep HTML deliberately simple for maximum email client compatibility.
        |
        | Do NOT add "Dear ...".
        | The main email template already handles the greeting using:
        |
        | notif_recipient_name
        |
        */

            $memberEmailMessage =
                '<p>Your <strong>'
                . $safeLoanName
                . '</strong> loan has been successfully disbursed.</p>'

                . '<p>'

                . '<strong>Amount:</strong> KES '
                . $safeFormattedAmount

                . '<br>'

                . '<strong>Status:</strong> Successfully disbursed';

            if ($bankReference !== '') {
                $memberEmailMessage .=
                    '<br>'
                    . '<strong>Reference:</strong> '
                    . $safeBankReference;
            }

            $memberEmailMessage .=
                '</p>';

            /*
        |--------------------------------------------------------------------------
        | Member Email Addresses
        |--------------------------------------------------------------------------
        |
        | Every valid email gets its OWN notification row.
        |--------------------------------------------------------------------------
        */

            $memberEmails = collect(
                preg_split(
                    '/[;,]+/',
                    (string) ($details->member_email ?? '')
                )
            )
                ->map(function ($email) {
                    return trim(
                        (string) $email
                    );
                })
                ->filter(function ($email) {
                    return (
                        $email !== ''
                        && filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                    );
                })
                ->unique()
                ->values();

            foreach ($memberEmails as $email) {
                try {
                    DB::table(
                        'sacco_system_notifications'
                    )->insert([
                        'notif_recipient_name' =>
                        $memberName,

                        'notif_recipient_email' =>
                        $email,

                        'notif_recipient_phone' =>
                        null,

                        'notif_subject' =>
                        'Loan Disbursement Successful',

                        'notif_message' =>
                        $memberEmailMessage,

                        'notif_status' =>
                        'unread',

                        'notif_member_id' =>
                        (int) $details->member_id,

                        'notif_related_doc' =>
                        $relatedDoc,

                        'notif_type' =>
                        'system',

                        'notif_created_by' =>
                        999,

                        'notif_ip' =>
                        '127.0.0.1',

                        'notif_meta' =>
                        json_encode(
                            $meta,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                        ),

                        'notif_created_at' =>
                        now('Africa/Nairobi'),
                    ]);
                } catch (Throwable $e) {
                    Log::error(
                        'Failed to queue NCBA member email.',
                        [
                            'disbursement_id' =>
                            $row->id ?? null,

                            'member_id' =>
                            $details->member_id ?? null,

                            'email' =>
                            $email,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Management SMS
        |--------------------------------------------------------------------------
        */

            $managerSms =
                "NCBA success: {$memberName}";

            if ($memberAccount !== '') {
                $managerSms .=
                    " ({$memberAccount})";
            }

            $managerSms .=
                ", {$loanName}, KES {$formattedAmount}";

            if ($bankReference !== '') {
                $managerSms .=
                    ". Ref {$bankReference}";
            }

            $managerSms .= '.';

            /*
        |--------------------------------------------------------------------------
        | Management Phone Numbers
        |--------------------------------------------------------------------------
        |
        | Source:
        |
        | sacco_defaults
        | default_name = SACCO_MANAGER_ALERT_PHONE_NUMBERS
        |
        | Supports multiple rows and comma/semicolon separated numbers.
        |
        | Every valid number gets its OWN SMS row.
        |--------------------------------------------------------------------------
        */

            $managerPhones = DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    'SACCO_MANAGER_ALERT_PHONE_NUMBERS'
                )
                ->pluck('default_value')
                ->flatMap(function ($value) {
                    return preg_split(
                        '/[;,]+/',
                        (string) $value
                    );
                })
                ->map(function ($phone) use ($normalizePhone) {
                    return $normalizePhone(
                        trim((string) $phone)
                    );
                })
                ->filter()
                ->unique()
                ->values();

            foreach (
                $managerPhones
                as $index => $phone
            ) {
                try {
                    app(
                        \App\Services\BulkSms\BulkSmsOutboxService::class
                    )->queue([
                        'member_id' =>
                        (int) $details->member_id,

                        'recipient_name' =>
                        'SACCO Manager',

                        'phone' =>
                        $phone,

                        'subject' =>
                        'NCBA Loan Disbursement Successful',

                        'message' =>
                        $managerSms,

                        'request_reference' =>
                        'NCBA-MANAGER-SUCCESS-'
                            . $row->id
                            . '-'
                            . ($index + 1),

                        'meta' =>
                        array_merge(
                            $meta,
                            [
                                'recipient_type' =>
                                'management',
                            ]
                        ),
                    ]);
                } catch (Throwable $e) {
                    Log::error(
                        'Failed to queue NCBA manager SMS.',
                        [
                            'disbursement_id' =>
                            $row->id ?? null,

                            'phone' =>
                            $phone,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Management Email
        |--------------------------------------------------------------------------
        |
        | Simple HTML only:
        |
        | - p
        | - strong
        | - br
        |
        | No tables.
        | No CSS.
        | No external assets.
        |--------------------------------------------------------------------------
        */

            $managerEmailMessage =
                '<p>'
                . '<strong>NCBA loan disbursement successfully completed.</strong>'
                . '</p>'

                . '<p>'

                . '<strong>Member:</strong> '
                . $safeMemberName

                . '<br>';

            if ($memberAccount !== '') {
                $managerEmailMessage .=
                    '<strong>Member account:</strong> '
                    . $safeMemberAccount
                    . '<br>';
            }

            $managerEmailMessage .=
                '<strong>Loan:</strong> '
                . $safeLoanName

                . '<br>'

                . '<strong>Amount:</strong> KES '
                . $safeFormattedAmount

                . '<br>'

                . '<strong>Status:</strong> Successfully disbursed';

            if ($bankReference !== '') {
                $managerEmailMessage .=
                    '<br>'
                    . '<strong>Reference:</strong> '
                    . $safeBankReference;
            }

            $managerEmailMessage .=
                '</p>';

            /*
        |--------------------------------------------------------------------------
        | Management Email Addresses
        |--------------------------------------------------------------------------
        |
        | Source:
        |
        | sacco_defaults
        | default_name = SACCO_MANAGER_ALERT_EMAILS
        |
        | Supports multiple rows and comma/semicolon separated emails.
        |
        | Every valid email gets its OWN notification row.
        |--------------------------------------------------------------------------
        */

            $managerEmails = DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    'SACCO_MANAGER_ALERT_EMAILS'
                )
                ->pluck('default_value')
                ->flatMap(function ($value) {
                    return preg_split(
                        '/[;,]+/',
                        (string) $value
                    );
                })
                ->map(function ($email) {
                    return trim(
                        (string) $email
                    );
                })
                ->filter(function ($email) {
                    return (
                        $email !== ''
                        && filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                    );
                })
                ->unique()
                ->values();

            foreach ($managerEmails as $email) {
                try {
                    DB::table(
                        'sacco_system_notifications'
                    )->insert([
                        'notif_recipient_name' =>
                        'SACCO Manager',

                        'notif_recipient_email' =>
                        $email,

                        'notif_recipient_phone' =>
                        null,

                        'notif_subject' =>
                        'NCBA Loan Disbursement Successful',

                        'notif_message' =>
                        $managerEmailMessage,

                        'notif_status' =>
                        'unread',

                        'notif_member_id' =>
                        (int) $details->member_id,

                        'notif_related_doc' =>
                        $relatedDoc,

                        'notif_type' =>
                        'system',

                        'notif_created_by' =>
                        999,

                        'notif_ip' =>
                        '127.0.0.1',

                        'notif_meta' =>
                        json_encode(
                            array_merge(
                                $meta,
                                [
                                    'recipient_type' =>
                                    'management',
                                ]
                            ),
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                        ),

                        'notif_created_at' =>
                        now('Africa/Nairobi'),
                    ]);
                } catch (Throwable $e) {
                    Log::error(
                        'Failed to queue NCBA manager email.',
                        [
                            'disbursement_id' =>
                            $row->id ?? null,

                            'email' =>
                            $email,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Final Queue Summary
        |--------------------------------------------------------------------------
        */

            Log::info(
                'NCBA successful disbursement notifications queued.',
                [
                    'disbursement_id' =>
                    $row->id ?? null,

                    'loan_id' =>
                    $row->loan_id ?? null,

                    'member_id' =>
                    $details->member_id ?? null,

                    'member_sms_count' =>
                    $memberPhones->count(),

                    'member_email_count' =>
                    $memberEmails->count(),

                    'manager_sms_count' =>
                    $managerPhones->count(),

                    'manager_email_count' =>
                    $managerEmails->count(),
                ]
            );
        } catch (Throwable $e) {

            /*
        |--------------------------------------------------------------------------
        | Notification Failure Isolation
        |--------------------------------------------------------------------------
        |
        | NCBA confirmation has ALREADY succeeded.
        |
        | Never change the disbursement status here.
        | Never throw the notification exception back into the bank flow.
        |
        */

            Log::error(
                'NCBA successful disbursement notification processing failed.',
                [
                    'disbursement_id' =>
                    $row->id ?? null,

                    'loan_id' =>
                    $row->loan_id ?? null,

                    'transaction_ref' =>
                    $row->transaction_ref ?? null,

                    'error' =>
                    $e->getMessage(),
                ]
            );
        }
    }
}
