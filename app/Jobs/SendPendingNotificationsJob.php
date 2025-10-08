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

        // 🔹 Only unread or failed (not queued/sent/read)
        $notifications = DB::table('sacco_system_notifications')
            ->whereIn('notif_status', ['unread', 'failed'])
            ->whereNotNull('notif_recipient_email')
            ->whereNull('notif_sent_at')
            ->orderBy('notif_created_at', 'asc')
            ->limit($limit)
            ->get();

        $queued = 0;

        foreach ($notifications as $notif) {
            // 🔹 Mark as queued immediately
            DB::table('sacco_system_notifications')
                ->where('notif_id', $notif->notif_id)
                ->update([
                    'notif_status' => 'queued',
                    'notif_sent_at' => now(),
                ]);

            // 🔹 Dispatch actual sender job (by ID for uniqueness)
            dispatch((new SendNotificationEmailJob((int) $notif->notif_id))->onQueue('emails'));
            $queued++;
        }

        Log::info($queued > 0
            ? "📧 SendPendingNotificationsJob queued {$queued} email(s) for sending."
            : "📧 No new notifications to queue."
        );
    }
}