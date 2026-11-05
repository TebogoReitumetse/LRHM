@php
    use App\Enums\ContractStatus; use App\Models\ContractDocument;
    $c = $contract;
    $isManager = auth()->user()->isManager();
    $monitored = in_array($c->status, ContractStatus::monitored(), true);
    $closed = in_array($c->status, [ContractStatus::Renewed, ContractStatus::Superseded, ContractStatus::Ended], true);
@endphp
<x-layouts.app :title="'Contract '.$c->reference">
    <x-page-header :title="$c->full_name" :subtitle="'Contract '.$c->reference.' · '.$c->typeLabel()" :back="$isManager ? route('contracts.index') : route('contracts.mine')">
        <x-status :status="$c->status" class="px-3 py-1 text-sm" />
        @if ($isManager && ! $closed)
            <a href="{{ route('contracts.edit', $c) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
        @endif
        @if ($isManager && $monitored)
            <a href="{{ route('contracts.renew', $c) }}" class="btn-accent"><x-icon name="refresh" class="size-4" /> Record renewal / extension</a>
        @endif
    </x-page-header>

    @if ($newer->isNotEmpty())
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800 ring-1 ring-sky-200">
            <x-icon name="info" class="size-5" />
            <span>This is a previous contract version. Current version: <a class="font-semibold underline" href="{{ route('contracts.show', $newer->last()) }}">{{ $newer->last()->reference }}</a>.</span>
        </div>
    @endif

    {{-- Timeline bar --}}
    @php
        $total = max(1, $c->start_date->diffInDays($c->end_date));
        $elapsed = min($total, max(0, $c->start_date->diffInDays(today(), false)));
        $pct = round($elapsed / $total * 100);
    @endphp
    <div class="card mb-6 p-5">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><p class="text-xs text-gray-500 uppercase">Start date</p><p class="text-lg font-semibold">{{ $c->start_date->format('d M Y') }}</p></div>
            <div><p class="text-xs text-gray-500 uppercase">End date</p><p class="text-lg font-semibold">{{ $c->end_date->format('d M Y') }}</p></div>
            <div><p class="text-xs text-gray-500 uppercase">Contract duration</p><p class="text-lg font-semibold text-brand-700">{{ $c->durationLabel() }}</p></div>
            <div><p class="text-xs text-gray-500 uppercase">Time remaining</p><p class="text-lg font-semibold {{ $c->urgencyClass() }}">{{ $c->remainingLabel() }}</p></div>
        </div>
        @if ($monitored)
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full {{ $c->daysRemaining() <= 30 ? 'bg-red-500' : ($c->daysRemaining() <= 90 ? 'bg-brand-500' : 'bg-accent-500') }}" style="width: {{ $pct }}%"></div>
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ $pct }}% of the contract period has elapsed.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Contract details</h2></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div><dt>Full name</dt><dd>{{ $c->full_name }}</dd></div>
                        <div><dt>Employee number</dt><dd>{{ $c->employee_number }}</dd></div>
                        <div><dt>Job title</dt><dd>{{ $c->job_title ?? '—' }}</dd></div>
                        <div><dt>Department</dt><dd>{{ $c->department?->name ?? '—' }}</dd></div>
                        <div><dt>Contract type</dt><dd>{{ $c->typeLabel() }}</dd></div>
                        <div><dt>Date signed</dt><dd>{{ $c->date_signed?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt>Commencement date</dt><dd>{{ $c->commencement_date?->format('d M Y') ?? 'Same as start date' }}</dd></div>
                        <div><dt>Dates verified</dt><dd>{!! $c->dates_verified ? '<span class="text-accent-700">Yes, against signed contract</span>' : '<span class="text-amber-700">Not yet verified</span>' !!}</dd></div>
                        @if ($isManager)
                            <div><dt>Captured by</dt><dd>{{ $c->creator->name }} · {{ $c->created_at->format('d M Y') }}</dd></div>
                            @if ($c->notes)<div class="sm:col-span-2"><dt>Notes</dt><dd class="whitespace-pre-line">{{ $c->notes }}</dd></div>@endif
                        @endif
                    </dl>
                </div>
            </div>

            @if ($c->renewal_decision || $c->ended_on)
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Renewal decision</h2></div>
                    <div class="card-body">
                        <dl class="dl-grid">
                            <div><dt>Decision</dt><dd class="font-semibold">{{ $c->renewal_decision === 'renew' ? ($c->status === ContractStatus::Superseded ? 'Extended' : 'Renewed') : 'Not renewed' }}</dd></div>
                            <div><dt>Decided by</dt><dd>{{ $c->renewal_decided_by_name }}</dd></div>
                            <div><dt>Decision date</dt><dd>{{ $c->renewal_decision_date?->format('d M Y') }}</dd></div>
                            @if ($c->ended_on)<div><dt>Contract ended on</dt><dd>{{ $c->ended_on->format('d M Y') }}</dd></div>@endif
                            @if ($isManager)
                                <div><dt>Recorded by</dt><dd>{{ $c->renewalRecorder?->name }}</dd></div>
                                @if ($c->renewal_notes)<div class="sm:col-span-2"><dt>Notes</dt><dd class="whitespace-pre-line">{{ $c->renewal_notes }}</dd></div>@endif
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Documents</h2></div>
                @if ($c->documents->isEmpty())
                    <p class="card-body text-sm text-gray-500">No documents uploaded.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($c->documents as $doc)
                            <li class="flex items-center gap-3 px-5 py-3 text-sm {{ $doc->is_current ? '' : 'opacity-60' }}">
                                <x-icon name="document" class="size-5 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $doc->original_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $doc->typeLabel() }}{{ $doc->is_current ? '' : ' (replaced)' }} · {{ $doc->uploader->name }} · {{ $doc->created_at->format('d M Y H:i') }}</p>
                                </div>
                                <a href="{{ route('contracts.document', [$c, $doc]) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Download</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($isManager)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('contracts.upload', $c) }}" class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:items-end">
                        @csrf
                        <x-field label="Document type" name="document_type" class="sm:w-48">
                            <select name="document_type" class="form-input">@foreach (ContractDocument::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        </x-field>
                        <x-field label="File" name="document" class="flex-1">
                            <input type="file" name="document" required class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                        </x-field>
                        <button class="btn-primary"><x-icon name="upload" class="size-4" /> Upload</button>
                    </form>
                @endif
            </div>

            @if ($history->isNotEmpty() || $newer->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-header"><h2 class="card-title">Contract history</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Reference</th><th>Period</th><th>Duration</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach ($newer->reverse()->concat([$c])->concat($history) as $h)
                                <tr @class(['bg-brand-50/60 font-semibold' => $h->is($c), 'cursor-pointer' => ! $h->is($c)]) @unless ($h->is($c)) onclick="location.href='{{ route('contracts.show', $h) }}'" @endunless>
                                    <td>{{ $h->reference }} @if ($h->is($c))<span class="text-xs font-normal text-gray-500">(this)</span>@endif</td>
                                    <td class="whitespace-nowrap">{{ $h->start_date->format('d M Y') }} – {{ $h->end_date->format('d M Y') }}</td>
                                    <td class="text-xs">{{ $h->durationLabel() }}</td>
                                    <td><x-status :status="$h->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        @if ($isManager)
            <div class="space-y-6">
                @if ($monitored && ! $c->renewal_decision)
                    <div class="card" x-data="{ open: false }">
                        <div class="card-header"><h2 class="card-title">Renewal or non-renewal</h2></div>
                        <div class="card-body space-y-3 text-sm">
                            <p class="text-gray-600">Start the renewal or non-renewal process with the Director or management under LivityICT's procedures, then record the outcome here.</p>
                            <a href="{{ route('contracts.renew', $c) }}" class="btn-accent w-full"><x-icon name="refresh" class="size-4" /> Record renewal / extension</a>
                            <button type="button" @click="open = !open" class="btn-secondary w-full"><x-icon name="x-circle" class="size-4" /> Record non-renewal / end</button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('contracts.end', $c) }}" class="space-y-3 border-t border-gray-100 pt-3">@csrf
                                <x-field label="Decision made by" name="decided_by_name" required><input name="decided_by_name" class="form-input" placeholder="e.g. Thandi Mokoena (Director)" required></x-field>
                                <x-field label="Decision date" name="decision_date" required><input type="date" name="decision_date" max="{{ today()->toDateString() }}" class="form-input" required></x-field>
                                <x-field label="Contract end date" name="ended_on" required><input type="date" name="ended_on" value="{{ $c->end_date->toDateString() }}" class="form-input" required></x-field>
                                <x-field label="Notes" name="notes" required><textarea name="notes" rows="3" class="form-input" required></textarea></x-field>
                                <button class="btn-danger w-full" onclick="return confirm('Record that this contract will not be renewed?')">Record non-renewal</button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="card overflow-hidden">
                    <div class="card-header"><h2 class="card-title">Reminder activity</h2></div>
                    @if ($reminders->isEmpty())
                        <p class="card-body text-sm text-gray-500">No reminders generated for this contract yet.</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($reminders as $r)
                                <li class="px-5 py-3 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium">{{ $r->label() }}</span>
                                        <span @class(['badge', 'bg-accent-50 text-accent-800 ring-accent-200' => $r->status === 'sent', 'bg-red-50 text-red-700 ring-red-200' => $r->status === 'failed', 'bg-gray-100 text-gray-600 ring-gray-300' => ! in_array($r->status, ['sent', 'failed'])])>{{ ucfirst($r->status) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500">Scheduled {{ $r->scheduled_for->format('d M Y') }} · generated {{ $r->generated_at?->format('d M Y H:i') }}{{ $r->recipients ? ' · '.$r->recipients.' recipient(s) via '.$r->channels : '' }}</p>
                                    @if ($r->error)<p class="text-xs text-gray-400">{{ $r->error }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="card overflow-hidden">
                    <div class="card-header"><h2 class="card-title">Audit trail</h2></div>
                    <ul class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
                        @foreach ($audit as $log)
                            <li class="px-5 py-3 text-xs">
                                <p class="font-medium text-gray-800">{{ $log->description }}</p>
                                <p class="text-gray-500">{{ $log->user->name }} · {{ $log->created_at->format('d M Y H:i') }}</p>
                                @if ($log->event === 'updated' && $log->new_values)
                                    <p class="mt-1 text-gray-500">@foreach ($log->new_values as $k => $v){{ str_replace('_', ' ', $k) }}: <span class="line-through">{{ is_scalar($log->old_values[$k] ?? null) ? $log->old_values[$k] : '—' }}</span> → {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last); @endif @endforeach</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
