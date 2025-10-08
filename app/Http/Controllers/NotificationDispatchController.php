<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendNotificationEmailJob;

class NotificationDispatchController extends Controller
{
    /**
     * Queue all pending (unsent or failed) notifications for email sending.
     */
    public function sendEmails()
    {
        // Fetch all unsent or failed notifications that have valid email addresses
        $notifications = DB::table('sacco_system_notifications')
            ->whereIn('notif_status', ['unread', 'failed'])
            ->whereNotNull('notif_recipient_email')
            ->get();

        $queued = 0;

        foreach ($notifications as $notif) {
            dispatch(new SendNotificationEmailJob($notif))->onQueue('emails');
            $queued++;
        }

        return redirect()->back()->with('success', "{$queued} notification email(s) queued for sending.");
    }
}