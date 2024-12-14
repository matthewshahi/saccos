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
     *
     * @return void
     */
    public function handle()
    {
        // Fetch members who haven't been sent the email (email_sent = 'N')
        $members = DB::table('sacco_members_new_applications')
            ->where('email_sent', 'N')
            ->get();

        foreach ($members as $member) {
            // Send the welcome email
            Mail::to($member->email)->send(new WelcomeEmail($member));

            // Update the database to mark the email as sent
            DB::table('sacco_members_new_applications')
                ->where('id', $member->id)
                ->update(['email_sent' => 'Y']);
        }
    }
}