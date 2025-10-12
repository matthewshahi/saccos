@php
use Carbon\Carbon;

if (Auth::check()) {
    $user = Auth::user();
    $needsChange = false;

    // ✅ define threshold and default values
    $MaxDays   = 35;     // maximum allowed days before password must be changed
    $daysSince = $MaxDays; // fallback default

    // ✅ if member has a recorded last password change date
    if (!empty($user->member_password_last_changed)) {
        $lastChanged = Carbon::parse($user->member_password_last_changed);
        $daysSince   = $lastChanged->diffInDays(Carbon::now());

        // require change if exceeded
        if ($daysSince > $MaxDays) {
            $needsChange = true;
        }
    } else {
        // no password change record — force immediate change
        $needsChange = true;
        $daysSince   = $MaxDays;
    }

    // ✅ redirect if needed
    if ($needsChange && !request()->routeIs('profile.password')) {
        header('Location: ' . route('profile.password') . '?days=' . $daysSince);
        exit;
    }
}
@endphp