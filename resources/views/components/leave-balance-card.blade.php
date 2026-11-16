@props(['balance'])
@php
    use App\Services\LeaveBalance;
    $b = $balance;
    $tone = match (true) {
        ! $b->tracked() => 'gray',
        $b->isOverdrawn() => 'red',
        $b->availableAfterPending() <= max(1, $b->entitlement * 0.2) => 'amber',
        default => 'accent',
    };
    $bar = ['accent' => 'bg-accent-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'gray' => 'bg-gray-400'][$tone];
    $num = ['accent' => 'text-accent-700', 'amber' => 'text-amber-700', 'red' => 'text-red-700', 'gray' => 'text-gray-900'][$tone];
@endphp
<div {{ $attributes->merge(['class' => 'card flex flex-col p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate font-semibold text-gray-900">{{ $b->category->name }}</p>
            <p class="text-xs text-gray-500">
                @if ($b->tracked())
                    {{ LeaveBalance::days($b->entitlement) }} working days {{ $b->category->cycleLabel() }}
                @else
                    No entitlement set · this year
                @endif
            </p>
        </div>
        @if ($b->tracked())
            <div class="text-right">
                <div class="text-2xl leading-none font-bold tabular-nums {{ $num }}">{{ LeaveBalance::days($b->available()) }}</div>
                <div class="mt-1 text-[11px] font-medium tracking-wide text-gray-500 uppercase">{{ $b->isOverdrawn() ? 'Over limit' : 'Available' }}</div>
            </div>
        @endif
    </div>

    @if ($b->tracked())
        <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-gray-100" role="img"
             aria-label="{{ LeaveBalance::days($b->taken) }} taken and {{ LeaveBalance::days($b->pending) }} pending of {{ LeaveBalance::days($b->entitlement) }} days">
            <div class="{{ $bar }}" style="width: {{ $b->percentTaken() }}%"></div>
            <div class="{{ $bar }} opacity-40" style="width: {{ $b->percentPending() }}%"></div>
        </div>
    @endif

    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
        <div class="rounded-lg bg-gray-50 px-2 py-2">
            <dt class="text-[11px] font-medium text-gray-500">Taken</dt>
            <dd class="text-sm font-semibold tabular-nums text-gray-900">{{ LeaveBalance::days($b->taken) }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-2 py-2">
            <dt class="text-[11px] font-medium text-gray-500">Pending</dt>
            <dd class="text-sm font-semibold tabular-nums text-gray-900">{{ LeaveBalance::days($b->pending) }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-2 py-2">
            <dt class="text-[11px] font-medium text-gray-500">{{ $b->tracked() ? 'After pending' : 'Scheduled' }}</dt>
            <dd class="text-sm font-semibold tabular-nums text-gray-900">{{ LeaveBalance::days($b->tracked() ? $b->availableAfterPending() : $b->scheduled) }}</dd>
        </div>
    </dl>

    <p class="mt-3 text-xs text-gray-400">
        Cycle {{ $b->cycleLabel() }}@if ($b->tracked() && $b->scheduled > 0) · {{ LeaveBalance::days($b->scheduled) }} of the taken days are still to come @endif
    </p>
</div>
