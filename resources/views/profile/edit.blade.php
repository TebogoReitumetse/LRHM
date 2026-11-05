<x-layouts.app title="Profile">
    <x-page-header title="My profile" subtitle="Your employee details and sign-in security." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Employee details</h2></div>
            <div class="card-body">
                <dl class="dl-grid">
                    <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
                    <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
                    <div><dt>Employee number</dt><dd>{{ $user->employee_number }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $user->role->label() }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $user->department?->name ?? '—' }}</dd></div>
                    <div><dt>Job title</dt><dd>{{ $user->job_title ?? '—' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $user->email ?? '—' }}</dd></div>
                    <div><dt>Last sign-in</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-gray-500">To correct your details, please contact your Manager or the system administrator.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Change password</h2></div>
            <form method="POST" action="{{ route('profile.password') }}" class="card-body space-y-4">
                @csrf @method('PUT')
                <x-field label="Current password" name="current_password" required>
                    <input type="password" name="current_password" autocomplete="current-password" class="form-input" required>
                </x-field>
                <x-field label="New password" name="password" required help="At least 10 characters, with upper and lower case letters, a number and a symbol.">
                    <input type="password" name="password" autocomplete="new-password" class="form-input" required>
                </x-field>
                <x-field label="Confirm new password" name="password_confirmation" required>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="form-input" required>
                </x-field>
                <button class="btn-primary">Update password</button>
            </form>
        </div>
    </div>
</x-layouts.app>
