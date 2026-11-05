<x-layouts.app title="Audit trail">
    <x-page-header title="Audit trail" subtitle="Who created, changed, reviewed or decided records, and when." />

    <form method="GET" class="card mb-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2"><label class="form-label">Search description</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-input"></div>
        <div><label class="form-label">User</label>
            <select name="user" class="form-input"><option value="">All</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Event</label>
            <select name="event" class="form-input"><option value="">All</option>
                @foreach (['leave.' => 'Leave workflow', 'contract.' => 'Contract activity', 'auth.' => 'Sign-in & passwords', 'created' => 'Record created', 'updated' => 'Record updated', 'deleted' => 'Record deleted', 'export.' => 'Exports', 'settings.' => 'Settings', 'system.' => 'Backups'] as $k => $v)
                    <option value="{{ $k }}" @selected(($filters['event'] ?? '') === $k)>{{ $v }}</option>
                @endforeach
            </select></div>
        <div><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input"></div>
        <div><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input"></div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6"><button class="btn-primary">Filter</button><a href="{{ route('admin.audit.index') }}" class="btn-secondary">Reset</a></div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>When</th><th>User</th><th>Event</th><th>Record</th><th>Description &amp; changes</th><th>IP</th></tr></thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="whitespace-nowrap">{{ $log->user->name }}</td>
                        <td><span class="badge bg-gray-100 font-mono text-gray-700 ring-gray-300">{{ $log->event }}</span></td>
                        <td class="text-xs whitespace-nowrap">{{ $log->subjectLabel() }}{{ $log->auditable_id ? ' #'.$log->auditable_id : '' }}</td>
                        <td class="max-w-xl text-xs">
                            <p class="text-gray-800">{{ $log->description }}</p>
                            @if ($log->event === 'updated' && $log->new_values)
                                <details class="mt-1 text-gray-500"><summary class="cursor-pointer">{{ count($log->new_values) }} field(s) changed</summary>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($log->new_values as $k => $v)
                                            <li><span class="font-medium">{{ str_replace('_', ' ', $k) }}:</span> <span class="text-red-600 line-through">{{ \Illuminate\Support\Str::limit(is_scalar($log->old_values[$k] ?? null) ? (string) $log->old_values[$k] : '—', 60) }}</span> → <span class="text-accent-700">{{ \Illuminate\Support\Str::limit(is_scalar($v) ? (string) $v : json_encode($v), 60) }}</span></li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </td>
                        <td class="font-mono text-xs text-gray-400">{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</x-layouts.app>
