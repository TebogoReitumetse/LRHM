<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}LivityICT HRMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
@php
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $nav = function (string $route, string $label, string $icon, $pattern = null, $badge = null) {
        $active = request()->routeIs($pattern ?? $route);
        return ['route' => $route, 'label' => $label, 'icon' => $icon, 'active' => $active, 'badge' => $badge];
    };
    $pendingQueue = 0;
    if ($u->isManager()) {
        $pendingQueue = \App\Models\LeaveApplication::status(\App\Enums\LeaveStatus::Submitted, \App\Enums\LeaveStatus::UnderManagerReview)->where('user_id', '!=', $u->id)->count();
    } elseif ($u->isDirector()) {
        $pendingQueue = \App\Models\LeaveApplication::status(\App\Enums\LeaveStatus::PendingDirectorApproval)->where('user_id', '!=', $u->id)->count();
    }

    $sections = [];
    $sections[''] = [$nav('dashboard', 'Dashboard', 'home')];
    $sections['My leave'] = [
        $nav('leave.index', 'My applications', 'calendar',
            request()->route('leave')?->user_id === $u->id ? ['leave.index', 'leave.show', 'leave.edit'] : ['leave.index', 'leave.edit']),
        $nav('leave.create', 'Apply for leave', 'plus'),
    ];
    if ($u->can_view_contract && ! $u->isManager()) {
        $sections['My leave'][] = $nav('contracts.mine', 'My contract', 'briefcase');
    }
    if ($u->isManagement()) {
        $sections['Leave management'] = [
            $nav('approvals.index', $u->isDirector() ? 'Awaiting my decision' : 'Awaiting my review', 'check-badge', 'approvals.*', $pendingQueue ?: null),
            $nav('register.index', 'Leave register', 'clipboard', 'register.*'),
            $nav('reports.index', 'Reports', 'chart', 'reports.*'),
            $nav('absences.index', 'Absence records', 'user-minus', 'absences.*'),
        ];
    }
    if ($u->isManager()) {
        $sections['Contracts'] = [
            $nav('contracts.dashboard', 'Contract dashboard', 'squares'),
            $nav('contracts.index', 'Contract register', 'briefcase', ['contracts.index', 'contracts.show', 'contracts.edit', 'contracts.create', 'contracts.renew']),
        ];
    }
    $admin = [];
    if (Gate::allows('manage-users')) {
        $admin[] = $nav('admin.users.index', 'Users', 'users', 'admin.users.*');
        $admin[] = $nav('admin.departments.index', 'Departments', 'building', 'admin.departments.*');
    }
    if (Gate::allows('manage-settings')) {
        $admin[] = $nav('admin.leave-categories.index', 'Leave categories', 'tag', 'admin.leave-categories.*');
        $admin[] = $nav('admin.holidays.index', 'Public holidays', 'sun', 'admin.holidays.*');
        $admin[] = $nav('admin.settings.edit', 'Notification settings', 'cog', 'admin.settings.*');
        $admin[] = $nav('admin.backups.index', 'Backups', 'database', 'admin.backups.*');
    }
    if (Gate::allows('view-audit')) {
        $admin[] = $nav('admin.audit.index', 'Audit trail', 'shield', 'admin.audit.*');
    }
    if ($admin) {
        $sections['Administration'] = $admin;
    }
@endphp

<div x-data="{ open: false }" class="min-h-full">
    {{-- Mobile overlay --}}
    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/60 lg:hidden" @click="open = false"></div>

    {{-- Sidebar --}}
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-gray-950 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
            <img src="{{ asset('images/logo.png') }}" alt="LivityICT logo" class="size-10 rounded-full">
            <div class="leading-tight">
                <div class="text-sm font-bold text-white">Livity<span class="text-brand-400">ICT</span></div>
                <div class="text-[11px] font-medium tracking-wide text-accent-400 uppercase">HR Management</div>
            </div>
            <button type="button" class="ml-auto text-gray-400 hover:text-white lg:hidden" @click="open = false" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @foreach ($sections as $heading => $links)
                @if ($heading)<div class="nav-section">{{ $heading }}</div>@else<div class="h-4"></div>@endif
                <ul class="space-y-1">
                    @foreach ($links as $l)
                        <li>
                            <a href="{{ route($l['route']) }}" @class(['nav-link', 'active' => $l['active']])>
                                <x-icon :name="$l['icon']" class="size-5 shrink-0" />
                                <span class="flex-1">{{ $l['label'] }}</span>
                                @if ($l['badge'])
                                    <span class="rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $l['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-accent-600 text-sm font-bold text-white">{{ $u->initials() }}</div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-white">{{ $u->name }}</div>
                    <div class="truncate text-xs text-gray-400">{{ $u->role->label() }}</div>
                </div>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-md p-1.5 text-gray-600 hover:bg-gray-100 lg:hidden" @click="open = true" aria-label="Open menu">
                <x-icon name="menu" class="size-6" />
            </button>
            <img src="{{ asset('images/logo.png') }}" alt="" class="size-8 rounded-full lg:hidden">
            <div class="min-w-0 flex-1">
                <p class="hidden truncate text-sm text-gray-500 sm:block">{{ now()->format('l, j F Y') }}</p>
            </div>

            <a href="{{ route('notifications.index') }}" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700" aria-label="Notifications">
                <x-icon name="bell" class="size-6" />
                @if ($unread)
                    <span class="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-brand-500 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </a>

            <div x-data="{ menu: false }" class="relative">
                <button type="button" @click="menu = !menu" @click.outside="menu = false" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-gray-100">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white">{{ $u->initials() }}</span>
                    <span class="hidden text-sm font-medium text-gray-700 sm:block">{{ $u->name }}</span>
                    <x-icon name="chevron-down" class="size-4 text-gray-400" />
                </button>
                <div x-cloak x-show="menu" x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <p class="text-sm font-medium">{{ $u->name }}</p>
                        <p class="text-xs text-gray-500">{{ $u->employee_number }} · {{ $u->role->label() }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><x-icon name="key" class="size-4" /> Profile & password</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>

        <footer class="px-4 pb-6 text-center text-xs text-gray-400 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} LivityICT (Pty) Ltd · Human Resource Management System · Personal information is processed in line with POPIA.
        </footer>
    </div>
</div>
</body>
</html>
