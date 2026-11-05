<x-layouts.app :title="auth()->user()->isDirector() ? 'Awaiting my decision' : 'Awaiting my review'">
    <x-page-header :title="auth()->user()->isDirector() ? 'Applications awaiting your decision' : 'Applications awaiting your review'"
                   :subtitle="auth()->user()->isDirector() ? 'Reviewed and forwarded by the Manager. You make the final decision.' : 'Review operational requirements, then forward to the Director or return for more information.'" />

    <div class="card overflow-hidden">
        @if ($applications->isEmpty())
            <x-empty icon="check" title="You're all caught up" text="There are no applications waiting for you." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Employee</th><th>Category</th><th>Dates</th><th>Days</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($applications as $a)
                        <tr>
                            <td class="font-medium whitespace-nowrap">{{ $a->reference }}</td>
                            <td><div class="font-medium text-gray-900">{{ $a->employee_name }}</div><div class="text-xs text-gray-500">{{ $a->department }}</div></td>
                            <td>{{ $a->category->name }}</td>
                            <td class="whitespace-nowrap">{{ $a->start_date->format('d M') }} – {{ $a->end_date->format('d M Y') }}</td>
                            <td class="tabular-nums">{{ (float) $a->working_days }}</td>
                            <td class="whitespace-nowrap" title="{{ $a->submitted_at?->format('d M Y H:i') }}">{{ $a->submitted_at?->diffForHumans() }}</td>
                            <td><x-status :status="$a->status" /></td>
                            <td class="text-right"><a href="{{ route('leave.show', $a) }}" class="btn-primary btn-sm">{{ auth()->user()->isDirector() ? 'Decide' : 'Review' }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $applications->links() }}
        @endif
    </div>

    @if ($returned->isNotEmpty())
        <div class="card mt-6 overflow-hidden">
            <div class="card-header"><h2 class="card-title">Returned for information (waiting on employee)</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Employee</th><th>Category</th><th>Returned</th><th>Requested</th></tr></thead>
                    <tbody>
                    @foreach ($returned as $a)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                            <td class="font-medium">{{ $a->reference }}</td>
                            <td>{{ $a->employee_name }}</td>
                            <td>{{ $a->category->name }}</td>
                            <td class="whitespace-nowrap">{{ $a->returned_at?->diffForHumans() }}</td>
                            <td class="max-w-md truncate text-gray-500">{{ $a->return_reason }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
