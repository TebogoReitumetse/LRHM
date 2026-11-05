@php use App\Enums\AbsenceClassification; use App\Models\AbsenceRecord; $editing = $record->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit absence record' : 'Record absence'">
    <x-page-header :title="$editing ? 'Edit attendance record' : 'Record an absence or late arrival'" :back="$editing ? route('absences.show', $record) : route('absences.index')" />

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('absences.update', $record) : route('absences.store') }}"
          x-data="{ type: '{{ old('record_type', $record->record_type) }}', cls: '{{ old('classification', $record->classification?->value) }}' }"
          class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card xl:col-span-2">
            <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Employee" name="user_id" required class="sm:col-span-2">
                    <select name="user_id" class="form-input" required>
                        <option value="">Select employee…</option>
                        @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('user_id', $record->user_id) == $e->id)>{{ $e->name }} ({{ $e->employee_number }})</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Date" name="date" required>
                    <input type="date" name="date" max="{{ today()->toDateString() }}" value="{{ old('date', $record->date?->toDateString()) }}" class="form-input" required>
                </x-field>
                <x-field label="Type of record" name="record_type" required>
                    <select name="record_type" x-model="type" class="form-input">
                        @foreach (AbsenceRecord::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Arrival time" name="arrival_time" x-show="type === 'late_arrival'" x-cloak>
                    <input type="time" name="arrival_time" value="{{ old('arrival_time', $record->arrival_time ? substr($record->arrival_time, 0, 5) : '') }}" class="form-input" :disabled="type !== 'late_arrival'">
                </x-field>
                <x-field label="Reason provided (if any)" name="reason" class="sm:col-span-2">
                    <textarea name="reason" rows="2" class="form-input">{{ old('reason', $record->reason) }}</textarea>
                </x-field>
                <x-field label="Classification" name="classification" required class="sm:col-span-2">
                    <select name="classification" x-model="cls" class="form-input">
                        @foreach (AbsenceClassification::options() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </x-field>
                <div x-show="cls === 'reviewed_classified'" x-cloak class="space-y-4 rounded-lg bg-purple-50/60 p-4 ring-1 ring-purple-200 sm:col-span-2">
                    <p class="text-sm text-purple-900">Record the outcome only after considering the circumstances and the applicable company policies.</p>
                    <x-field label="Management outcome" name="management_outcome" required>
                        <select name="management_outcome" class="form-input" :disabled="cls !== 'reviewed_classified'">
                            <option value="">Select…</option>
                            @foreach (AbsenceRecord::OUTCOMES as $k => $v)<option value="{{ $k }}" @selected(old('management_outcome', $record->management_outcome) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <x-field label="Management comments" name="management_comment" class="sm:col-span-2">
                    <textarea name="management_comment" rows="3" class="form-input">{{ old('management_comment', $record->management_comment) }}</textarea>
                </x-field>
            </div>
        </div>
        <div class="card self-start">
            <div class="card-body space-y-4">
                <x-field label="Follow-up status" name="follow_up_status" required>
                    <select name="follow_up_status" class="form-input">
                        @foreach (AbsenceRecord::FOLLOW_UP as $k => $v)<option value="{{ $k }}" @selected(old('follow_up_status', $record->follow_up_status) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Supporting document" name="document" :help="$record->document_name ? 'Current: '.$record->document_name.' (uploading replaces it)' : 'Optional: PDF, Word or image.'">
                    <input type="file" name="document" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                </x-field>
                <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <strong>Important:</strong> this record does not issue warnings, deduct salary or start disciplinary procedures.
                    Any employment-related action must follow LivityICT's approved policies and fair process.
                </div>
                <button class="btn-primary w-full">Save record</button>
            </div>
        </div>
    </form>
</x-layouts.app>
