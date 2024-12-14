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
        // Log member details for debugging
        Log::info('Building WelcomeEmail for member', [
            'id' => $this->member->id,
            'email' => $this->member->email,
            'email_key_unique' => $this->member->email_key_unique ?? 'Missing',
        ]);

        // Ensure email_key_unique exists before generating the route
        if (empty($this->member->email_key_unique)) {
            Log::error('Missing email_key_unique for member ID: ' . $this->member->id);
            throw new \Exception('Cannot send WelcomeEmail: Missing email_key_unique for member ID: ' . $this->member->id);
        }

        // Generate the unique registration link
        $uniqueLink = route('register.unique', ['code' => $this->member->email_key_unique]);

        // Build and return the email
        return $this->subject('Welcome to Our SACCO')
            ->view('emails.welcome')
            ->with([
                'name' => $this->member->first_name,
                'uniqueLink' => $uniqueLink,
            ]);
    }
}