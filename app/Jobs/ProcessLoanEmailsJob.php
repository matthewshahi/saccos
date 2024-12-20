<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\LoanApprovalEmail;

class ProcessLoanEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Hardcoded test email (can be commented out)
        $testEmail = "matthewshahi@gmail.com";

        // Fetch sacco_mail from sacco_defaults table
        $saccoMail = DB::table('sacco_defaults')
            ->where('default_name', 'sacco_mail')
            ->value('default_value');

        // Fetch loans where loan_email_sent = 'N' and updated in the last 24 hours
        $loans = DB::table('sacco_loans as loans')
            ->join('sacco_members as members', 'loans.loan_member', '=', 'members.member_id')
            ->join('sacco_loan_types as loan_types', 'loans.loan_loan_type', '=', 'loan_types.loan_type_id')
            ->where('loans.loan_email_sent', 'N')
            ->where('loans.loan_on', '>=', now()->subDay()) // Only loans updated in the last 24 hours
            ->select(
                'loans.loan_id',
                'loans.loan_amount',
                'loans.loan_taken_period',
                'loans.loan_monthly_repayment_amount',
                'loans.loan_on',
                'members.member_email',
                'members.member_name',
                'loan_types.loan_type_name as loan_type'
            )
            ->orderBy('loans.loan_on')
            ->limit(5)
            ->lockForUpdate() // Prevent other processes from selecting the same loans
            ->get();

        foreach ($loans as $loan) {
            try {
                // Update loan_email_sent to 'Y' to avoid processing the same loan again
                DB::table('sacco_loans')
                    ->where('loan_id', $loan->loan_id)
                    ->update(['loan_email_sent' => 'Y']);

                // Determine recipient email
                $recipientEmail = isset($testEmail) ? $testEmail : $loan->member_email;

                // Send the email
                Mail::to($recipientEmail)->send(new LoanApprovalEmail($loan, $saccoMail));
            } catch (\Exception $e) {
                Log::error('Failed to send loan approval email for Loan ID ' . $loan->loan_id . ': ' . $e->getMessage());
            }
        }

        Log::info('Processed loan emails.');
    }
}