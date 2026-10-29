<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSecurity
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()->active) {
            Auth::logout();
            $r->session()->invalidate();

            return redirect('/login');
        }

        return $next($r);
    }
}
