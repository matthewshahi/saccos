<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\DB;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $members = DB::table('sacco_members_new_applications')
            ->where('email_sent', 'N')
            ->get();

        foreach ($members as $member) {
            // Validate the email format before sending
            if (filter_var($member->email, FILTER_VALIDATE_EMAIL)) {
                // Send welcome email
                Mail::to($member->email)->send(new WelcomeEmail($member));

                // Mark as sent
                DB::table('sacco_members_new_applications')
                    ->where('id', $member->id)
                    ->update(['email_sent' => 'Y']);
            } else {
                // Optionally log or handle invalid email
                \Log::warning("mugera_Invalid email skipped: {$member->email} (ID: {$member->id})");
            }
        }
    }
}