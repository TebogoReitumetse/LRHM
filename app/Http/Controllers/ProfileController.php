<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()->load('department')]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password'], 'password_changed_at' => now()]);
        AuditLog::record('auth.password_changed', $user, $user->name.' changed their password');

        return redirect()->route('dashboard')->with('success', 'Your password has been updated.');
    }
}
