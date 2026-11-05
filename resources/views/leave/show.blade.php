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
                   :subtitle="$a->employee_name.' · '.$a->category->name"
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
                <div class="card-header"><h2 class="card-title">Application details</h2></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div><dt>Employee name</dt><dd>{{ $a->employee_name }}</dd></div>
                        <div><dt>Employee number</dt><dd>{{ $a->employee_number }}</dd></div>
                        <div><dt>Department / job function</dt><dd>{{ $a->department ?? '—' }}{{ $a->job_title ? ' · '.$a->job_title : '' }}</dd></div>
                        <div><dt>Leave category</dt><dd>{{ $a->category->name }}</dd></div>
                        <div><dt>Leave start date</dt><dd>{{ $a->start_date->format('l, d F Y') }}</dd></div>
                        <div><dt>Leave end date</dt><dd>{{ $a->end_date->format('l, d F Y') }}</dd></div>
                        <div><dt>Expected return date</dt><dd class="font-semibold text-accent-700">{{ $a->return_date->format('l, d F Y') }}</dd></div>
                        <div><dt>Working days</dt><dd><span class="text-lg font-bold text-brand-700">{{ (float) $a->working_days }}</span>
                            @if ($holidays)<span class="text-xs text-gray-500"> (excludes {{ implode(', ', $holidays) }})</span>@endif</dd></div>
                        <div class="sm:col-span-2"><dt>Reason / notes</dt><dd class="whitespace-pre-line">{{ $a->reason ?: '—' }}</dd></div>
                        @if ($a->contact_while_away)<div class="sm:col-span-2"><dt>Contact while away</dt><dd>{{ $a->contact_while_away }}</dd></div>@endif
                        <div><dt>Employee declaration</dt><dd>
                            @if ($a->declaration_accepted)<span class="inline-flex items-center gap-1 text-accent-700"><x-icon name="check" class="size-4" /> Accepted {{ $a->declaration_accepted_at?->format('d M Y H:i') }}</span>
                            @else<span class="text-gray-500">Not yet accepted</span>@endif
                        </dd></div>
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
                        @else
                            <p class="mt-1 text-sm text-gray-500">Not yet reviewed.</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Director (final decision)</p>
                        @if ($a->decided_at)
                            <p class="mt-1 text-sm font-medium">{{ $a->director?->name }}</p>
                            <p class="text-xs text-gray-500">{{ $a->decided_at->format('d M Y H:i') }}</p>
                            <p class="mt-2"><x-status :status="$a->status" /></p>
                            @if ($a->director_comment)<p class="mt-2 rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $a->director_comment }}</p>@endif
                        @else
                            <p class="mt-1 text-sm text-gray-500">Awaiting decision.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
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
                            <button class="btn-primary w-full"><x-icon name="send" class="size-4" /> Forward to Director</button>
                        </form>
                        <form x-cloak x-show="mode==='return'" method="POST" action="{{ route('leave.return', $a) }}" class="space-y-3">@csrf
                            <x-field label="What information is needed?" name="comment" required>
                                <textarea name="comment" rows="4" class="form-input" required placeholder="e.g. Please upload the medical certificate."></textarea>
                            </x-field>
                            <button class="btn bg-amber-500 text-white hover:bg-amber-600 w-full"><x-icon name="undo" class="size-4" /> Return to employee</button>
                        </form>
                        <p class="text-xs text-gray-500">The Manager reviews and forwards. The final decision is made by the Director.</p>
                    </div>
                </div>
            @endif

            {{-- Director decision --}}
            @if ($canDecide)
                <div class="card ring-2 ring-accent-200">
                    <div class="card-header bg-accent-50/60"><h2 class="card-title">Director decision</h2></div>
                    <form method="POST" action="{{ route('leave.decide', $a) }}" class="card-body space-y-4">@csrf
                        <x-field label="Comments" name="comment" help="Required when declining.">
                            <textarea name="comment" rows="4" class="form-input">{{ old('comment') }}</textarea>
                        </x-field>
                        <div class="grid grid-cols-2 gap-2">
                            <button name="decision" value="decline" class="btn-danger" onclick="return confirm('Decline this application?')"><x-icon name="x" class="size-4" /> Decline</button>
                            <button name="decision" value="approve" class="btn-accent"><x-icon name="check" class="size-4" /> Approve</button>
                        </div>
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
