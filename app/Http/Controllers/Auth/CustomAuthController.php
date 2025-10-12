<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use Carbon\Carbon;

class CustomAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login    = $request->input('login');
        $password = md5($request->input('password'));

        // ✅ Check if member exists by email or phone
        $member = Member::where(function ($query) use ($login) {
                $query->where('member_email', $login)
                      ->orWhere('member_phone_no', $login);
            })
            ->where('member_password', $password)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('login'));
        }

        // ✅ Log in the member
        Auth::login($member);

        // ✅ Capture detailed audit info
        $ip          = $request->ip();
        $agent       = $request->header('User-Agent');
        $hostname    = gethostbyaddr($ip) ?: 'Unknown Host';
        $referer     = $request->headers->get('referer') ?: 'Direct Access';
        $method      = $request->method();
        $platform    = php_uname();
        $serverIp    = $_SERVER['SERVER_ADDR'] ?? 'N/A';
        $remotePort  = $_SERVER['REMOTE_PORT'] ?? 'N/A';
        $protocol    = $_SERVER['SERVER_PROTOCOL'] ?? 'N/A';
        $uri         = $request->getRequestUri();
        $time        = Carbon::now()->format('Y-m-d H:i:s');

        // ✅ Build structured audit message
        $auditMessage = "
        Dear {$member->member_name},<br><br>
        This is to notify you that a login to your SACCO account was recorded with the following details:<br><br>

        <strong>📍 Login Details</strong><br>
        IP Address: <code>{$ip}</code><br>
        Hostname: <code>{$hostname}</code><br>
        Device / Browser: <code>{$agent}</code><br>
        Accessed URL: <code>{$uri}</code><br>
        Request Method: <code>{$method}</code><br>
        Referrer: <code>{$referer}</code><br>
        Login Time: <code>{$time}</code><br><br>

        <strong>🖥️ System Environment</strong><br>
        Server IP: <code>{$serverIp}</code><br>
        Remote Port: <code>{$remotePort}</code><br>
        Protocol: <code>{$protocol}</code><br>
        Server Platform: <code>{$platform}</code><br><br>

        ⚠️ <strong>Security Notice:</strong><br>
        If this activity was not initiated by you, it may indicate unauthorized access. 
        Please change your password immediately and contact your SACCO administrator.<br><br>

        <i>This login event has been logged for security and compliance purposes.</i>
        ";

        // ✅ Store login audit record
        DB::table('sacco_system_notifications')->insert([
            'notif_recipient_name'  => $member->member_name,
            'notif_recipient_email' => $member->member_email,
            'notif_recipient_phone' => $member->member_phone_no,
            'notif_subject'         => 'Login Alert - ' . $member->member_name,
            'notif_message'         => $auditMessage,
            'notif_status'          => 'unread', // default status
            'notif_type'            => 'login_audit',
            'notif_sent_at'         => $time,
            'notif_member_id'       => $member->member_id,
            'notif_created_by'      => $member->member_id,
            'notif_ip'              => $ip,
            'notif_created_at'      => $time,
        ]);

        return redirect()->intended('home')->with('success', 'Logged in successfully');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login')->with('success', 'Logged out successfully');
    }
}