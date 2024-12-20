<?php
use Illuminate\Console\Command;
use App\Models\SaccoLoan;
use App\Jobs\SendLoanApprovalEmail;

class ProcessLoanEmails extends Command
{
    protected $signature = 'process:loan-emails';
    protected $description = 'Process loan emails in batches';

    public function handle()
    {
        $loans = SaccoLoan::where('loan_email_sent', 'N')
                          ->whereNotNull('loan_on')
                          ->orderBy('loan_on')
                          ->limit(5)
                          ->get();

        foreach ($loans as $loan) {
            dispatch(new SendLoanApprovalEmail($loan));
        }

        $this->info('Processed loan emails.');
    }
}
