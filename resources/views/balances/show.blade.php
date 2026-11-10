@php use App\Services\LeaveBalance; @endphp
<x-layouts.app :title="'Leave balances · '.$employee->name">
    <x-page-header :title="$employee->name" :back="route('balances.index')"
        :subtitle="collect([$employee->employee_number, $employee->job_title, $employee->department?->name, $employee->isContractor() ? 'Contractor' : null])->filter()->implode(' · ')
            .' · Employment start: '.($employee->employment_start_date?->format('d M Y') ?? 'not recorded (cycles start 1 January)')" />

    @if ($balances->isEmpty())
        <div class="card p-5 text-sm text-gray-500">No leave entitlements apply to this account and no leave has been taken this year.</div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($balances as $b)
                <x-leave-balance-card :balance="$b" />
            @endforeach
        </div>
    @endif

    @if ($applications !== null)
        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Leave counted in the current cycles</h2></div>
            @if ($applications->isEmpty())
                <x-empty icon="calendar" title="No leave yet" text="No approved or pending leave in the current cycles." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Category</th><th>Dates</th><th>Working days</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($applications as $a)
                            <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                                <td class="font-medium text-gray-900">{{ $a->reference }}</td>
                                <td>{{ $a->category->name }}</td>
                                <td class="whitespace-nowrap">{{ $a->start_date->format('d M Y') }} – {{ $a->end_date->format('d M Y') }}</td>
                                <td class="tabular-nums">{{ LeaveBalance::days((float) $a->working_days) }}</td>
                                <td><x-status :status="$a->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
