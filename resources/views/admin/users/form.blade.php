@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit user' : 'Add user'">
    <x-page-header :title="$editing ? 'Edit '.$user->name : 'Add user account'" :back="route('admin.users.index')" />

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Employee details</h2></div>
            <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Full name" name="name" required><input name="name" value="{{ old('name', $user->name) }}" class="form-input" required></x-field>
                <x-field label="Employee number" name="employee_number" required><input name="employee_number" value="{{ old('employee_number', $user->employee_number) }}" class="form-input" required></x-field>
                <x-field label="Department" name="department_id">
                    <select name="department_id" class="form-input"><option value="">—</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Job title / function" name="job_title"><input name="job_title" value="{{ old('job_title', $user->job_title) }}" class="form-input"></x-field>
                <x-field label="Email (for notifications)" name="email"><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-input"></x-field>
                <x-field label="Phone" name="phone"><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-input"></x-field>
                <x-field label="Employment type" name="employment_type" required>
                    <select name="employment_type" class="form-input">
                        <option value="employee" @selected(old('employment_type', $user->employment_type) === 'employee')>Employee</option>
                        <option value="contractor" @selected(old('employment_type', $user->employment_type) === 'contractor')>Contractor</option>
                    </select>
                </x-field>
            </div>
        </div>
        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">Access</h2></div>
            <div class="card-body space-y-4">
                <x-field label="Username" name="username" required><input name="username" value="{{ old('username', $user->username) }}" class="form-input font-mono" required autocomplete="off"></x-field>
                <x-field label="Role" name="role" required>
                    <select name="role" class="form-input" @disabled($user->is(auth()->user()))>
                        @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', $user->role?->value) === $r->value)>{{ $r->label() }}</option>@endforeach
                    </select>
                    @if ($user->is(auth()->user()))<input type="hidden" name="role" value="{{ $user->role->value }}">@endif
                </x-field>
                @unless ($editing)
                    <x-field label="Initial password (optional)" name="password" help="Leave blank to generate a temporary password. The user must change it at first sign-in.">
                        <input type="text" name="password" class="form-input font-mono" autocomplete="off">
                    </x-field>
                @endunless
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="can_view_contract" value="0">
                    <input type="checkbox" name="can_view_contract" value="1" class="form-check mt-0.5" @checked(old('can_view_contract', $user->can_view_contract))>
                    <span>May view their own contract online</span>
                </label>
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check mt-0.5" @checked(old('is_active', $user->is_active)) @disabled($user->is(auth()->user()))>
                    <span>Account active (inactive users cannot sign in)</span>
                </label>
                <button class="btn-primary w-full">{{ $editing ? 'Save changes' : 'Create account' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
