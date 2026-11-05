@php
    use App\Enums\ContractStatus; use App\Models\Contract;
    $editing = $contract->exists;
    $statusValue = in_array($contract->status, ContractStatus::monitored(), true) ? 'active' : $contract->status?->value;
@endphp
<x-layouts.app :title="$editing ? 'Edit contract' : 'New contract'">
    <x-page-header :title="$editing ? 'Edit contract '.$contract->reference : 'New contract record'"
                   subtitle="Verify every date against the signed contract before activating the record."
                   :back="$editing ? route('contracts.show', $contract) : route('contracts.index')" />

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('contracts.update', $contract) : route('contracts.store') }}"
          x-data="{
              people: {{ Js::from($people->keyBy('id')) }},
              start: '{{ old('start_date', $contract->start_date?->toDateString()) }}',
              end: '{{ old('end_date', $contract->end_date?->toDateString()) }}',
              fill(id) {
                  const p = this.people[id]; if (!p) return;
                  this.$refs.name.value = p.name; this.$refs.num.value = p.employee_number;
                  this.$refs.title.value = p.job_title || ''; this.$refs.dept.value = p.department_id || '';
              },
              get duration() {
                  if (!this.start || !this.end || this.end < this.start) return '—';
                  const s = new Date(this.start), e = new Date(this.end); e.setDate(e.getDate() + 1);
                  let m = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth());
                  let anchor = new Date(s); anchor.setMonth(anchor.getMonth() + m);
                  if (anchor > e) { m--; anchor = new Date(s); anchor.setMonth(anchor.getMonth() + m); }
                  const d = Math.round((e - anchor) / 86400000);
                  return [m ? m + (m === 1 ? ' month' : ' months') : '', (d || !m) ? d + (d === 1 ? ' day' : ' days') : ''].filter(Boolean).join(', ');
              }
          }" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        @csrf @if ($editing) @method('PUT') @endif

        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Employee / contractor</h2></div>
                <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Linked user account" name="user_id" required class="sm:col-span-2" help="The person who will be able to view this contract (if enabled on their account).">
                        <select name="user_id" class="form-input" required @change="fill($event.target.value)">
                            <option value="">Select…</option>
                            @foreach ($people as $p)<option value="{{ $p->id }}" @selected(old('user_id', $contract->user_id) == $p->id)>{{ $p->name }} ({{ $p->employee_number }}){{ $p->employment_type === 'contractor' ? ' · contractor' : '' }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Full name" name="full_name" required><input x-ref="name" name="full_name" value="{{ old('full_name', $contract->full_name) }}" class="form-input" required></x-field>
                    <x-field label="Employee number / identifier" name="employee_number" required><input x-ref="num" name="employee_number" value="{{ old('employee_number', $contract->employee_number) }}" class="form-input" required></x-field>
                    <x-field label="Job title" name="job_title"><input x-ref="title" name="job_title" value="{{ old('job_title', $contract->job_title) }}" class="form-input"></x-field>
                    <x-field label="Department" name="department_id">
                        <select x-ref="dept" name="department_id" class="form-input"><option value="">—</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $contract->department_id) == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Contract terms</h2></div>
                <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Contract type" name="contract_type" required>
                        <select name="contract_type" class="form-input">
                            @foreach (Contract::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('contract_type', $contract->contract_type) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Date signed" name="date_signed"><input type="date" name="date_signed" value="{{ old('date_signed', $contract->date_signed?->toDateString()) }}" class="form-input"></x-field>
                    <x-field label="Contract start date" name="start_date" required><input type="date" name="start_date" x-model="start" class="form-input" required></x-field>
                    <x-field label="Contract end date" name="end_date" required><input type="date" name="end_date" x-model="end" :min="start" class="form-input" required></x-field>
                    <x-field label="Actual commencement date" name="commencement_date" help="Only if different from the contract start date.">
                        <input type="date" name="commencement_date" value="{{ old('commencement_date', $contract->commencement_date?->toDateString()) }}" class="form-input">
                    </x-field>
                    <div>
                        <span class="form-label">Contract duration</span>
                        <div class="flex h-[38px] items-center rounded-lg bg-brand-50 px-3 text-sm font-semibold text-brand-800 ring-1 ring-brand-200" x-text="duration"></div>
                        <p class="form-help">Calculated automatically from the start and end dates.</p>
                    </div>
                    <x-field label="Notes" name="notes" class="sm:col-span-2"><textarea name="notes" rows="2" class="form-input">{{ old('notes', $contract->notes) }}</textarea></x-field>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Documents</h2></div>
                <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Signed contract" name="signed_contract" :help="$editing && $contract->currentDocument() ? 'Uploading replaces the current signed copy; the old one is kept in the history.' : 'PDF, Word or image.'">
                        <input type="file" name="signed_contract" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                    </x-field>
                    <x-field label="Supporting documents" name="supporting" help="Extensions, addenda, etc. Up to 5 files.">
                        <input type="file" name="supporting[]" multiple class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700">
                    </x-field>
                </div>
            </div>
        </div>

        <div class="card self-start xl:sticky xl:top-24">
            <div class="card-header"><h2 class="card-title">Status &amp; verification</h2></div>
            <div class="card-body space-y-4">
                <x-field label="Contract status" name="status" required help="Active contracts automatically move to Due to Expire and Expired based on their end date.">
                    <select name="status" class="form-input">
                        @foreach (ContractStatus::manualOptions() as $k => $v)<option value="{{ $k }}" @selected(old('status', $statusValue) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </x-field>
                <label class="flex items-start gap-3 rounded-lg bg-accent-50 p-3 text-sm text-accent-900 ring-1 ring-accent-200">
                    <input type="hidden" name="dates_verified" value="0">
                    <input type="checkbox" name="dates_verified" value="1" class="form-check mt-0.5 text-accent-600 focus:ring-accent-500" @checked(old('dates_verified', $contract->dates_verified))>
                    <span>I have verified the contract dates against the signed contractual documentation.</span>
                </label>
                @error('dates_verified')<p class="form-error">{{ $message }}</p>@enderror
                <button class="btn-primary w-full">{{ $editing ? 'Save changes' : 'Create contract' }}</button>
                <p class="text-xs text-gray-500">The system never renews, ends or changes contract terms automatically. All changes are recorded in the audit trail.</p>
            </div>
        </div>
    </form>
</x-layouts.app>
