<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LoanEndMonthController;

class ProcessEndMonthLoansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum execution time (in seconds).
     * Extended to handle large end-month processing workloads.
     */
    public $timeout = 1900; // ~31 minutes

    /**
     * Number of times the job should be attempted.
     */
    public $tries = 5;

    /**
     * @var array
     */
    protected array $validRows;

    /**
     * @var string
     */
    protected string $period;

    /**
     * Create a new job instance.
     *
     * @param array $validRows
     * @param string $period
     */
    public function __construct(array $validRows, string $period)
    {
        $this->validRows = $validRows;
        $this->period = $period;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info("🟢 Starting background end-month loan processing for period {$this->period}");

        $controller = app(LoanEndMonthController::class);

        foreach ($this->validRows as $row) {
            try {
                // Ensure required keys exist before processing
                if (
                    !isset($row['companyId'], $row['loanTypeId'], $row['loanDocNo'], $row['loanDatePaid'])
                ) {
                    Log::warning("⚠️ Skipped incomplete row in ProcessEndMonthLoansJob: " . json_encode($row));
                    continue;
                }

                // Core processing
                $controller->defaultProcEndMonthLoanUpdate(
                    $row['companyId'],
                    $row['loanTypeId'],
                    $row['loanDocNo'],
                    $row['loanDatePaid']
                );

                Log::info("✅ Processed Company {$row['companyId']} / LoanType {$row['loanTypeId']} for period {$this->period}");
            } catch (\Throwable $e) {
                Log::error("❌ Error in ProcessEndMonthLoansJob → Company {$row['companyId']}, LoanType {$row['loanTypeId']}: " . $e->getMessage());
                continue;
            }
        }

        // ✅ Notify management (position = 2) after completion
        try {
            if (!empty($this->validRows)) {
                $first = $this->validRows[0];

                $controller->notifyEndMonthCompletion(
                    $first['companyId'],
                    $first['loanTypeId'],
                    $this->period
                );

                Log::info("📩 Notification successfully dispatched after end-month processing for Company {$first['companyId']} / LoanType {$first['loanTypeId']}");
            }
        } catch (\Throwable $e) {
            Log::error("⚠️ Failed to send end-month completion notification: " . $e->getMessage());
        }

        Log::info("✅ Completed background end-month loan processing for period {$this->period}");
    }
}