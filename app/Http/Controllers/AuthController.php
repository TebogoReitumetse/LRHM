<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! $user->is_active || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::create([
                'event' => 'auth.failed', 'description' => 'Failed login for "'.mb_substr($credentials['username'], 0, 60).'"',
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);

            throw ValidationException::withMessages(['username' => 'These credentials do not match an active account.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLog::record('auth.login', $user, $user->name.' signed in');

        if (! $user->password_changed_at) {
            return redirect()->route('profile.edit')->with('warning', 'Please set a new personal password before continuing.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request->user(), $request->user()->name.' signed out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
