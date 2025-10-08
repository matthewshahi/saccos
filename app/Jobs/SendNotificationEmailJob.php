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

    protected int $notifId;

    public function __construct(int $notifId)
    {
        $this->notifId = $notifId;
    }

    public function handle(): void
    {
        $notif = DB::table('sacco_system_notifications')
            ->where('notif_id', $this->notifId)
            ->first();

        if (!$notif) {
            Log::warning("⚠️ Notification {$this->notifId} missing in database.");
            return;
        }

        try {
            if (empty($notif->notif_recipient_email)) {
                throw new \Exception('Recipient email missing');
            }

            $companyName = DB::table('sacco_defaults')
                ->where('default_name', 'company_name')
                ->value('default_value') ?? 'iSACCO Technologies';

            $data = [
                'name'        => $notif->notif_recipient_name,
                'messageBody' => $notif->notif_message,
                'companyName' => $companyName,
            ];

            // ✅ Send the email — Laravel will throw an exception automatically if it fails
            Mail::send('emails.generic_notification', $data, function ($message) use ($notif) {
                $message->to($notif->notif_recipient_email, $notif->notif_recipient_name)
                        ->subject($notif->notif_subject ?: 'iSACCO Notification');
            });

            // ✅ Update as sent
            DB::table('sacco_system_notifications')
                ->where('notif_id', $notif->notif_id)
                ->update([
                    'notif_status' => 'sent',
                    'notif_sent_at' => now(),
                ]);

            Log::info("✅ Email sent to {$notif->notif_recipient_email} ({$companyName}) [ID {$notif->notif_id}]");

        } catch (\Throwable $e) {
            DB::table('sacco_system_notifications')
                ->where('notif_id', $this->notifId)
                ->update([
                    'notif_status' => 'failed',
                    'notif_sent_at' => null,
                ]);

            Log::error("❌ Failed to send notif {$this->notifId}: ".$e->getMessage());
        }
    }
}