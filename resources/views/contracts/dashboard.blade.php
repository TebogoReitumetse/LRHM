<x-layouts.app title="Contract dashboard">
    <x-page-header title="Contract management" subtitle="Monitor contract periods, upcoming expiry dates and renewal decisions.">
        <form method="POST" action="{{ route('contracts.run-monitor') }}">@csrf
            <button class="btn-secondary" title="Runs automatically every day at 07:00"><x-icon name="refresh" class="size-4" /> Run expiry check now</button>
        </form>
        <a href="{{ route('contracts.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New contract</a>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total active contracts" :value="$stats['active']" icon="briefcase" tone="accent" :href="route('contracts.index')" />
        <x-stat label="Expiring within 90 days" :value="$stats['within90']" icon="clock" tone="amber" :href="route('contracts.index', ['expiry' => '90'])" />
        <x-stat label="Expiring within 60 days" :value="$stats['within60']" icon="clock" tone="brand" :href="route('contracts.index', ['expiry' => '60'])" />
        <x-stat label="Expiring within 30 days" :value="$stats['within30']" icon="exclamation" tone="red" :href="route('contracts.index', ['expiry' => '30'])" />
        <x-stat label="Expiring this month" :value="$stats['this_month']" icon="calendar" tone="sky" :href="route('contracts.index', ['expiry' => 'this_month'])" />
        <x-stat label="Expired" :value="$stats['expired']" icon="x-circle" tone="red" :href="route('contracts.index', ['expiry' => 'expired'])" />
        <x-stat label="Awaiting renewal decision" :value="$stats['awaiting']" icon="check-badge" tone="indigo" :href="route('contracts.index', ['expiry' => 'awaiting_decision'])" />
        <x-stat label="Renewals / extensions completed" :value="$stats['renewed']" icon="refresh" tone="gray" :href="route('contracts.index', ['history' => 1, 'status' => 'renewed'])" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Expiring within 90 days</h2>
                <span class="text-xs text-gray-500">Reminders at {{ implode(', ', $reminderDays) }} days before expiry</span>
            </div>
            @if ($expiring->isEmpty())
                <x-empty icon="check" title="Nothing expiring soon" text="No active contracts end in the next 90 days." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Name</th><th>Type</th><th>End date</th><th>Remaining</th><th>Decision</th></tr></thead>
                        <tbody>
                        @foreach ($expiring as $c)
                            <tr class="cursor-pointer" onclick="location.href='{{ route('contracts.show', $c) }}'">
                                <td><div class="font-medium text-gray-900">{{ $c->full_name }}</div><div class="text-xs text-gray-500">{{ $c->employee_number }} · {{ $c->department?->name }}</div></td>
                                <td class="text-xs">{{ $c->typeLabel() }}</td>
                                <td class="whitespace-nowrap">{{ $c->end_date->format('d M Y') }}</td>
                                <td class="whitespace-nowrap {{ $c->urgencyClass() }}">{{ $c->daysRemaining() }} days</td>
                                <td>{!! $c->renewal_decision ? '<span class="badge bg-accent-50 text-accent-800 ring-accent-200">Recorded</span>' : '<span class="badge bg-amber-50 text-amber-800 ring-amber-200">Awaiting</span>' !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title text-red-700">Expired, no decision recorded</h2></div>
                @if ($expired->isEmpty())
                    <p class="card-body text-sm text-gray-500">No unresolved expired contracts.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($expired as $c)
                            <li><a href="{{ route('contracts.show', $c) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-red-50/50">
                                <span class="text-sm font-medium">{{ $c->full_name }}</span>
                                <span class="text-xs text-red-600">{{ $c->remainingLabel() }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Recent reminder activity</h2></div>
                @if ($recentReminders->isEmpty())
                    <p class="card-body text-sm text-gray-500">No reminders generated yet.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($recentReminders as $r)
                            <li class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('contracts.show', $r->contract) }}" class="font-medium hover:text-brand-700">{{ $r->contract->full_name }}</a>
                                    <span @class(['badge', 'bg-accent-50 text-accent-800 ring-accent-200' => $r->status === 'sent', 'bg-red-50 text-red-700 ring-red-200' => $r->status === 'failed', 'bg-gray-100 text-gray-600 ring-gray-300' => ! in_array($r->status, ['sent', 'failed'])])>{{ ucfirst($r->status) }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ $r->label() }} · {{ $r->generated_at?->format('d M Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
