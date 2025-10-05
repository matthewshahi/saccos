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

    protected array $validRows;
    protected string $period;

    /**
     * Create a new job instance.
     */
    public function __construct(array $validRows, string $period)
    {
        $this->validRows = $validRows;
        $this->period = $period;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("🟢 Starting background end-month loan processing for period {$this->period}");

        $controller = app(LoanEndMonthController::class);

        foreach ($this->validRows as $row) {
            try {
                $controller->defaultProcEndMonthLoanUpdate(
                    $row['companyId'],
                    $row['loanTypeId'],
                    $row['loanDocNo'],
                    $row['loanDatePaid']
                );
            } catch (\Exception $e) {
                Log::error("❌ Error in ProcessEndMonthLoansJob → Company {$row['companyId']}, LoanType {$row['loanTypeId']}: " . $e->getMessage());
                continue;
            }
        }

        // ✅ Notify management (position = 2) after completion
        try {
            if (!empty($this->validRows)) {
                $first = $this->validRows[0];

                app(LoanEndMonthController::class)->notifyEndMonthCompletion(
                    $first['companyId'],
                    $first['loanTypeId'],
                    $this->period
                );

                Log::info("📩 Notification successfully dispatched after end-month processing for Company {$first['companyId']} / LoanType {$first['loanTypeId']}");
            }
        } catch (\Exception $e) {
            Log::error("⚠️ Failed to send end-month completion notifications: " . $e->getMessage());
        }

        Log::info("✅ Completed background end-month loan processing for period {$this->period}");
    }
}