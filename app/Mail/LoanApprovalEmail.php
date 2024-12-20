<?php
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanApprovalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $loan;

    public function __construct($loan)
    {
        $this->loan = $loan;
    }

    public function build()
    {
        $subject = 'Congratulations! Your Loan Has Been Approved';
        return $this->view('emails.loan_approval')
                    ->subject($subject)
                    ->with([
                        'loan' => $this->loan,
                    ]);
    }
}