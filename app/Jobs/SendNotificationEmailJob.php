<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notification;

    /**
     * Create a new job instance.
     */
    public function __construct($notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notif = $this->notification;

        try {
            // Skip if no recipient email
            if (empty($notif->notif_recipient_email)) {
                Log::warning("Skipping notification ID {$notif->notif_id}: No recipient email provided.");
                return;
            }

            // Fetch company name dynamically from sacco_defaults
            $companyName = DB::table('sacco_defaults')
                ->where('default_name', 'company_name')
                ->value('default_value') ?? 'iSACCO Technologies';

            // Prepare email content
            $data = [
                'name'         => $notif->notif_recipient_name,
                'messageBody'  => $notif->notif_message,
                'companyName'  => $companyName,
            ];

            // Send email
            Mail::send('emails.generic_notification', $data, function ($message) use ($notif) {
                $subject = $notif->notif_subject ?: 'iSACCO Notification';
                $message->to($notif->notif_recipient_email, $notif->notif_recipient_name)
                        ->subject($subject);
            });

            // Detect send failure
            if (!empty(Mail::failures())) {
                throw new \Exception('Mail sending failed for ' . $notif->notif_recipient_email);
            }

            // Update notification status
            DB::table('sacco_system_notifications')
                ->where('notif_id', $notif->notif_id)
                ->update([
                    'notif_status' => 'sent',
                    'notif_sent_at' => now(),
                ]);

            Log::info("✅ Notification email sent successfully to {$notif->notif_recipient_email} ({$companyName}) [ID: {$notif->notif_id}]");
        } catch (\Throwable $e) {
            // Update record to failed
            DB::table('sacco_system_notifications')
                ->where('notif_id', $notif->notif_id)
                ->update([
                    'notif_status' => 'failed',
                ]);

            Log::error("❌ Failed to send notification ID {$notif->notif_id}: " . $e->getMessage());
        }
    }
}