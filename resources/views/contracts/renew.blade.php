@php use App\Models\Contract; $c = $contract; @endphp
<x-layouts.app title="Record renewal">
    <x-page-header title="Record renewal or extension" :subtitle="$c->full_name.' · current contract '.$c->reference.' ends '.$c->end_date->format('d M Y')" :back="route('contracts.show', $c)" />

    <div class="mb-6 rounded-xl bg-sky-50 p-4 text-sm text-sky-900 ring-1 ring-sky-200">
        A new contract version is created with the new dates. The current contract ({{ $c->reference }}) and its documents stay in the history
        as <strong>Renewed</strong> (new contract) or <strong>Superseded</strong> (extension / addendum to the same contract).
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ route('contracts.renew', $c) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3"
          x-data="{ kind: '{{ old('renewal_kind', 'renewal') }}' }">
        @csrf
        <div class="card xl:col-span-2">
            <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Type of change" name="renewal_kind" required class="sm:col-span-2">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg p-3 ring-1" :class="kind==='renewal' ? 'ring-brand-400 bg-brand-50' : 'ring-gray-200'">
                            <input type="radio" name="renewal_kind" value="renewal" x-model="kind" class="text-brand-500 focus:ring-brand-500"> Renewal (new contract)
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg p-3 ring-1" :class="kind==='extension' ? 'ring-brand-400 bg-brand-50' : 'ring-gray-200'">
                            <input type="radio" name="renewal_kind" value="extension" x-model="kind" class="text-brand-500 focus:ring-brand-500"> Extension / addendum
                        </label>
                    </div>
                </x-field>
                <x-field label="Contract type" name="contract_type" required>
                    <select name="contract_type" class="form-input">@foreach (Contract::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('contract_type', $c->contract_type) === $k)>{{ $v }}</option>@endforeach</select>
                </x-field>
                <x-field label="Job title" name="job_title"><input name="job_title" value="{{ old('job_title', $c->job_title) }}" class="form-input"></x-field>
                <x-field label="New start date" name="start_date" required><input type="date" name="start_date" value="{{ old('start_date', $c->end_date->copy()->addDay()->toDateString()) }}" class="form-input" required></x-field>
                <x-field label="New end date" name="end_date" required><input type="date" name="end_date" value="{{ old('end_date') }}" class="form-input" required></x-field>
                <x-field label="Date signed" name="date_signed"><input type="date" name="date_signed" value="{{ old('date_signed') }}" class="form-input"></x-field>
                <x-field label="Commencement date (if different)" name="commencement_date"><input type="date" name="commencement_date" value="{{ old('commencement_date') }}" class="form-input"></x-field>
                <x-field label="Decision made by" name="decided_by_name" required help="Name and role of the person who approved the renewal."><input name="decided_by_name" value="{{ old('decided_by_name') }}" class="form-input" required></x-field>
                <x-field label="Decision date" name="decision_date" required><input type="date" name="decision_date" max="{{ today()->toDateString() }}" value="{{ old('decision_date') }}" class="form-input" required></x-field>
                <x-field label="Notes" name="notes" class="sm:col-span-2"><textarea name="notes" rows="3" class="form-input">{{ old('notes') }}</textarea></x-field>
            </div>
        </div>
        <div class="card self-start">
            <div class="card-body space-y-4">
                <x-field label="Signed renewal agreement / addendum" name="document">
                    <input type="file" name="document" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                </x-field>
                <label class="flex items-start gap-3 rounded-lg bg-accent-50 p-3 text-sm text-accent-900 ring-1 ring-accent-200">
                    <input type="checkbox" name="dates_verified" value="1" class="form-check mt-0.5" required>
                    <span>I have verified the new dates against the signed renewal agreement or addendum.</span>
                </label>
                @error('dates_verified')<p class="form-error">{{ $message }}</p>@enderror
                <button class="btn-accent w-full"><x-icon name="check" class="size-4" /> Record renewal</button>
            </div>
        </div>
    </form>
</x-layouts.app>
