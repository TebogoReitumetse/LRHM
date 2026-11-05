<x-layouts.app title="My leave applications">
    <x-page-header title="My leave applications" subtitle="Track the status of your applications and see comments from management.">
        <a href="{{ route('leave.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Apply for leave</a>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('leave.index') }}" @class(['badge px-3 py-1', 'bg-gray-900 text-white ring-gray-900' => ! request('status'), 'bg-white text-gray-600 ring-gray-300' => request('status')])>All</a>
        @foreach (\App\Enums\LeaveStatus::cases() as $s)
            <a href="{{ route('leave.index', ['status' => $s->value]) }}" @class(['badge px-3 py-1', 'bg-gray-900 text-white ring-gray-900' => request('status') === $s->value, 'bg-white text-gray-600 ring-gray-300' => request('status') !== $s->value])>{{ $s->label() }}</a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        @if ($applications->isEmpty())
            <x-empty icon="calendar" title="No applications found" text="Leave applications you create will appear here.">
                <a href="{{ route('leave.create') }}" class="btn-primary">Apply for leave</a>
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Leave category</th><th>Dates</th><th>Days</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($applications as $a)
                        <tr>
                            <td class="font-medium whitespace-nowrap text-gray-900">{{ $a->reference ?? '—' }}</td>
                            <td>{{ $a->category->name }}</td>
                            <td class="whitespace-nowrap">{{ $a->start_date->format('d M Y') }} – {{ $a->end_date->format('d M Y') }}</td>
                            <td class="tabular-nums">{{ (float) $a->working_days }}</td>
                            <td class="whitespace-nowrap">{{ $a->submitted_at?->format('d M Y') ?? '—' }}</td>
                            <td><x-status :status="$a->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                @can('update', $a)
                                    <a href="{{ route('leave.edit', $a) }}" class="btn-secondary btn-sm">{{ $a->status === \App\Enums\LeaveStatus::ReturnedForInformation ? 'Respond' : 'Edit' }}</a>
                                @endcan
                                <a href="{{ route('leave.show', $a) }}" class="btn-secondary btn-sm">View</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $applications->links() }}
        @endif
    </div>
</x-layouts.app>
