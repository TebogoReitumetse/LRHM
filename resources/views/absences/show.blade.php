@php use App\Models\AbsenceRecord; $r = $record; @endphp
<x-layouts.app title="Absence record">
    <x-page-header :title="AbsenceRecord::TYPES[$r->record_type].' · '.$r->user->name" :subtitle="$r->date->format('l, d F Y')" :back="route('absences.index')">
        <a href="{{ route('absences.edit', $r) }}" class="btn-primary"><x-icon name="pencil" class="size-4" /> Update / follow up</a>
    </x-page-header>

    @if ($leave)
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-accent-50 px-4 py-3 text-sm text-accent-800 ring-1 ring-accent-200">
            <x-icon name="info" class="size-5" />
            <span>{{ $r->user->name }} has <strong>approved leave</strong> covering this date:
                <a class="underline" href="{{ route('leave.show', $leave) }}">{{ $leave->reference }}</a> ({{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}).</span>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <dl class="dl-grid">
                <div><dt>Employee</dt><dd>{{ $r->user->name }} ({{ $r->user->employee_number }})</dd></div>
                <div><dt>Department</dt><dd>{{ $r->user->department?->name ?? '—' }}</dd></div>
                <div><dt>Date</dt><dd>{{ $r->date->format('d M Y') }}</dd></div>
                <div><dt>Type</dt><dd>{{ AbsenceRecord::TYPES[$r->record_type] }}{{ $r->arrival_time ? ' · arrived '.substr($r->arrival_time, 0, 5) : '' }}</dd></div>
                <div><dt>Classification</dt><dd><span class="badge {{ $r->classification->badge() }}">{{ $r->classification->label() }}</span></dd></div>
                <div><dt>Follow-up status</dt><dd>{{ AbsenceRecord::FOLLOW_UP[$r->follow_up_status] }}</dd></div>
                @if ($r->management_outcome)
                    <div><dt>Management outcome</dt><dd>{{ AbsenceRecord::OUTCOMES[$r->management_outcome] }}</dd></div>
                    <div><dt>Reviewed by</dt><dd>{{ $r->reviewer?->name }} · {{ $r->reviewed_at?->format('d M Y H:i') }}</dd></div>
                @endif
                <div class="sm:col-span-2"><dt>Reason provided</dt><dd class="whitespace-pre-line">{{ $r->reason ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt>Management comments</dt><dd class="whitespace-pre-line">{{ $r->management_comment ?: '—' }}</dd></div>
                <div><dt>Supporting document</dt><dd>@if ($r->document_path)<a href="{{ route('absences.document', $r) }}" class="text-brand-700 underline">{{ $r->document_name }}</a>@else — @endif</dd></div>
                <div><dt>Recorded by</dt><dd>{{ $r->recorder->name }} · {{ $r->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </div>
    </div>
</x-layouts.app>
