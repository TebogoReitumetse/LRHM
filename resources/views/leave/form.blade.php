@php
    $editing = $application->exists;
    $returned = $application->status === \App\Enums\LeaveStatus::ReturnedForInformation;
    $catMap = $categories->mapWithKeys(fn ($c) => [$c->id => [
        'doc' => $c->requires_document, 'after' => $c->document_after_days, 'specify' => $c->requiresSpecification(), 'desc' => $c->description,
    ]]);
@endphp
<x-layouts.app :title="$editing ? 'Edit leave application' : 'Apply for leave'">
    <x-page-header :title="$editing ? 'Leave application '.($application->reference ?? '(draft)') : 'Leave application form'"
                   subtitle="Complete the form as you would the paper form. Working days are calculated automatically."
                   :back="$editing ? route('leave.show', $application) : route('leave.index')" />

    @if ($returned)
        <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-semibold">Management has asked for more information</p>
            <p class="mt-1">{{ $application->return_reason }}</p>
            <p class="mt-2 text-xs">Update your application or attach the requested documents, then sign the declaration again and resubmit.</p>
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

        <div class="card mx-auto max-w-4xl">
            {{-- Letterhead --}}
            <div class="flex flex-wrap items-start justify-between gap-4 border-b-4 border-brand-500 px-6 py-5">
                <img src="{{ asset('images/logo.png') }}" alt="LivityICT" class="h-14 w-auto">
                <div class="text-right text-xs leading-5 text-gray-600">
                    <p>Mahikeng 2745</p>
                    <p>Tel: 018 381 1418</p>
                    <p>Email: info@livityict.co.za</p>
                </div>
            </div>

            <div class="space-y-8 px-6 py-6">
                <h1 class="text-2xl font-extrabold tracking-wide text-gray-900 uppercase">Leave Application Form</h1>

                {{-- 1. Employee details --}}
                <section>
                    <h2 class="paper-heading">1. Employee Details</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-field label="Employee Name"><input class="form-input" value="{{ $user->name }}" disabled></x-field>
                        <x-field label="Employee ID Number" name="id_number">
                            <input name="id_number" class="form-input" maxlength="20" inputmode="numeric" autocomplete="off"
                                   value="{{ old('id_number', $user->id_number) }}" placeholder="SA ID or passport number">
                        </x-field>
                        <x-field label="Department/Position"><input class="form-input" value="{{ $user->departmentPosition() }}" disabled></x-field>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Your name and department come from your employee profile. Your ID number is saved to your profile when you save this form, so it is filled in for your next application.
                        If your name or department is wrong, ask your Manager to update it.
                    </p>
                </section>

                {{-- 2. Leave request details --}}
                <section class="space-y-6">
                    <h2 class="paper-heading">2. Leave Request Details</h2>

                    <fieldset>
                        <legend class="form-label">Type of Leave (Tick one) <span class="text-brand-600">*</span></legend>
                        <div class="mt-2 space-y-2">
                            @foreach ($categories as $c)
                                <label class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                                    <input type="radio" name="leave_category_id" value="{{ $c->id }}" x-model="category" class="form-check rounded-full" required
                                           @checked(old('leave_category_id', $application->leave_category_id) == $c->id)>
                                    <span class="font-medium text-gray-900">{{ $c->name }}{{ $c->requiresSpecification() ? ':' : '' }}</span>
                                    @if ($note = $c->formNote())
                                        <span class="text-gray-500">({{ $note }})</span>
                                    @endif
                                    @if ($c->requiresSpecification())
                                        <input name="other_leave_type" value="{{ old('other_leave_type', $application->other_leave_type) }}"
                                               class="form-input min-w-0 flex-1 py-1 sm:max-w-xs" maxlength="150" placeholder="Specify"
                                               :disabled="category != '{{ $c->id }}'" :required="category == '{{ $c->id }}'"
                                               @focus="category = '{{ $c->id }}'">
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('leave_category_id')<p class="form-error">{{ $message }}</p>@enderror
                        @error('other_leave_type')<p class="form-error">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Balance for the selected type (current cycle, not counting this application) --}}
                    <div x-data="{ balances: {{ Js::from($balanceMap) }} }" x-show="balances[category]" x-cloak>
                        <template x-if="balances[category] && !balances[category].tracked">
                            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-200">
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    <template x-for="(entitlement, type) in balances[category].entitlements" :key="type">
                                        <span><span x-text="type"></span>: <strong x-text="entitlement"></strong></span>
                                    </template>
                                    <span>Taken: <strong x-text="balances[category].taken"></strong></span>
                                    <span>Pending: <strong x-text="balances[category].pending"></strong></span>
                                    <span>This application: <strong x-text="days"></strong></span>
                                </div>
                                <p class="mt-1 text-xs opacity-80">Taken and pending are working days this year (<span x-text="balances[category].cycle"></span>). No day balance is deducted for this type of leave.</p>
                            </div>
                        </template>
                        <template x-if="balances[category]?.tracked">
                            <div class="rounded-lg px-4 py-3 text-sm ring-1"
                                 :class="days > balances[category].available ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-gray-50 text-gray-700 ring-gray-200'">
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    <span>Entitlement: <strong x-text="balances[category].entitlement"></strong></span>
                                    <span>Taken: <strong x-text="balances[category].taken"></strong></span>
                                    <span>Pending: <strong x-text="balances[category].pending"></strong></span>
                                    <span>Available: <strong x-text="balances[category].available"></strong></span>
                                    <span>After this application: <strong x-text="Math.max(0, Math.round((balances[category].available - days) * 10) / 10)"></strong></span>
                                </div>
                                <p class="mt-1 text-xs opacity-80">Working days in your current cycle (<span x-text="balances[category].cycle"></span>).</p>
                                <p x-show="days > balances[category].available" class="mt-2 text-xs font-semibold">
                                    This is more than your available balance. The extra
                                    <span x-text="Math.round((days - Math.max(0, balances[category].available)) * 10) / 10"></span>
                                    day(s) will be recorded as {{ \App\Models\LeaveCategory::UNPAID }}.
                                </p>
                            </div>
                        </template>
                    </div>

                    <div>
                        <p class="form-label">Period of Leave</p>
                        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-field label="Start Date" name="start_date" required>
                                <input id="start_date" type="date" name="start_date" x-model="start" @change="calculate()" class="form-input" required>
                            </x-field>
                            <x-field label="End Date" name="end_date" required>
                                <input id="end_date" type="date" name="end_date" x-model="end" :min="start" @change="calculate()" class="form-input" required>
                            </x-field>
                            <div>
                                <span class="form-label">Total Working Days</span>
                                <div class="flex h-[38px] items-center gap-2 rounded-lg bg-brand-50 px-3 ring-1 ring-brand-200">
                                    <x-icon name="calendar" class="size-4 text-brand-600" />
                                    <span class="text-lg font-bold tabular-nums text-brand-800" x-text="loading ? '…' : days"></span>
                                    <span class="text-sm text-brand-700">working day(s)</span>
                                </div>
                                <p class="form-help">Calculated automatically. Excludes weekends and public holidays.</p>
                            </div>
                            <x-field label="Date Returning to Work" name="return_date" required help="Defaults to the next working day after your leave.">
                                <input id="return_date" type="date" name="return_date" x-model="returnDate" @input="returnTouched = true" :min="end" class="form-input" required>
                            </x-field>
                        </div>
                        <template x-if="holidays.length">
                            <div class="mt-3 rounded-lg bg-accent-50 px-3 py-2 text-xs text-accent-800 ring-1 ring-accent-200">
                                <strong>Public holidays excluded:</strong> <span x-text="holidays.join(', ')"></span>
                            </div>
                        </template>
                        <p x-show="error" x-text="error" class="form-error"></p>
                    </div>

                    {{-- Attachments (e.g. medical certificate) --}}
                    <div class="space-y-3">
                        <p class="form-label">Attachments</p>
                        <div x-show="docRequired" x-cloak class="flex items-start gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-900 ring-1 ring-brand-200">
                            <x-icon name="paperclip" class="mt-0.5 size-4" />
                            <span x-text="selected?.after !== null ? `Attach a medical certificate. It is required for more than ${selected?.after} days.` : 'A supporting document is required for this type of leave.'"></span>
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
                </section>

                {{-- 3. Employee declaration --}}
                <section>
                    <h2 class="paper-heading">3. Employee Declaration</h2>
                    <p class="text-sm leading-relaxed text-gray-800">
                        I confirm that the above information is correct and that I will ensure all urgent tasks are handed over before my leave begins.
                    </p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-field label="Employee Signature" name="employee_signature" required class="sm:col-span-2">
                            <x-signature-pad name="employee_signature" upload :value="old('employee_signature', $application->employee_signature ?? $user->signature)" />
                        </x-field>
                        <x-field label="Date" name="employee_signed_on" required>
                            <input type="date" name="employee_signed_on" class="form-input"
                                   value="{{ old('employee_signed_on', $application->employee_signed_on?->toDateString() ?? today()->toDateString()) }}">
                        </x-field>
                    </div>
                </section>

                {{-- 4. Office use only (read-only for the employee) --}}
                <section class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                    <h2 class="paper-heading">4. Office Use Only (Approval)</h2>
                    <div class="pointer-events-none space-y-3 text-sm text-gray-500 select-none">
                        <p class="flex items-center gap-2"><span class="paper-box"></span> Approved</p>
                        <p class="flex flex-wrap items-center gap-2"><span class="paper-box"></span> Declined (Reason: <span class="paper-line min-w-40 flex-1"></span>)</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <p class="flex items-end gap-2">Manager/Owner Signature: <span class="paper-line flex-1"></span></p>
                            <p class="flex items-end gap-2">Date: <span class="paper-line flex-1"></span></p>
                            <p class="flex items-end gap-2">Print Name: <span class="paper-line flex-1"></span></p>
                            <p class="flex items-end gap-2">Director Signature: <span class="paper-line flex-1"></span></p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Completed by the Manager/Owner after you submit. Your Manager reviews the application first, then it goes for the final decision.</p>
                </section>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-6 py-4 sm:flex-row sm:justify-end">
                <button name="action" value="draft" class="btn-secondary">Save as draft</button>
                <button name="action" value="submit" class="btn-primary"><x-icon name="send" class="size-4" /> {{ $returned ? 'Resubmit application' : 'Submit application' }}</button>
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
