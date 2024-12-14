<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        // Ensure email_key_unique exists before generating the URL
        if (empty($this->member->email_key_unique)) {
            Log::error('Missing email_key_unique for member ID: ' . $this->member->id);
            throw new \Exception('Cannot send WelcomeEmail: Missing email_key_unique for member ID: ' . $this->member->id);
        }

        // Generate the unique registration link using the url() helper
        $uniqueLink = url('/register/' . $this->member->email_key_unique);

        // Log the generated link for debugging
        Log::info('Generated unique link for WelcomeEmail', [
            'member_id' => $this->member->id,
            'uniqueLink' => $uniqueLink,
        ]);

        // Build and return the email
        return $this->subject('Welcome to Our SACCO')
            ->view('emails.welcome')
            ->with([
                'name' => $this->member->first_name,
                'uniqueLink' => $uniqueLink,
            ]);
    }
}