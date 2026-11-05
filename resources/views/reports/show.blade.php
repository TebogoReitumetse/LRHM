<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$subtitle" :back="route('reports.index')">
        <a href="{{ route('reports.run', $query + ['format' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel (CSV)</a>
        <a href="{{ route('reports.run', $query + ['format' => 'pdf']) }}" class="btn-secondary"><x-icon name="document" class="size-4" /> PDF</a>
        <a href="{{ route('reports.run', $query + ['format' => 'print']) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Applications" :value="$applications->count()" icon="clipboard" tone="gray" />
        <x-stat label="Employees" :value="$applications->pluck('user_id')->unique()->count()" icon="users" tone="sky" />
        <x-stat label="Working days requested" :value="(float) $applications->sum('working_days')" icon="calendar" tone="brand" />
        <x-stat label="Approved working days" :value="(float) $applications->where('status', \App\Enums\LeaveStatus::Approved)->sum('working_days')" icon="check" tone="accent" />
    </div>

    @if ($summary)
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($summary as $heading => $rows)
                <div class="card overflow-hidden">
                    <div class="card-header"><h2 class="card-title">{{ ucfirst($heading) }}</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th></th><th class="text-right">Applications</th><th class="text-right">Employees</th><th class="text-right">Days</th><th class="text-right">Approved days</th></tr></thead>
                            <tbody>
                            @forelse ($rows as $label => $r)
                                <tr><td class="font-medium text-gray-900">{{ $label }}</td><td class="text-right tabular-nums">{{ $r['applications'] }}</td><td class="text-right tabular-nums">{{ $r['employees'] }}</td><td class="text-right tabular-nums">{{ $r['days'] }}</td><td class="text-right tabular-nums font-semibold text-accent-700">{{ $r['approved_days'] }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-gray-500">No data</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Applications</h2></div>
        @if ($applications->isEmpty())
            <x-empty icon="search" title="No applications" text="No applications match these report options." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Employee</th><th>Department</th><th>Category</th><th>Dates</th><th>Days</th><th>Submitted</th><th>Status</th><th>Decision</th></tr></thead>
                    <tbody>
                    @foreach ($applications as $a)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                            <td class="font-medium whitespace-nowrap">{{ $a->reference }}</td>
                            <td>{{ $a->employee_name }}</td>
                            <td>{{ $a->department }}</td>
                            <td>{{ $a->category->name }}</td>
                            <td class="whitespace-nowrap">{{ $a->start_date->format('d M Y') }} – {{ $a->end_date->format('d M Y') }}</td>
                            <td class="tabular-nums">{{ (float) $a->working_days }}</td>
                            <td class="whitespace-nowrap">{{ $a->submitted_at?->format('d M Y') }}</td>
                            <td><x-status :status="$a->status" /></td>
                            <td class="text-xs whitespace-nowrap">{{ $a->director?->name }}<div class="text-gray-500">{{ $a->decided_at?->format('d M Y H:i') }}</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
