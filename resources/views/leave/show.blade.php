@php
    use App\Enums\LeaveStatus;
    $a = $application;
    $me = auth()->user();
    $canReview = $me->can('review', $a) && in_array($a->status, [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview], true);
    $canDecide = $me->can('decide', $a) && $a->status === LeaveStatus::PendingDirectorApproval;
    $steps = [
        ['Submitted', $a->submitted_at],
        ['Manager review', $a->manager_reviewed_at],
        ['Director decision', $a->decided_at],
    ];
@endphp
<x-layouts.app :title="'Leave '.($a->reference ?? 'draft')">
    <x-page-header :title="'Leave application '.($a->reference ?? '(draft)')"
                   :subtitle="$a->employee_name.' · '.$a->leaveTypeLabel()"
                   :back="$a->user_id === $me->id ? route('leave.index') : route('register.index')">
        <x-status :status="$a->status" class="px-3 py-1 text-sm" />
        <a href="{{ route('leave.print', $a) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
        @can('update', $a)
            <a href="{{ route('leave.edit', $a) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
            <form method="POST" action="{{ route('leave.submit', $a) }}">@csrf
                <button class="btn-primary"><x-icon name="send" class="size-4" /> {{ $a->status === LeaveStatus::ReturnedForInformation ? 'Resubmit' : 'Submit' }}</button>
            </form>
        @endcan
        @can('delete', $a)
            <form method="POST" action="{{ route('leave.destroy', $a) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')
                <button class="btn-danger"><x-icon name="trash" class="size-4" /> Delete</button>
            </form>
        @endcan
    </x-page-header>

    {{-- Progress --}}
    <div class="card mb-6 p-5">
        <ol class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <li class="flex items-center gap-3">
                <span class="flex size-8 items-center justify-center rounded-full bg-accent-500 text-white"><x-icon name="check" class="size-4" /></span>
                <div><p class="text-sm font-medium">Application created</p><p class="text-xs text-gray-500">{{ $a->created_at->format('d M Y H:i') }}</p></div>
            </li>
            @foreach ($steps as $i => [$label, $at])
                @php
                    $done = (bool) $at;
                    $current = ! $done && (($i === 0 && $a->status === LeaveStatus::Draft) || ($i === 1 && in_array($a->status, [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview, LeaveStatus::ReturnedForInformation], true)) || ($i === 2 && $a->status === LeaveStatus::PendingDirectorApproval));
                    $declined = $i === 2 && $a->status === LeaveStatus::Declined;
                @endphp
                <li class="flex items-center gap-3">
                    <span @class(['flex size-8 items-center justify-center rounded-full text-sm font-bold',
                        'bg-red-500 text-white' => $declined,
                        'bg-accent-500 text-white' => $done && ! $declined,
                        'bg-brand-500 text-white ring-4 ring-brand-100' => $current,
                        'bg-gray-100 text-gray-400' => ! $done && ! $current])>
                        @if ($declined)<x-icon name="x" class="size-4" />@elseif ($done)<x-icon name="check" class="size-4" />@else{{ $i + 2 }}@endif
                    </span>
                    <div><p class="text-sm font-medium">{{ $label }}</p><p class="text-xs text-gray-500">{{ $at?->format('d M Y H:i') ?? ($current ? 'In progress' : 'Pending') }}</p></div>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($a->status === LeaveStatus::ReturnedForInformation)
                <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                    <p class="font-semibold">Returned for additional information · {{ $a->returned_at?->format('d M Y H:i') }}</p>
                    <p class="mt-1">{{ $a->return_reason }}</p>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h2 class="card-title">1. Employee Details</h2></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div><dt>Employee Name</dt><dd>{{ $a->employee_name }}</dd></div>
                        <div><dt>Employee ID Number</dt><dd>{{ $a->id_number ?? '—' }}</dd></div>
                        <div><dt>Department/Position</dt><dd>{{ $a->departmentPosition() }}</dd></div>
                        <div><dt>Employee number</dt><dd>{{ $a->employee_number }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">2. Leave Request Details</h2></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div class="sm:col-span-2"><dt>Type of Leave</dt><dd class="font-semibold">{{ $a->leaveTypeLabel() }}</dd></div>
                        <div><dt>Start Date</dt><dd>{{ $a->start_date->format('l, d F Y') }}</dd></div>
                        <div><dt>End Date</dt><dd>{{ $a->end_date->format('l, d F Y') }}</dd></div>
                        <div><dt>Total Working Days</dt><dd><span class="text-lg font-bold text-brand-700">{{ (float) $a->working_days }}</span>
                            @if ($holidays)<span class="text-xs text-gray-500"> (excludes {{ implode(', ', $holidays) }})</span>@endif
                            @if ($a->hasUnpaidDays())<span class="mt-1 block text-sm font-medium text-amber-700">{{ $a->daysSplitLabel() }}</span>@endif</dd></div>
                        <div><dt>Date Returning to Work</dt><dd class="font-semibold text-accent-700">{{ $a->return_date->format('l, d F Y') }}</dd></div>
                        @if ($a->reason)<div class="sm:col-span-2"><dt>Notes</dt><dd class="whitespace-pre-line">{{ $a->reason }}</dd></div>@endif
                        @if ($a->contact_while_away)<div class="sm:col-span-2"><dt>Contact while away</dt><dd>{{ $a->contact_while_away }}</dd></div>@endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">3. Employee Declaration</h2></div>
                <div class="card-body space-y-4">
                    <p class="text-sm text-gray-700">I confirm that the above information is correct and that I will ensure all urgent tasks are handed over before my leave begins.</p>
                    <dl class="dl-grid">
                        <div><dt>Employee Signature</dt><dd>
                            @if ($a->employee_signature)
                                <img src="{{ $a->employee_signature }}" alt="Signature of {{ $a->employee_name }}" class="h-20 max-w-full rounded border border-gray-200 bg-white">
                            @elseif ($a->declaration_accepted)
                                <span class="inline-flex items-center gap-1 text-accent-700"><x-icon name="check" class="size-4" /> Accepted electronically</span>
                            @else
                                <span class="text-gray-500">Not signed yet</span>
                            @endif
                        </dd></div>
                        <div><dt>Date</dt><dd>{{ ($a->employee_signed_on ?? $a->declaration_accepted_at)?->format('d F Y') ?? '—' }}</dd></div>
                        <div><dt>Submission date</dt><dd>{{ $a->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Supporting documents</h2></div>
                @if ($a->documents->isEmpty())
                    <p class="card-body text-sm text-gray-500">No documents uploaded.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($a->documents as $doc)
                            <li class="flex items-center gap-3 px-5 py-3 text-sm">
                                <x-icon name="paperclip" class="size-5 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $doc->original_name }}</p>
                                    <p class="text-xs text-gray-500">Uploaded by {{ $doc->uploader->name }} · {{ $doc->created_at->format('d M Y H:i') }} · {{ number_format($doc->size / 1024) }} KB</p>
                                </div>
                                <a href="{{ route('leave.document', [$a, $doc]) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Download</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Management review &amp; decision</h2></div>
                <div class="card-body grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Manager</p>
                        @if ($a->manager_reviewed_at)
                            <p class="mt-1 text-sm font-medium">{{ $a->manager?->name }}</p>
                            <p class="text-xs text-gray-500">{{ $a->manager_reviewed_at->format('d M Y H:i') }}</p>
                            <p class="mt-2"><span @class(['badge', 'bg-accent-50 text-accent-800 ring-accent-200' => $a->manager_recommendation === 'approve', 'bg-red-50 text-red-700 ring-red-200' => $a->manager_recommendation !== 'approve'])>
                                {{ $a->manager_recommendation === 'approve' ? 'Recommends approval' : 'Does not recommend approval' }}</span></p>
                            @if ($a->manager_comment)<p class="mt-2 rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $a->manager_comment }}</p>@endif
                            @if ($a->approver_signature && ! $a->decided_at)
                                <p class="mt-3 text-xs text-gray-500">Manager/Owner Signature</p>
                                <img src="{{ $a->approver_signature }}" alt="Manager signature" class="h-16 max-w-full rounded border border-gray-200 bg-white">
                            @endif
                        @else
                            <p class="mt-1 text-sm text-gray-500">Not yet reviewed.</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">4. Office Use Only (Approval)</p>
                        @if ($a->decided_at)
                            <p class="mt-2"><x-status :status="$a->status" /></p>
                            @if ($a->director_comment)
                                <p class="mt-2 text-xs text-gray-500">{{ $a->status === LeaveStatus::Declined ? 'Reason' : 'Comment' }}</p>
                                <p class="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $a->director_comment }}</p>
                            @endif
                            <dl class="mt-3 space-y-2 text-sm">
                                <div><dt class="text-xs text-gray-500">Manager/Owner Signature</dt><dd>
                                    @if ($a->approver_signature)<img src="{{ $a->approver_signature }}" alt="Approver signature" class="h-16 max-w-full rounded border border-gray-200 bg-white">@else<span class="text-gray-500">Recorded electronically</span>@endif
                                </dd></div>
                                <div><dt class="text-xs text-gray-500">Date</dt><dd>{{ $a->decided_at->format('d/m/Y') }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Print Name</dt><dd class="font-medium">{{ $a->approver_name ?? $a->director?->name }}</dd></div>
                                @if ($a->director_signature)
                                    <div><dt class="text-xs text-gray-500">Director Signature</dt><dd>
                                        <img src="{{ $a->director_signature }}" alt="Director signature" class="h-16 max-w-full rounded border border-gray-200 bg-white">
                                        <span class="block text-xs text-gray-500">{{ $a->director?->name }}</span>
                                    </dd></div>
                                @endif
                            </dl>
                        @else
                            <p class="mt-1 text-sm text-gray-500">Awaiting decision.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Applicant's balance for this category --}}
            @if ($balance?->tracked())
                @php
                    $counts = in_array($a->status, [LeaveStatus::Approved, ...\App\Services\LeaveBalanceService::PENDING], true);
                    $after = $balance->availableAfterPending() - ($counts ? $a->paidDays() : 0);
                @endphp
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">{{ $a->user_id === $me->id ? 'Your' : 'Employee' }} {{ $balance->category->name }} balance</h2>
                    </div>
                    <div class="card-body space-y-3 text-sm">
                        <dl class="grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Entitlement</dt><dd class="font-semibold tabular-nums">{{ \App\Services\LeaveBalance::days($balance->entitlement) }} days</dd></div>
                            <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Already taken</dt><dd class="font-semibold tabular-nums">{{ \App\Services\LeaveBalance::days($balance->taken) }} days</dd></div>
                            <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Other pending</dt><dd class="font-semibold tabular-nums">{{ \App\Services\LeaveBalance::days($balance->pending) }} days</dd></div>
                            <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">This application</dt><dd class="font-semibold tabular-nums">{{ \App\Services\LeaveBalance::days($a->paidDays()) }} days</dd></div>
                        </dl>
                        @if ($a->hasUnpaidDays())
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800 ring-1 ring-amber-200">
                                {{ \App\Services\LeaveBalance::days((float) $a->unpaid_days) }} day(s) beyond the balance are recorded as {{ \App\Models\LeaveCategory::UNPAID }}.
                            </p>
                        @endif
                        <div @class(['rounded-lg px-3 py-2 ring-1', 'bg-red-50 text-red-800 ring-red-200' => $after < 0, 'bg-accent-50 text-accent-800 ring-accent-200' => $after >= 0])>
                            @if ($counts)
                                Left after this application: <strong class="tabular-nums">{{ \App\Services\LeaveBalance::days($after) }} days</strong>
                                @if ($after < 0)<span class="block text-xs">This exceeds the entitlement by {{ \App\Services\LeaveBalance::days(-$after) }} days.</span>@endif
                            @else
                                Available: <strong class="tabular-nums">{{ \App\Services\LeaveBalance::days($balance->availableAfterPending()) }} days</strong>
                                <span class="block text-xs">This application does not count against the balance ({{ strtolower($a->status->label()) }}).</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400">Current cycle {{ $balance->cycleLabel() }}, working days.</p>
                    </div>
                </div>
            @endif

            {{-- Manager actions --}}
            @if ($canReview)
                <div class="card ring-2 ring-brand-200" x-data="{ mode: 'forward' }">
                    <div class="card-header bg-brand-50/60"><h2 class="card-title">Manager review</h2></div>
                    <div class="card-body space-y-4">
                        @if ($a->status === LeaveStatus::Submitted)
                            <form method="POST" action="{{ route('leave.start-review', $a) }}">@csrf
                                <button class="btn-secondary w-full"><x-icon name="eye" class="size-4" /> Mark as under review</button>
                            </form>
                        @endif
                        <div class="flex rounded-lg bg-gray-100 p-1 text-sm font-medium">
                            <button type="button" @click="mode='forward'" :class="mode==='forward' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="flex-1 rounded-md px-3 py-1.5">Forward to Director</button>
                            <button type="button" @click="mode='return'" :class="mode==='return' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="flex-1 rounded-md px-3 py-1.5">Return for info</button>
                        </div>
                        <form x-show="mode==='forward'" method="POST" action="{{ route('leave.forward', $a) }}" class="space-y-3">@csrf
                            <x-field label="Your recommendation" name="recommendation" required>
                                <select name="recommendation" class="form-input">
                                    <option value="approve">Recommend approval</option>
                                    <option value="decline">Do not recommend approval</option>
                                </select>
                            </x-field>
                            <x-field label="Comments (operational requirements, cover, etc.)" name="comment">
                                <textarea name="comment" rows="3" class="form-input">{{ old('comment') }}</textarea>
                            </x-field>
                            <x-field label="Manager/Owner Signature" name="approver_signature" required>
                                <x-signature-pad name="approver_signature" :value="old('approver_signature', $me->signature)" />
                            </x-field>
                            <x-field label="Print Name" name="approver_name" required>
                                <input name="approver_name" class="form-input" value="{{ old('approver_name', $me->name) }}" maxlength="150" required>
                            </x-field>
                            <button class="btn-primary w-full"><x-icon name="send" class="size-4" /> Sign &amp; forward to Director</button>
                        </form>
                        <form x-cloak x-show="mode==='return'" method="POST" action="{{ route('leave.return', $a) }}" class="space-y-3">@csrf
                            <x-field label="What information is needed?" name="comment" required>
                                <textarea name="comment" rows="4" class="form-input" required placeholder="e.g. Please upload the medical certificate."></textarea>
                            </x-field>
                            <button class="btn bg-amber-500 text-white hover:bg-amber-600 w-full"><x-icon name="undo" class="size-4" /> Return to employee</button>
                        </form>
                        <p class="text-xs text-gray-500">The Manager reviews, signs and forwards. The final decision is made by the Director.</p>
                    </div>
                </div>
            @endif

            {{-- Office use only: final decision --}}
            @if ($canDecide)
                <div class="card ring-2 ring-accent-200" x-data="{ decision: '{{ old('decision', 'approve') }}' }">
                    <div class="card-header bg-accent-50/60"><h2 class="card-title">4. Office Use Only (Approval)</h2></div>
                    <form method="POST" action="{{ route('leave.decide', $a) }}" class="card-body space-y-4">@csrf
                        <fieldset class="space-y-2 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="approve" x-model="decision" class="form-check rounded-full"> <span class="font-medium">Approved</span></label>
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="decline" x-model="decision" class="form-check rounded-full"> <span class="font-medium">Declined</span></label>
                            @error('decision')<p class="form-error">{{ $message }}</p>@enderror
                        </fieldset>
                        <x-field label="Reason" name="comment" x-show="decision === 'decline'" x-cloak>
                            <textarea name="comment" rows="3" class="form-input" :required="decision === 'decline'">{{ old('comment') }}</textarea>
                        </x-field>
                        @if ($a->approver_signature)
                            {{-- Signed by the Manager when forwarding. --}}
                            <div class="space-y-2 text-sm">
                                <p class="text-xs font-medium text-gray-500">Manager/Owner Signature</p>
                                <img src="{{ $a->approver_signature }}" alt="Manager signature" class="h-16 max-w-full rounded border border-gray-200 bg-white">
                                <p class="text-xs text-gray-500">Signed by {{ $a->approver_name }}{{ $a->manager_reviewed_at ? ' on '.$a->manager_reviewed_at->format('d/m/Y') : '' }}</p>
                            </div>
                        @endif
                        <x-field label="Director Signature" name="director_signature" required
                                 :help="$a->approver_signature ? null : 'No Manager signed this form, so your signature is also used as the Manager/Owner Signature.'">
                            <x-signature-pad name="director_signature" upload :value="old('director_signature', $me->signature)" />
                        </x-field>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="Date"><input class="form-input" value="{{ today()->format('d/m/Y') }}" disabled></x-field>
                            @if ($a->approver_signature)
                                <x-field label="Director"><input class="form-input" value="{{ $me->name }}" disabled></x-field>
                            @else
                                <x-field label="Print Name" name="approver_name" required>
                                    <input name="approver_name" class="form-input" value="{{ old('approver_name', $me->name) }}" maxlength="150" required>
                                </x-field>
                            @endif
                        </div>
                        <button class="w-full" :class="decision === 'decline' ? 'btn-danger' : 'btn-accent'"
                                @click="if (decision === 'decline' && !confirm('Decline this application?')) $event.preventDefault()">
                            <span x-text="decision === 'decline' ? 'Decline application' : 'Approve application'"></span>
                        </button>
                    </form>
                </div>
            @endif

            @if ($a->user_id === $me->id && $me->isManagement() && ! $a->status->isFinal() && $a->status !== LeaveStatus::Draft)
                <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-gray-200">This is your own application. It will be reviewed by another authorised user.</div>
            @endif

            {{-- Timeline --}}
            <div class="card">
                <div class="card-header"><h2 class="card-title">History &amp; audit trail</h2></div>
                <ol class="card-body space-y-5">
                    @foreach ($a->actions as $act)
                        <li class="relative flex gap-3">
                            @unless ($loop->last)<span class="absolute top-7 left-[11px] h-full w-px bg-gray-200"></span>@endunless
                            <span @class(['relative z-10 mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                                'bg-accent-500 text-white' => $act->action === 'approved',
                                'bg-red-500 text-white' => $act->action === 'declined',
                                'bg-amber-500 text-white' => $act->action === 'returned',
                                'bg-brand-500 text-white' => ! in_array($act->action, ['approved', 'declined', 'returned'])])>
                                <span class="size-2 rounded-full bg-white"></span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $act->label() }}</p>
                                <p class="text-xs text-gray-500">{{ $act->user->name }} · {{ $act->created_at->format('d M Y H:i') }}</p>
                                @if ($act->comment)<p class="mt-1 rounded-md bg-gray-50 px-2 py-1 text-xs whitespace-pre-line text-gray-700">{{ $act->comment }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</x-layouts.app>
