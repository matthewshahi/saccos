<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendGuarantorEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        // Constructor can be used to pass data if needed in the future
    }

    public function handle()
    {
        // Test email to redirect all emails during testing
        $testEmail = "matthewshahi@gmail.com";

        // Fetch loans applied within the last 24 hours that are not deleted or approved
        $loans = DB::table('sacco_loan_batch_trans_members AS loans')
    ->join('sacco_loan_types AS loan_types', 'loans.batch_trans_loan_type', '=', 'loan_types.loan_type_id')
    ->join('sacco_members AS applicants', 'loans.batch_trans_member_id', '=', 'applicants.member_id')
    ->join('sacco_loan_batch_guarantors_members AS guarantors', 'loans.batch_trans_id', '=', 'guarantors.guarantors_loan_batch_trans_id')
    ->join('sacco_members AS guarantor_members', 'guarantors.guarantors_guarantor_id', '=', 'guarantor_members.member_id')
    ->where('loans.batch_trans_deleted', 'N')
    ->where('loans.batch_trans_updated', 'N')
    ->where('guarantors.guarantors_email_sent', 'N')
    ->where('guarantors.guarantors_deleted', 'N')
    ->whereDate('loans.batch_trans_on', '>=', now()->subDay())
    ->select(
        'loans.batch_trans_id',
        'loans.batch_trans_loan_amount',
        'loan_types.loan_type_name',
        'applicants.member_name AS applicant_name',
        'guarantors.guarantors_guarantor_id',
        'guarantors.guarantors_amount_guaranteed',
        'guarantors.guarantors_email_sent',
        'guarantors.guarantors_description',
        'guarantors.guarantors_id',
        'guarantor_members.member_email AS guarantor_email',
        'guarantor_members.member_name AS guarantor_name' // Fetch name for the email
    )
    ->get();

        foreach ($loans as $loan) {
            try {
                // Determine the recipient email address
                $recipientEmail = $testEmail ?? $loan->guarantor_email;

                // Validate email address
                if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    \Log::warning("Invalid email address for guarantor ID: {$loan->guarantors_guarantor_id}");
                    continue;
                }

                // Send email using the Blade template
                Mail::send('emails.guarantor_notification', ['loan' => $loan], function ($message) use ($loan, $recipientEmail) {
                    $message->to($recipientEmail) // Use the test email or actual email
                        ->subject("Loan Guarantee Request for {$loan->applicant_name}");
                });

                // Log success and update email sent status
                \Log::info("Email successfully sent to guarantor ID: {$loan->guarantors_guarantor_id} (Recipient: {$recipientEmail})");
                DB::table('sacco_loan_batch_guarantors_members')
                    ->where('guarantors_id', $loan->guarantors_id)
                    ->update(['guarantors_email_sent' => 'Y']);
            } catch (\Exception $e) {
                // Log error details
                \Log::error("Failed to send email to guarantor ID: {$loan->guarantors_guarantor_id}. Error: {$e->getMessage()}");
            }
        }
    }
}