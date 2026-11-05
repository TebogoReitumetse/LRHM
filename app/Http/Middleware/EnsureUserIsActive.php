<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out users whose account was deactivated during their session. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Your account has been deactivated.']);
        }

        // Accounts created by an administrator must set a personal password first.
        if ($request->user() && ! $request->user()->password_changed_at
            && ! $request->routeIs('profile.*', 'logout')) {
            return redirect()->route('profile.edit')->with('warning', 'Please set a new personal password before continuing.');
        }

        return $next($request);
    }
}
