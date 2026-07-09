<?php

namespace App\Console\Commands;

use App\Services\NcbaOpenBankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
}