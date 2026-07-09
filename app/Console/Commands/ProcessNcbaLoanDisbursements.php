<?php

namespace App\Console\Commands;

use App\Services\NcbaOpenBankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessNcbaLoanDisbursements extends Command
{
    protected $signature = 'ncba:process-loan-disbursements 
                            {--live : Actually send to NCBA. Without this, it only dry-runs.}
                            {--limit=1 : Number of records to process}';

    protected $description = 'Process queued SACCO loan disbursements through NCBA Open Banking';

    public function handle(NcbaOpenBankingService $ncba): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $live = (bool) $this->option('live');

        if ($live && (! config('services.ncba.enabled') || config('services.ncba.dry_run'))) {
            $this->error('Live mode blocked. Set NCBA_ENABLED=true and NCBA_DRY_RUN=false in .env first.');
            return self::FAILURE;
        }

        for ($i = 0; $i < $limit; $i++) {
            $processed = $this->processOne($ncba, $live);

            if (! $processed) {
                $this->info('No READY_TO_SEND disbursement found.');
                break;
            }
        }

        return self::SUCCESS;
    }

    private function processOne(NcbaOpenBankingService $ncba, bool $live): bool
    {
        if (! $live) {
            $row = DB::table('sacco_loan_disbursements')
                ->where('status', 'READY_TO_SEND')
                ->where('disbursement_channel', 'MPESA')
                ->whereNotNull('approved_by')
                ->where('amount', '>=', 50)
                ->orderBy('id')
                ->first();

            if (! $row) {
                return false;
            }

            try {
                $result = $ncba->sendMpesaDisbursement($row);

                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'request_payload' => json_encode($result['payload']),
                        'response_payload' => json_encode($result['response']),
                        'bank_status_message' => 'DRY RUN ONLY - payload generated, no money sent',
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                $this->info("DRY RUN OK: {$row->transaction_ref}");
                $this->line(json_encode($result['payload'], JSON_PRETTY_PRINT));

                return true;
            } catch (Throwable $e) {
                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'last_error' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);

                $this->error("DRY RUN FAILED: {$e->getMessage()}");
                return true;
            }
        }

        $row = DB::transaction(function () {
            $row = DB::table('sacco_loan_disbursements')
                ->where('status', 'READY_TO_SEND')
                ->where('disbursement_channel', 'MPESA')
                ->whereNotNull('approved_by')
                ->where('amount', '>=', 50)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'READY_TO_SEND')
                ->update([
                    'status' => 'SENDING',
                    'updated_at' => now(),
                ]);

            return $row;
        });

        if (! $row) {
            return false;
        }

        try {
            $result = $ncba->sendMpesaDisbursement($row);
            $response = $result['response'] ?? [];

            $succeeded = (bool) ($response['succeeded'] ?? false);
            $statusCode = $response['statusCode'] ?? null;
            $message = $response['message'] ?? ($response['data']['message'] ?? null);
            $bankRef = $response['data']['bankRef'] ?? null;
            $coreRef = $response['data']['id'] ?? null;

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'status' => $succeeded ? 'SENT_TO_BANK' : 'FAILED_RETRY',
                    'sent_at' => now(),
                    'bank_reference' => $bankRef,
                    'core_reference' => $coreRef,
                    'bank_status_code' => $statusCode,
                    'bank_status_message' => $message,
                    'request_payload' => json_encode($result['payload']),
                    'response_payload' => json_encode($response),
                    'retry_count' => $succeeded ? DB::raw('retry_count') : DB::raw('retry_count + 1'),
                    'next_retry_at' => $succeeded ? null : now()->addMinutes(10),
                    'last_error' => $succeeded ? null : json_encode($response),
                    'updated_at' => now(),
                ]);

            $this->info("SENT TO BANK: {$row->transaction_ref}");

            return true;
        } catch (Throwable $e) {
            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'status' => 'FAILED_RETRY',
                    'retry_count' => DB::raw('retry_count + 1'),
                    'next_retry_at' => now()->addMinutes(10),
                    'last_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->error("FAILED: {$row->transaction_ref} - {$e->getMessage()}");

            return true;
        }
    }
}<?php

namespace App\Console\Commands;

use App\Services\NcbaOpenBankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessNcbaLoanDisbursements extends Command
{
    protected $signature = 'ncba:process-loan-disbursements
                            {--live : Actually send to NCBA. Without this, it only dry-runs.}
                            {--limit=1 : Number of records to process}';

    protected $description = 'Process queued SACCO loan disbursements through NCBA Open Banking';

    public function handle(NcbaOpenBankingService $ncba): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $live = (bool) $this->option('live');

        if ($live && (! config('services.ncba.enabled') || config('services.ncba.dry_run'))) {
            $this->error('Live mode blocked. Set NCBA_ENABLED=true and NCBA_DRY_RUN=false in .env first.');
            return self::FAILURE;
        }

        for ($i = 0; $i < $limit; $i++) {
            $processed = $live
                ? $this->processOneLive($ncba)
                : $this->processOneDryRun($ncba);

            if (! $processed) {
                $this->info('No READY_TO_SEND disbursement found.');
                break;
            }
        }

        return self::SUCCESS;
    }

    private function processOneDryRun(NcbaOpenBankingService $ncba): bool
    {
        $row = DB::table('sacco_loan_disbursements')
            ->where('status', 'READY_TO_SEND')
            ->where('disbursement_channel', 'MPESA')
            ->whereNotNull('approved_by')
            ->where('amount', '>=', 50)
            ->orderBy('id')
            ->first();

        if (! $row) {
            return false;
        }

        try {
            // Important: dry-run must only build payload. It must not call NCBA.
            $payload = $ncba->buildMpesaPayload($row);

            $dryResponse = [
                'message' => 'Dry run only. No request was sent to NCBA.',
            ];

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'request_payload' => json_encode($payload),
                    'response_payload' => json_encode($dryResponse),
                    'bank_status_message' => 'DRY RUN ONLY - payload generated, no money sent',
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

            $this->info("DRY RUN OK: {$row->transaction_ref}");
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));

            return true;
        } catch (Throwable $e) {
            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'last_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->error("DRY RUN FAILED: {$row->transaction_ref} - {$e->getMessage()}");

            return true;
        }
    }

    private function processOneLive(NcbaOpenBankingService $ncba): bool
    {
        $row = DB::transaction(function () {
            $row = DB::table('sacco_loan_disbursements')
                ->where('status', 'READY_TO_SEND')
                ->where('disbursement_channel', 'MPESA')
                ->whereNotNull('approved_by')
                ->where('amount', '>=', 50)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'READY_TO_SEND')
                ->update([
                    'status' => 'SENDING',
                    'updated_at' => now(),
                ]);

            return $row;
        });

        if (! $row) {
            return false;
        }

        try {
            $result = $ncba->sendMpesaDisbursement($row);

            $response = $result['response'] ?? [];

           $errorCode = $response['errorCode']
    ?? $response['ErrorCode']
    ?? $response['resErrorCode']
    ?? $response['data']['errorCode']
    ?? null;

$errorMessage = $response['errorMessage']
    ?? $response['ErrorMessage']
    ?? $response['resErrorMessage']
    ?? $response['resErrorDesc']
    ?? $response['message']
    ?? $response['data']['message']
    ?? null;

$succeeded = (
    ($response['succeeded'] ?? false) === true
    || (string) $errorCode === '000'
    || strtoupper((string) $errorMessage) === 'SUCCESS'
);

$statusCode = $errorCode
    ?? $response['statusCode']
    ?? $response['resultCode']
    ?? null;

$message = $errorMessage;

           $bankRef = $response['bankRef']
    ?? $response['bankReference']
    ?? $response['bankReferenceNo']
    ?? $response['resCbxReferenceNo']
    ?? $response['resCoreReferenceNo']
    ?? $response['data']['bankRef']
    ?? $response['data']['bankReference']
    ?? null;

$coreRef = $response['transactionId']
    ?? $response['transactionID']
    ?? $response['CoreReference']
    ?? $response['coreReference']
    ?? $response['resCoreReferenceNo']
    ?? $response['data']['id']
    ?? $response['id']
    ?? null;

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'status' => $succeeded ? 'SENT_TO_BANK' : 'FAILED_RETRY',
                    'sent_at' => now(),
                    'bank_reference' => $bankRef,
                    'core_reference' => $coreRef,
                    'bank_status_code' => $statusCode,
                    'bank_status_message' => $message,
                    'request_payload' => json_encode($result['payload'] ?? []),
                    'response_payload' => json_encode($response),
                    'retry_count' => $succeeded ? DB::raw('retry_count') : DB::raw('retry_count + 1'),
                    'next_retry_at' => $succeeded ? null : now()->addMinutes(10),
                    'last_error' => $succeeded ? null : json_encode($response),
                    'updated_at' => now(),
                ]);

            if ($succeeded) {
                $this->info("SENT TO BANK: {$row->transaction_ref}");
                $this->line('Bank Ref: ' . ($bankRef ?: 'N/A'));
                return true;
            }

            $this->warn("BANK DID NOT CONFIRM SUCCESS: {$row->transaction_ref}");
            $this->line(json_encode($response, JSON_PRETTY_PRINT));

            return true;
        } catch (Throwable $e) {
            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'status' => 'FAILED_RETRY',
                    'retry_count' => DB::raw('retry_count + 1'),
                    'next_retry_at' => now()->addMinutes(10),
                    'last_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->error("FAILED: {$row->transaction_ref} - {$e->getMessage()}");

            return true;
        }
    }
}