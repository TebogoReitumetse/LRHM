@php
    $editing = $application->exists;
    $returned = $application->status === \App\Enums\LeaveStatus::ReturnedForInformation;
    $catMap = $categories->mapWithKeys(fn ($c) => [$c->id => ['doc' => $c->requires_document, 'reason' => $c->requires_reason, 'desc' => $c->description]]);
@endphp
<x-layouts.app :title="$editing ? 'Edit leave application' : 'Apply for leave'">
    <x-page-header :title="$editing ? 'Leave application '.($application->reference ?? '(draft)') : 'Leave application form'"
                   subtitle="Complete all required fields. Working days are calculated automatically from the working calendar."
                   :back="$editing ? route('leave.show', $application) : route('leave.index')" />

    @if ($returned)
        <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-semibold">Management has asked for more information</p>
            <p class="mt-1">{{ $application->return_reason }}</p>
            <p class="mt-2 text-xs">Update your application or upload the requested documents, then accept the declaration and resubmit.</p>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('leave.update', $application) : route('leave.store') }}"
          x-data="leaveForm({
              url: '{{ route('leave.calculate') }}',
              start: '{{ old('start_date', $application->start_date?->toDateString()) }}',
              end: '{{ old('end_date', $application->end_date?->toDateString()) }}',
              returnDate: '{{ old('return_date', $application->return_date?->toDateString()) }}',
              days: {{ (float) $application->working_days }},
              category: '{{ old('leave_category_id', $application->leave_category_id) }}',
              categories: {{ Js::from($catMap) }},
          })" x-init="calculate()">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                {{-- Section A: Employee details --}}
                <div class="card">
                    <div class="card-header"><h2 class="card-title"><span class="text-brand-500">A.</span> Employee details</h2></div>
                    <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Employee name"><input class="form-input" value="{{ $user->name }}" disabled></x-field>
                        <x-field label="Employee number"><input class="form-input" value="{{ $user->employee_number }}" disabled></x-field>
                        <x-field label="Department"><input class="form-input" value="{{ $user->department?->name ?? '—' }}" disabled></x-field>
                        <x-field label="Job function"><input class="form-input" value="{{ $user->job_title ?? '—' }}" disabled></x-field>
                        <p class="text-xs text-gray-500 sm:col-span-2">These details come from your employee profile. If anything is wrong, ask your Manager to update it.</p>
                    </div>
                </div>

                {{-- Section B: Leave details --}}
                <div class="card">
                    <div class="card-header"><h2 class="card-title"><span class="text-brand-500">B.</span> Leave details</h2></div>
                    <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Leave category" name="leave_category_id" required class="sm:col-span-2">
                            <select id="leave_category_id" name="leave_category_id" x-model="category" class="form-input" required>
                                <option value="">Select a leave category…</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}" @selected(old('leave_category_id', $application->leave_category_id) == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <p class="form-help" x-show="selected" x-text="selected?.desc"></p>
                        </x-field>

                        <x-field label="Leave start date (first day)" name="start_date" required>
                            <input id="start_date" type="date" name="start_date" x-model="start" @change="calculate()" class="form-input" required>
                        </x-field>
                        <x-field label="Leave end date (last day)" name="end_date" required>
                            <input id="end_date" type="date" name="end_date" x-model="end" :min="start" @change="calculate()" class="form-input" required>
                        </x-field>
                        <x-field label="Expected return date" name="return_date" required help="Defaults to the next working day after your leave.">
                            <input id="return_date" type="date" name="return_date" x-model="returnDate" @input="returnTouched = true" :min="end" class="form-input" required>
                        </x-field>
                        <div>
                            <span class="form-label">Number of working days</span>
                            <div class="flex h-[38px] items-center gap-2 rounded-lg bg-brand-50 px-3 ring-1 ring-brand-200">
                                <x-icon name="calendar" class="size-4 text-brand-600" />
                                <span class="text-lg font-bold tabular-nums text-brand-800" x-text="loading ? '…' : days"></span>
                                <span class="text-sm text-brand-700">working day(s)</span>
                            </div>
                            <p class="form-help">Excludes weekends and public holidays.</p>
                        </div>
                        <template x-if="holidays.length">
                            <div class="rounded-lg bg-accent-50 px-3 py-2 text-xs text-accent-800 ring-1 ring-accent-200 sm:col-span-2">
                                <strong>Public holidays excluded:</strong>
                                <span x-text="holidays.join(', ')"></span>
                            </div>
                        </template>
                        <p x-show="error" x-text="error" class="form-error sm:col-span-2"></p>

                        <x-field label="Reason / notes" name="reason" class="sm:col-span-2">
                            <textarea id="reason" name="reason" rows="3" class="form-input" :required="selected?.reason"
                                      placeholder="Any information required by the leave category or company policy">{{ old('reason', $application->reason) }}</textarea>
                            <p class="form-help" x-show="selected?.reason">A reason is required for this leave category.</p>
                        </x-field>
                        <x-field label="Contact details while on leave (optional)" name="contact_while_away" class="sm:col-span-2">
                            <input id="contact_while_away" name="contact_while_away" class="form-input" value="{{ old('contact_while_away', $application->contact_while_away) }}" placeholder="Phone number or email, in case of urgent queries">
                        </x-field>
                    </div>
                </div>

                {{-- Section C: Supporting documents --}}
                <div class="card">
                    <div class="card-header"><h2 class="card-title"><span class="text-brand-500">C.</span> Supporting documents</h2></div>
                    <div class="card-body space-y-4">
                        <div x-show="selected?.doc" class="flex items-start gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-900 ring-1 ring-brand-200">
                            <x-icon name="paperclip" class="mt-0.5 size-4" />
                            <span>A supporting document (e.g. a medical certificate) is required for this leave category before you can submit.</span>
                        </div>
                        @if ($editing && $application->documents->isNotEmpty())
                            <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                                @foreach ($application->documents as $doc)
                                    <li class="flex items-center gap-3 px-3 py-2 text-sm">
                                        <x-icon name="document" class="size-5 text-gray-400" />
                                        <a href="{{ route('leave.document', [$application, $doc]) }}" class="flex-1 truncate text-brand-700 hover:underline">{{ $doc->original_name }}</a>
                                        <span class="text-xs text-gray-400">{{ number_format($doc->size / 1024) }} KB</span>
                                        <button form="del-doc-{{ $doc->id }}" class="text-red-500 hover:text-red-700" title="Remove"><x-icon name="trash" class="size-4" /></button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <x-field name="documents" help="PDF, Word or image files. Up to 5 files, {{ config('hrms.max_upload_kb') / 1024 }} MB each.">
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                        </x-field>
                    </div>
                </div>
            </div>

            {{-- Section D: Declaration & submit --}}
            <div class="space-y-6">
                <div class="card xl:sticky xl:top-24">
                    <div class="card-header"><h2 class="card-title"><span class="text-brand-500">D.</span> Employee declaration</h2></div>
                    <div class="card-body space-y-4">
                        <div class="rounded-lg bg-gray-50 p-3 text-sm leading-relaxed text-gray-700">
                            I, <strong>{{ $user->name }}</strong>, declare that the information in this application is true and correct.
                            I understand that leave may only be taken once it has been approved by the Director, that supporting documents may be
                            requested in line with LivityICT policy, and that providing false information may be dealt with under company policy.
                        </div>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="hidden" name="declaration" value="0">
                            <input type="checkbox" name="declaration" value="1" class="form-check mt-0.5" @checked(old('declaration', $application->declaration_accepted))>
                            <span>I accept the declaration above.</span>
                        </label>
                        @error('declaration')<p class="form-error">{{ $message }}</p>@enderror
                        <div class="text-xs text-gray-500">Submission date: <strong>recorded automatically</strong> when you submit.</div>

                        <div class="flex flex-col gap-2 border-t border-gray-100 pt-4">
                            <button name="action" value="submit" class="btn-primary w-full"><x-icon name="send" class="size-4" /> {{ $returned ? 'Resubmit application' : 'Submit application' }}</button>
                            <button name="action" value="draft" class="btn-secondary w-full">Save as draft</button>
                        </div>
                        <p class="text-xs text-gray-500">Once submitted, your Manager is notified to review the application before it goes to the Director for a final decision.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($editing)
        @foreach ($application->documents as $doc)
            <form id="del-doc-{{ $doc->id }}" method="POST" action="{{ route('leave.document.destroy', [$application, $doc]) }}" onsubmit="return confirm('Remove this document?')">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
</x-layouts.app>
