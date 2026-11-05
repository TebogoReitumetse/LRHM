@php $channels = explode(',', $values['contract_notify_channels']); @endphp
<x-layouts.app title="Notification settings">
    <x-page-header title="Contract notification settings" subtitle="Configure when Managers are reminded about contracts approaching expiry." />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="card max-w-3xl">
        @csrf @method('PUT')
        <div class="card-body space-y-5">
            <x-field label="Reminder schedule (days before expiry)" name="contract_reminder_days" required help="Comma-separated, e.g. 90,60,30. An expiry notification is also sent on the expiry date.">
                <input name="contract_reminder_days" value="{{ old('contract_reminder_days', $values['contract_reminder_days']) }}" class="form-input font-mono">
            </x-field>
            <x-field label="Mark contracts as “Due to Expire” within (days)" name="contract_due_to_expire_days" required>
                <input type="number" min="1" max="365" name="contract_due_to_expire_days" value="{{ old('contract_due_to_expire_days', $values['contract_due_to_expire_days']) }}" class="form-input">
            </x-field>
            <x-field label="Follow-up after expiry: repeat every (days) while unresolved" name="contract_followup_interval_days" required>
                <input type="number" min="1" max="90" name="contract_followup_interval_days" value="{{ old('contract_followup_interval_days', $values['contract_followup_interval_days']) }}" class="form-input">
            </x-field>
            <div>
                <span class="form-label">Notification channels</span>
                <div class="flex gap-6 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="channels[]" value="database" class="form-check" @checked(in_array('database', $channels))> In-system notification</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="channels[]" value="mail" class="form-check" @checked(in_array('mail', $channels))> Email</label>
                </div>
                @error('channels')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                The expiry check runs automatically every day at 07:00 (Windows Task Scheduler or cron must run <code class="font-mono">php artisan schedule:run</code> every minute).
                Reminders are administrative only. Contracts are never renewed or ended automatically.
            </div>
            <button class="btn-primary">Save settings</button>
        </div>
    </form>
</x-layouts.app>
