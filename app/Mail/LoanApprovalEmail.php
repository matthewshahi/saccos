<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanApprovalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $loan;
    public $saccoMail;

    public function __construct($loan, $saccoMail)
    {
        $this->loan = $loan;
        $this->saccoMail = $saccoMail;
    }

    public function build()
    {
        return $this->view('emails.loan_approval')
            ->subject('Loan Approved')
            ->with([
                'loan' => $this->loan,
                'saccoMail' => $this->saccoMail,
            ]);
    }
}