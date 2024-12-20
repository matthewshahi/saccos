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

    /**
     * Create a new message instance.
     *
     * @param $loan
     * @param $saccoMail
     */
    public function __construct($loan, $saccoMail)
    {
        $this->loan = $loan;
        $this->saccoMail = $saccoMail;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->view('emails.loan_approval')
            ->subject('Congratulations! Your Loan Has Been Approved')
            ->with([
                'loan' => $this->loan,
                'saccoMail' => $this->saccoMail,
            ]);
    }
}