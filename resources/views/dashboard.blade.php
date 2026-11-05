<x-layouts.app title="Dashboard">
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gray-950 px-6 py-7 text-white sm:px-8">
        <div class="absolute -top-16 -right-16 size-64 rounded-full bg-brand-500/20 blur-2xl"></div>
        <div class="absolute -bottom-20 left-1/3 size-64 rounded-full bg-accent-500/20 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-300">{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }},</p>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ $user->job_title ?? $user->role->label() }}{{ $user->department ? ' · '.$user->department->name : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('leave.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Apply for leave</a>
                @if ($user->isManagement())
                    <a href="{{ route('approvals.index') }}" class="btn bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20">
                        <x-icon name="check-badge" class="size-4" /> {{ $user->isDirector() ? 'Decisions' : 'Reviews' }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    @isset($stats)
        {{-- Management dashboard --}}
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">Leave overview</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Awaiting Manager review" :value="$stats['awaiting_manager']" icon="clock" tone="indigo" :href="route('register.index', ['status' => 'submitted'])" />
            <x-stat label="Awaiting Director approval" :value="$stats['awaiting_director']" icon="check-badge" tone="brand" :href="route('register.index', ['status' => 'pending_director_approval'])" />
            <x-stat label="Currently on approved leave" :value="$stats['on_leave']" icon="sun" tone="accent" :href="route('register.index', ['status' => 'approved', 'from' => today()->toDateString(), 'to' => today()->toDateString()])" />
            <x-stat label="Approved upcoming leave" :value="$stats['upcoming']" icon="calendar" tone="sky" :href="route('register.index', ['status' => 'approved', 'from' => today()->addDay()->toDateString()])" />
            <x-stat label="Total applications submitted" :value="$stats['total']" icon="clipboard" tone="gray" :href="route('register.index')" />
            <x-stat label="Returned for information" :value="$stats['returned']" icon="undo" tone="amber" :href="route('register.index', ['status' => 'returned_for_information'])" />
            <x-stat label="Declined applications" :value="$stats['declined']" icon="x-circle" tone="red" :href="route('register.index', ['status' => 'declined'])" />
            <x-stat label="Absences open for follow-up" :value="$absencesOpen" icon="user-minus" tone="amber" :href="route('absences.index', ['follow_up' => 'open'])" :hint="$absencesPending.' pending an explanation'" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card xl:col-span-2">
                <div class="card-header">
                    <h3 class="card-title">{{ $user->isDirector() ? 'Awaiting your decision' : 'Awaiting your review' }}</h3>
                    <a href="{{ route('approvals.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
                </div>
                @if ($actionQueue->isEmpty())
                    <x-empty icon="check" title="You're all caught up" text="No applications are waiting for you right now." />
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($actionQueue as $a)
                            <li>
                                <a href="{{ route('leave.show', $a) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-brand-50/40">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                        {{ collect(explode(' ', $a->employee_name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ $a->employee_name }} <span class="font-normal text-gray-500">· {{ $a->category->name }}</span></p>
                                        <p class="text-xs text-gray-500">{{ $a->start_date->format('d M') }} – {{ $a->end_date->format('d M Y') }} · {{ (float) $a->working_days }} working days · submitted {{ $a->submitted_at?->diffForHumans() }}</p>
                                    </div>
                                    <x-status :status="$a->status" class="hidden sm:inline-flex" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Approved leave days by month</h3></div>
                <div class="card-body">
                    @php $max = max(1, collect($byMonth)->max('value')); @endphp
                    <div class="flex h-44 items-end gap-3">
                        @foreach ($byMonth as $m)
                            <div class="flex flex-1 flex-col items-center gap-1">
                                <span class="text-xs font-semibold tabular-nums text-gray-700">{{ $m['value'] ?: '' }}</span>
                                <div class="w-full rounded-t-md {{ $loop->last ? 'bg-brand-500' : 'bg-accent-400' }}" style="height: {{ max(4, ($m['value'] / $max) * 140) }}px" title="{{ $m['label'] }}: {{ $m['value'] }} days"></div>
                                <span class="text-xs text-gray-500">{{ $m['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-400">Working days of approved leave, by month the leave starts.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">On leave today &amp; expected returns</h3></div>
                @if ($onLeave->isEmpty())
                    <x-empty icon="users" title="Everyone is in" text="No employees are recorded as on approved leave today." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Employee</th><th>Leave</th><th>Until</th><th>Returns</th></tr></thead>
                            <tbody>
                            @foreach ($onLeave as $a)
                                <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                                    <td class="font-medium text-gray-900">{{ $a->employee_name }}<div class="text-xs font-normal text-gray-500">{{ $a->department }}</div></td>
                                    <td>{{ $a->category->name }}</td>
                                    <td class="whitespace-nowrap">{{ $a->end_date->format('d M') }}</td>
                                    <td class="whitespace-nowrap font-semibold text-accent-700">{{ $a->return_date->format('D d M') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Upcoming approved leave (30 days)</h3></div>
                @if ($upcoming->isEmpty())
                    <x-empty icon="calendar" title="No upcoming leave" text="No approved leave starts in the next 30 days." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Employee</th><th>Leave</th><th>Dates</th><th>Return</th></tr></thead>
                            <tbody>
                            @foreach ($upcoming as $a)
                                <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                                    <td class="font-medium text-gray-900">{{ $a->employee_name }}</td>
                                    <td>{{ $a->category->name }}</td>
                                    <td class="whitespace-nowrap">{{ $a->start_date->format('d M') }} – {{ $a->end_date->format('d M') }}</td>
                                    <td class="whitespace-nowrap">{{ $a->return_date->format('d M') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset

    @isset($contracts)
        <h2 class="mt-10 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">Contracts</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat label="Active contracts" :value="$contracts['active']" icon="briefcase" tone="accent" :href="route('contracts.index')" />
            <x-stat label="Expiring within 90 days" :value="$contracts['within90']" icon="clock" tone="amber" :href="route('contracts.index', ['expiry' => '90'])" />
            <x-stat label="Expiring within 30 days" :value="$contracts['within30']" icon="exclamation" tone="red" :href="route('contracts.index', ['expiry' => '30'])" />
            <x-stat label="Expired" :value="$contracts['expired']" icon="x-circle" tone="red" :href="route('contracts.index', ['expiry' => 'expired'])" />
            <x-stat label="Awaiting decision" :value="$contracts['awaiting']" icon="check-badge" tone="brand" :href="route('contracts.index', ['expiry' => 'awaiting_decision'])" />
        </div>
    @endisset

    @isset($admin)
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">System</h2>
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat label="Active user accounts" :value="$admin['users']" icon="users" tone="accent" :href="route('admin.users.index')" />
            <x-stat label="Deactivated accounts" :value="$admin['inactive']" icon="user-minus" tone="gray" :href="route('admin.users.index', ['active' => 0])" />
            <x-stat label="Last backup" :value="$admin['lastBackup'] ? \Carbon\Carbon::createFromTimestamp($admin['lastBackup']['time'])->diffForHumans() : 'Never'" icon="database" tone="brand" :href="route('admin.backups.index')" />
        </div>
    @endisset

    {{-- Personal section --}}
    @unless ($user->isAdmin())
        <h2 class="mt-10 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">My leave</h2>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-1 lg:grid-cols-1">
                <x-stat label="In progress" :value="$my['pending']" icon="clock" tone="indigo" :href="route('leave.index')" />
                <x-stat label="Needs your attention" :value="$my['returned'] + $my['drafts']" icon="pencil" tone="amber" :href="route('leave.index')" :hint="$my['returned'].' returned · '.$my['drafts'].' drafts'" />
                <x-stat label="Approved this year" :value="$my['approved']" icon="check" tone="accent" :href="route('leave.index', ['status' => 'approved'])" />
                @if ($myContract)
                    <x-stat label="My contract ends" :value="$myContract->end_date->format('d M Y')" icon="briefcase" tone="brand" :href="route('contracts.mine')" :hint="$myContract->remainingLabel()" />
                @endif
            </div>
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3 class="card-title">Recent applications</h3>
                    <a href="{{ route('leave.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
                </div>
                @if ($my['next'])
                    <div class="mx-5 mt-4 flex items-start gap-3 rounded-lg bg-accent-50 px-4 py-3 text-sm text-accent-800 ring-1 ring-accent-200">
                        <x-icon name="sun" class="mt-0.5 size-5 shrink-0" />
                        <p>Your next approved leave: <strong>{{ $my['next']->start_date->format('d M') }} – {{ $my['next']->end_date->format('d M Y') }}</strong>, back on {{ $my['next']->return_date->format('D d M') }}.</p>
                    </div>
                @endif
                @if ($my['recent']->isEmpty())
                    <x-empty icon="calendar" title="No applications yet" text="When you apply for leave it will appear here with its current status.">
                        <a href="{{ route('leave.create') }}" class="btn-primary">Apply for leave</a>
                    </x-empty>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($my['recent'] as $a)
                            <li>
                                <a href="{{ route('leave.show', $a) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-brand-50/40">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">{{ $a->category->name }} <span class="font-normal text-gray-400">{{ $a->reference ?? 'Draft' }}</span></p>
                                        <p class="text-xs text-gray-500">{{ $a->start_date->format('d M') }} – {{ $a->end_date->format('d M Y') }} · {{ (float) $a->working_days }} working days</p>
                                    </div>
                                    <x-status :status="$a->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endunless
</x-layouts.app>
