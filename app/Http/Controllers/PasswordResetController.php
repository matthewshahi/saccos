<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Show confirmation page before executing reset.
     */
    public function confirm()
    {
        // Count active + non-deleted members
        $total = DB::table('sacco_members')
            ->where(function ($q) {
                $q->whereNull('member_deleted')
                  ->orWhere('member_deleted', '<>', 'Y');
            })
            ->count();

        return view('members.mass_password_reset_confirm', compact('total'));
    }

    /**
     * Execute the actual mass reset after confirmation.
     */
    public function execute(Request $request)
    {
        $request->validate([
            'confirm_reset' => 'accepted',
        ]);

        $now = Carbon::now();
        $ip = $request->ip();
        $user = Auth::user();
        $adminName = $user->name ?? ($user->member_name ?? 'System Admin');
        $adminId = $user->id ?? 0;

        // Fetch all non-deleted members
        $members = DB::table('sacco_members')
            ->select('member_id', 'member_name', 'member_email', 'member_phone_no')
            ->where(function ($q) {
                $q->whereNull('member_deleted')
                  ->orWhere('member_deleted', '<>', 'Y');
            })
            ->get();

        $resetCount = 0;
        $failCount  = 0;

        foreach ($members as $member) {
            try {
                // Generate new simple random password
                $plainPassword = Str::random(8);
                $hashedPassword = md5($plainPassword);

                // Update member credentials
                DB::table('sacco_members')
                    ->where('member_id', $member->member_id)
                    ->update([
                        'member_password' => $hashedPassword,
                        'member_password_last_changed' => null,
                        'member_last_mobile_login' => null,
                        'member_password_changed_by' => $adminId,
                    ]);

                // ✅ Compose notification with the new password
                $message = "Your SACCO account password has been reset by the administrator. "
                    . "Your old password is no longer valid. "
                    . "Your new temporary password is: {$plainPassword}  "
                    . "Please log in and change your password immediately.";

                // Insert system notification
                DB::table('sacco_system_notifications')->insert([
                    'notif_recipient_name'  => $member->member_name,
                    'notif_recipient_email' => $member->member_email,
                    'notif_recipient_phone' => $member->member_phone_no,
                    'notif_subject'         => 'Mass Password Reset Notice',
                    'notif_message'         => $message,
                    'notif_status'          => 'unread',
                    'notif_member_id'       => $member->member_id,
                    'notif_type'            => 'system_password_reset',
                    'notif_created_by'      => $adminId,
                    'notif_ip'              => $ip,
                    'notif_meta'            => json_encode([
                        'admin' => $adminName,
                        'generated_password' => $plainPassword,
                        'reset_at' => $now->toDateTimeString(),
                    ]),
                    'notif_created_at'      => $now,
                ]);

                $resetCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error("Password reset failed for member_id {$member->member_id}: " . $e->getMessage());
            }
        }

        // ✅ Force logout of all users
        try {
            if (config('session.driver') === 'database') {
                DB::table('sessions')->truncate();
            } else {
                Artisan::call('session:clear');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear sessions after password reset: ' . $e->getMessage());
        }

        return redirect()
            ->route('members.mass_password_reset.confirm')
            ->with('success', "✅ {$resetCount} passwords reset successfully. {$failCount} failed (check logs). All users have been logged out.");
    }
}
