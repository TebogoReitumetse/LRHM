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

    /** Saves (or removes, when cleared) the signature that pre-fills the user's signature pads. */
    public function signature(Request $request)
    {
        $data = $request->validate([
            'signature' => ['nullable', 'string', 'max:'.LeaveApplicationController::MAX_SIGNATURE_LENGTH,
                'regex:'.LeaveApplicationController::SIGNATURE_PATTERN],
        ], [
            'signature.regex' => 'The signature could not be read. Please clear it and sign again.',
            'signature.max' => 'The signature is too large. Please clear it and sign again.',
        ]);

        $request->user()->update(['signature' => $data['signature'] ?? null]);

        return back()->with('success', isset($data['signature']) ? 'Your signature has been saved.' : 'Your saved signature has been removed.');
    }
}
