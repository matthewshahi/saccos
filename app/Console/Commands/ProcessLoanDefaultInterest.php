<?php

namespace App\Console\Commands;

use App\Services\LoanDefaultInterestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessLoanDefaultInterest extends Command
{
    protected $signature = 'loans:process-default-interest {--limit=20}';

    protected $description = 'Process automatic default interest for at most 20 eligible SACCO loans';

    public function handle(LoanDefaultInterestService $service): int
    {
        // Hard cap: this command must never process more than 20 loans per invocation.
        $limit = max(1, min(20, (int) $this->option('limit')));
        $threshold = $service->thresholdAmount();

        $databaseName = (string) DB::connection()->getDatabaseName();
        $cursorKey = 'dfi:loan-cursor:' . sha1($databaseName);
        $lastLoanId = (int) Cache::get($cursorKey, 0);

        $candidateIds = $this->candidateLoanIds(
            $lastLoanId,
            $limit,
            $threshold
        );

        if ($candidateIds->isEmpty()) {
            if ($lastLoanId > 0) {
                // End of this pass. Start again from the beginning next minute.
                Cache::put($cursorKey, 0, now()->addDays(7));
            }

            $this->info('DFI: no candidate loans in this cursor window.');
            return self::SUCCESS;
        }

        $posted = 0;
        $duplicates = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($candidateIds as $loanId) {
            $loanId = (int) $loanId;

            try {
                $result = $service->processLoan($loanId);
                $status = $result['status'] ?? 'skipped';

                if ($status === 'posted') {
                    $posted++;
                    $this->line(
                        'POSTED ' . ($result['doc_no'] ?? '')
                        . ' KES ' . number_format((float) ($result['interest'] ?? 0), 2)
                    );
                } elseif ($status === 'duplicate') {
                    $duplicates++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $errors++;

                Log::error('DFI loan processing failed', [
                    'loan_id' => $loanId,
                    'message' => $e->getMessage(),
                ]);

                $this->error("DFI loan {$loanId}: {$e->getMessage()}");
            } finally {
                // Progress past bad/non-default loans so one record can never block the whole SACCO.
                Cache::put($cursorKey, $loanId, now()->addDays(7));
            }
        }

        $this->info(
            "DFI complete: examined={$candidateIds->count()}, posted={$posted}, duplicates={$duplicates}, skipped={$skipped}, errors={$errors}."
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function candidateLoanIds(int $afterLoanId, int $limit, float $threshold)
    {
        return DB::table('sacco_loans as l')
            ->join('sacco_loan_types as t', 'l.loan_loan_type', '=', 't.loan_type_id')
            ->where('l.loan_id', '>', $afterLoanId)
            ->whereRaw("COALESCE(l.loan_stoped, 'N') <> 'Y'")
            ->whereRaw(
                '(COALESCE(l.loan_amount,0) - COALESCE(l.loan_loan_paid,0)) > ?',
                [$threshold]
            )
            ->where(function ($query) {
                $query->where('t.loan_type_auto_interest_on_period_change', 1)
                    ->orWhereIn('t.loan_type_auto_interest_on_period_change', [
                        'Y', 'YES', 'TRUE',
                    ]);
            })
            // Deliberately no member_active/member_deleted filter.
            // Existing loans remain financially valid even for inactive members.
            ->orderBy('l.loan_id')
            ->limit($limit)
            ->pluck('l.loan_id');
    }
}
