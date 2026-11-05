@php use App\Enums\AbsenceClassification; use App\Models\AbsenceRecord; @endphp
<x-layouts.app title="Absence records">
    <x-page-header title="Absence & late-arrival records" subtitle="Operational record for follow-up. The system does not classify misconduct or start disciplinary action.">
        <a href="{{ route('absences.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Record absence</a>
    </x-page-header>

    <form method="GET" class="card mb-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div><label class="form-label">Employee</label>
            <select name="employee" class="form-input"><option value="">All</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(($filters['employee'] ?? '') == $e->id)>{{ $e->name }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Record type</label>
            <select name="type" class="form-input"><option value="">All</option>
                @foreach (AbsenceRecord::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Classification</label>
            <select name="classification" class="form-input"><option value="">All</option>
                @foreach (AbsenceClassification::options() as $k => $v)<option value="{{ $k }}" @selected(($filters['classification'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Follow-up</label>
            <select name="follow_up" class="form-input"><option value="">All</option>
                @foreach (AbsenceRecord::FOLLOW_UP as $k => $v)<option value="{{ $k }}" @selected(($filters['follow_up'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input"></div>
        <div><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input"></div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
            <button class="btn-primary"><x-icon name="filter" class="size-4" /> Filter</button>
            <a href="{{ route('absences.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($records->isEmpty())
            <x-empty icon="user-minus" title="No records" text="Absences and late arrivals you record will appear here." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Employee</th><th>Type</th><th>Classification</th><th>Follow-up</th><th>Approved leave?</th><th>Recorded by</th></tr></thead>
                    <tbody>
                    @foreach ($records as $r)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('absences.show', $r) }}'">
                            <td class="whitespace-nowrap font-medium text-gray-900">{{ $r->date->format('D d M Y') }}</td>
                            <td><div class="font-medium text-gray-900">{{ $r->user->name }}</div><div class="text-xs text-gray-500">{{ $r->user->department?->name }}</div></td>
                            <td class="whitespace-nowrap">{{ AbsenceRecord::TYPES[$r->record_type] }}@if ($r->arrival_time)<div class="text-xs text-gray-500">Arrived {{ substr($r->arrival_time, 0, 5) }}</div>@endif</td>
                            <td><span class="badge {{ $r->classification->badge() }}">{{ $r->classification->label() }}</span>
                                @if ($r->management_outcome)<div class="mt-1 text-xs text-gray-500">{{ AbsenceRecord::OUTCOMES[$r->management_outcome] }}</div>@endif</td>
                            <td><span @class(['badge', 'bg-amber-50 text-amber-800 ring-amber-200' => $r->follow_up_status === 'open', 'bg-sky-50 text-sky-700 ring-sky-200' => $r->follow_up_status === 'in_progress', 'bg-gray-100 text-gray-600 ring-gray-300' => $r->follow_up_status === 'closed'])>{{ AbsenceRecord::FOLLOW_UP[$r->follow_up_status] }}</span></td>
                            <td class="text-xs">@if (isset($leaveOnDate[$r->id]))<span class="font-medium text-accent-700">Yes · {{ $leaveOnDate[$r->id]->reference }}</span>@else<span class="text-gray-400">No</span>@endif</td>
                            <td class="text-xs text-gray-500">{{ $r->recorder->name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $records->links() }}
        @endif
    </div>
</x-layouts.app>
