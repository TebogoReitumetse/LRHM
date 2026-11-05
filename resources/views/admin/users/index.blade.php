<x-layouts.app title="Users">
    <x-page-header title="User accounts" subtitle="Employees, contractors and management users with access to the HRMS.">
        <a href="{{ route('admin.users.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add user</a>
    </x-page-header>

    <form method="GET" class="card mb-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="sm:col-span-2"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-input" placeholder="Name, username or employee no."></div>
        <div><label class="form-label">Role</label>
            <select name="role" class="form-input"><option value="">All</option>
                @foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ $r->label() }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Department</label>
            <select name="department" class="form-input"><option value="">All</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach
            </select></div>
        <div class="flex items-end gap-2"><button class="btn-primary">Filter</button><a href="{{ route('admin.users.index') }}" class="btn-secondary">Reset</a></div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Username</th><th>Employee no.</th><th>Department</th><th>Role</th><th>Type</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td><div class="font-medium text-gray-900">{{ $u->name }}</div><div class="text-xs text-gray-500">{{ $u->job_title }}</div></td>
                        <td class="font-mono text-xs">{{ $u->username }}</td>
                        <td>{{ $u->employee_number }}</td>
                        <td>{{ $u->department?->name ?? '—' }}</td>
                        <td><span @class(['badge', 'bg-brand-50 text-brand-800 ring-brand-200' => $u->isManagement(), 'bg-purple-50 text-purple-700 ring-purple-200' => $u->isAdmin(), 'bg-gray-100 text-gray-700 ring-gray-300' => $u->isEmployee()])>{{ $u->role->label() }}</span></td>
                        <td class="text-xs capitalize">{{ $u->employment_type }}</td>
                        <td>{!! $u->is_active ? '<span class="badge bg-accent-50 text-accent-800 ring-accent-200">Active</span>' : '<span class="badge bg-gray-100 text-gray-500 ring-gray-300">Inactive</span>' !!}
                            @unless ($u->password_changed_at)<div class="mt-1 text-[11px] text-amber-700">Must set password</div>@endunless</td>
                        <td class="text-right whitespace-nowrap">
                            @if (auth()->user()->isAdmin() || ! $u->hasRole(\App\Enums\Role::Director, \App\Enums\Role::Admin))
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn-secondary btn-sm">Edit</a>
                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="inline" onsubmit="return confirm('Issue a new temporary password for {{ $u->name }}?')">@csrf
                                        <button class="btn-secondary btn-sm" title="Reset password"><x-icon name="key" class="size-4" /></button>
                                    </form>
                                @endunless
                                @if (auth()->user()->isManager())
                                    <a href="{{ route('contracts.create', ['user' => $u->id]) }}" class="btn-secondary btn-sm" title="New contract"><x-icon name="briefcase" class="size-4" /></a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</x-layouts.app>
