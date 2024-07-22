<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;

class CustomAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->input('login');
        $password = md5($request->input('password'));

        $member = Member::where(function ($query) use ($login) {
            $query->where('member_email', $login)
                ->orWhere('member_phone_no', $login);
        })
        ->where('member_password', $password)
        ->where('member_active', 'Y')
        ->first();

        if ($member) {
            Auth::login($member);
            return redirect()->intended('home')->with('success', 'Logged in successfully');
        }

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('login'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login')->with('success', 'Logged out successfully');
    }
}
