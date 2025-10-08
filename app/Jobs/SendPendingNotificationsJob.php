<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendNotificationEmailJob;

class SendPendingNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $limit = (int) env('NOTIF_EMAILS_PER_MIN', 10);

        // Select unread or failed notifications that have not been sent
        $notifications = DB::table('sacco_system_notifications')
            ->whereIn('notif_status', ['unread', 'failed'])
            ->whereNull('notif_sent_at')
            ->whereNotNull('notif_recipient_email')
            ->orderBy('notif_created_at')
            ->limit($limit)
            ->get();

        $queued = 0;

        foreach ($notifications as $notif) {
            dispatch((new SendNotificationEmailJob($notif))->onQueue('emails'));
            $queued++;
        }

        Log::info("📧 SendPendingNotificationsJob queued {$queued} email(s) this minute.");
    }
}