<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'values' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($d, $k) => [$k => Setting::get($k)])->all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'contract_reminder_days' => ['required', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'contract_due_to_expire_days' => ['required', 'integer', 'min:1', 'max:365'],
            'contract_followup_interval_days' => ['required', 'integer', 'min:1', 'max:90'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:mail,database'],
        ], ['contract_reminder_days.regex' => 'Enter whole numbers of days separated by commas, e.g. 90,60,30.']);

        $old = collect(Setting::DEFAULTS)->mapWithKeys(fn ($d, $k) => [$k => Setting::get($k)])->all();

        $days = collect(explode(',', $data['contract_reminder_days']))->map(fn ($d) => (int) trim($d))
            ->filter()->unique()->sortDesc()->implode(',');
        Setting::put('contract_reminder_days', $days);
        Setting::put('contract_due_to_expire_days', (string) $data['contract_due_to_expire_days']);
        Setting::put('contract_followup_interval_days', (string) $data['contract_followup_interval_days']);
        Setting::put('contract_notify_channels', implode(',', $data['channels']));

        $new = collect(Setting::DEFAULTS)->mapWithKeys(fn ($d, $k) => [$k => Setting::get($k)])->all();
        AuditLog::record('settings.updated', null, 'Contract notification settings updated', $old, $new);

        return back()->with('success', 'Settings saved.');
    }
}
