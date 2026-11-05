<x-layouts.app title="My contract">
    <x-page-header title="My contract" subtitle="Your contract details and signed documents. Contact your Manager if anything looks incorrect." />

    @if (! $enabled)
        <div class="card"><x-empty icon="shield" title="Contract access is not enabled" text="Viewing your contract online has not been enabled for your account. Please contact your Manager." /></div>
    @elseif ($contracts->isEmpty())
        <div class="card"><x-empty icon="briefcase" title="No contract on record" text="There is no contract recorded for you yet." /></div>
    @else
        <div class="space-y-6">
            @foreach ($contracts as $c)
                @php $current = $loop->first; @endphp
                <div @class(['card', 'ring-2 ring-brand-200' => $current])>
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">{{ $c->typeLabel() }} <span class="font-normal text-gray-400">· {{ $c->reference }}</span></h2>
                            @if ($current)<p class="text-xs font-medium text-brand-600">Current contract</p>@endif
                        </div>
                        <x-status :status="$c->status" />
                    </div>
                    <div class="card-body">
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div><dt class="text-xs text-gray-500 uppercase">Start date</dt><dd class="font-semibold">{{ $c->start_date->format('d M Y') }}</dd></div>
                            <div><dt class="text-xs text-gray-500 uppercase">End date</dt><dd class="font-semibold">{{ $c->end_date->format('d M Y') }}</dd></div>
                            <div><dt class="text-xs text-gray-500 uppercase">Duration</dt><dd class="font-semibold">{{ $c->durationLabel() }}</dd></div>
                            <div><dt class="text-xs text-gray-500 uppercase">Expiry status</dt><dd class="font-semibold {{ $c->urgencyClass() }}">{{ $c->remainingLabel() }}</dd></div>
                        </dl>
                        @php $docs = $c->documents->where('is_current', true); @endphp
                        @if ($docs->isNotEmpty())
                            <div class="mt-5 flex flex-wrap gap-2">
                                @foreach ($docs as $doc)
                                    <a href="{{ route('contracts.document', [$c, $doc]) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> {{ $doc->typeLabel() }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
