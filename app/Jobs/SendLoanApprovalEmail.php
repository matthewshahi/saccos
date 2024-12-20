<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendLoanApprovalEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $loan;

    /**
     * Create a new job instance.
     *
     * @param  $loan
     */
    public function __construct($loan)
    {
        $this->loan = $loan;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Hardcoded test email (can be commented out for production)
        $testEmail = "matthewshahi@gmail.com";

        // Determine the recipient email
        $recipientEmail = isset($testEmail) ? $testEmail : $this->loan->member_email;

        try {
            // Send the email
            Mail::to($recipientEmail)->send(new LoanApprovalEmail($this->loan));

            // Update the loan as email sent
            $this->loan->update(['loan_email_sent' => 'Y']);
        } catch (\Exception $e) {
            // Log the error if email sending fails
            \Log::error('Failed to send loan approval email for Loan ID ' . $this->loan->loan_id . ': ' . $e->getMessage());
        }
    }
}