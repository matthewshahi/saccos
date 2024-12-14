<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $member;

    /**
     * Create a new message instance.
     *
     * @param $member
     */
    public function __construct($member)
    {
        $this->member = $member;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $uniqueLink = route('register.unique', ['code' => $this->member->email_key_unique]);

        return $this->subject('Welcome to Our SACCO')
            ->view('emails.welcome')
            ->with([
                'name' => $this->member->first_name,
                'uniqueLink' => $uniqueLink,
            ]);
    }
}