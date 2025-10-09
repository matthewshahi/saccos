<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmailController extends Controller
{
    /**
     * Show the bulk email interface with filters.
     */
 public function index(Request $request)
{
    $filters = [
        'active' => $request->input('active', 'Y'),
        'include_deleted' => $request->boolean('include_deleted', false),
        'officials_only' => $request->boolean('officials_only', false),
        'search_name' => trim($request->input('search_name', '')),
    ];

    $query = DB::table('sacco_members');

    // Filter by active status
    if ($filters['active'] === 'Y') {
        $query->where('member_active', 'Y');
    } elseif ($filters['active'] === 'N') {
        $query->where('member_active', 'N');
    }

    // Include or exclude deleted members
    if (!$filters['include_deleted']) {
        $query->where('member_deleted', '<>', 'Y');
    }

    // Officials Only
    if ($filters['officials_only']) {
        $query->where('member_position', '=', 2);
    }

    // ✅ Optional Name Search
    if (!empty($filters['search_name'])) {
        $query->where('member_name', 'like', '%' . $filters['search_name'] . '%');
    }

    $members = $query->select(
            'member_id',
            'member_name',
            'member_email',
            'member_phone_no',
            'member_active',
            'member_position'
        )
        ->orderBy('member_name')
        ->paginate(50)
        ->appends($filters); // keep filters in pagination links

    return view('emails.bulk', compact('members', 'filters'));
}

    /**
     * Store bulk email records in sacco_system_notifications table.
     * (Emails are not sent directly — only queued/saved.)
     */
  public function sendBulk(Request $request)
{
    $request->validate([
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
    ]);

    // Get filters from the previous request (if any)
    $filters = [
        'active' => $request->input('active', 'Y'),
        'include_deleted' => $request->boolean('include_deleted', false),
        'officials_only' => $request->boolean('officials_only', false),
        'search_name' => trim($request->input('search_name', '')),
    ];

    // Build base query for filtered members
    $query = DB::table('sacco_members');

    if ($filters['active'] === 'Y') $query->where('member_active', 'Y');
    elseif ($filters['active'] === 'N') $query->where('member_active', 'N');

    if (!$filters['include_deleted']) $query->where('member_deleted', '<>', 'Y');
    if ($filters['officials_only']) $query->where('member_position', 2);
    if (!empty($filters['search_name'])) $query->where('member_name', 'like', '%' . $filters['search_name'] . '%');

    // ✅ Limit check
    $count = $query->count();
    if ($count > 1000) {
        return back()->with('error', "Too many recipients ({$count}). Please narrow your filters before sending.");
    }

    // ✅ Get members with emails
    $members = $query->whereNotNull('member_email')->get();

    if ($members->isEmpty()) {
        return back()->with('error', 'No members found matching your filters.');
    }

    // ✅ User and environment info
    $user = Auth::user();
    $userId = $user->id ?? 0;
    $userName = $user->name ?? ($user->member_name ?? 'System User');
    $ip = $request->ip();
    $userAgent = $request->userAgent();
    $sessionId = session()->getId();
    $now = now();
    $insertCount = 0;
    $failCount = 0;
    $invalidCount = 0;

    // ✅ Meta for audit trail
    $meta = [
        'sender_id'   => $userId,
        'sender_name' => $userName,
        'ip_address'  => $ip,
        'browser'     => $userAgent,
        'session_id'  => $sessionId,
        'timestamp'   => $now->toDateTimeString(),
        'filters_applied' => $filters,
    ];

    // ✅ Loop through members
    foreach ($members as $m) {
        // Validate email before inserting
        $email = trim($m->member_email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalidCount++;
            Log::warning("⚠️ Invalid email skipped for member_id {$m->member_id} ({$m->member_name}): {$email}");
            continue;
        }

        try {
            DB::table('sacco_system_notifications')->insert([
                'notif_recipient_name'   => $m->member_name,
                'notif_recipient_email'  => $email,
                'notif_recipient_phone'  => $m->member_phone_no,
                'notif_subject'          => $request->subject,
                'notif_message'          => $request->message,
                'notif_status'           => 'unread',
                'notif_member_id'        => $m->member_id,
                'notif_type'             => 'bulk_email',
                'notif_created_by'       => $userId,
                'notif_ip'               => $ip,
                'notif_meta'             => json_encode($meta),
                'notif_created_at'       => $now,
            ]);
            $insertCount++;
        } catch (\Exception $e) {
            $failCount++;
            Log::error("❌ Failed to queue email for member_id {$m->member_id}: {$e->getMessage()}");
        }
    }

    // ✅ Summary Message
    $msg = "{$insertCount} email(s) queued successfully for all filtered members.";
    if ($invalidCount > 0) $msg .= " {$invalidCount} invalid email(s) were skipped.";
    if ($failCount > 0) $msg .= " {$failCount} failed — check logs.";

    return back()->with('success', $msg);
}
}