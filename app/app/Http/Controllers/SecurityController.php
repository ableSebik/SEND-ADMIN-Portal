<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function page(Request $r)
    {
        return view('security');
    }

    public function changePassword(Request $r)
    {
        $v = $r->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:8|confirmed']);
        $r->user()->forceFill(['password' => $v['password'], 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', Auth::id())->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate();
        PortalController::audit('Changed password', 'Account');

        return back()->with('success', 'Password updated. Other sessions have been revoked.');
    }

    public function sendReset(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $key = 'reset:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429);
        }RateLimiter::hit($key, 300);
        Password::sendResetLink($r->only('email'));

        return back()->with('success', 'If the account exists, a reset link has been sent.');
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:8|confirmed']);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $password) {
            $u->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect('/login')->with('success', 'Password reset. You can sign in now.');
    }
}
