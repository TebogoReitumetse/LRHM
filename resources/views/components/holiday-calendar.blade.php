@props(['months', 'holidays', 'upcoming'])
@php
    $today = today();
    $first = $months->first();

    // Build each month's grid (weeks start on Monday, padded with blank cells).
    $grids = $months->map(function ($month) use ($holidays) {
        $lead = $month->dayOfWeekIso - 1;
        $cells = array_merge(array_fill(0, $lead, null), range(1, $month->daysInMonth));
        $cells = array_merge($cells, array_fill(0, (7 - count($cells) % 7) % 7, null));
        $count = $holidays->filter(fn ($h) => $h->date->isSameMonth($month))->count();

        return ['month' => $month, 'cells' => $cells, 'holidayCount' => $count];
    });
@endphp
<div {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-2xl']) }}
     x-data="{
        current: 0,
        total: {{ $months->count() }},
        dir: 1,
        touchX: null,
        go(i) {
            if (i < 0 || i >= this.total || i === this.current) return;
            this.dir = i > this.current ? 1 : -1;
            this.current = i;
            this.$nextTick(() => {
                const strip = this.$refs.strip, pill = strip.children[i];
                strip.scrollTo({ left: pill.offsetLeft - (strip.clientWidth - pill.clientWidth) / 2, behavior: 'smooth' });
            });
        },
        swipe(endX) {
            if (this.touchX === null) return;
            const dx = endX - this.touchX;
            this.touchX = null;
            if (Math.abs(dx) > 40) this.go(this.current + (dx < 0 ? 1 : -1));
        },
     }"
     @keydown.left.prevent="go(current - 1)"
     @keydown.right.prevent="go(current + 1)">
    {{-- Logo image behind the frosted glass --}}
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-white bg-cover bg-no-repeat"
         style="background-image: url('{{ asset('images/logo_background.jpg') }}'); background-position: 70% 72%;"></div>

    {{-- Frosted glass that fades out toward the bottom-right so the logo shows through clearly there --}}
    <div aria-hidden="true" class="cal-glass absolute inset-0 -z-10 rounded-2xl bg-white/30 backdrop-blur-[6px]"></div>

    <div class="grid grid-cols-1 gap-6 rounded-2xl border border-white/60 p-5 shadow-lg shadow-gray-900/5 sm:p-6 lg:grid-cols-5">
        {{-- Month view --}}
        <div class="min-w-0 lg:col-span-3" :class="dir > 0 ? 'cal-next' : 'cal-prev'">
            <div class="mb-3 flex items-center gap-2">
                <button type="button" @click="go(current - 1)" :disabled="current === 0"
                        class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white/60 text-gray-700 ring-1 ring-white/80 backdrop-blur transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Previous month">
                    <x-icon name="chevron-left" class="size-5" />
                </button>

                {{-- Month heading: one per month, stacked; the active one animates in --}}
                <div class="grid min-w-0 flex-1 overflow-hidden" aria-live="polite">
                    @foreach ($grids as $i => $g)
                        <h3 x-show="current === {{ $i }}" @if ($i) x-cloak @endif
                            class="cal-title col-start-1 row-start-1 flex items-center justify-center gap-2 text-lg font-bold text-gray-900 sm:text-xl">
                            <x-icon name="calendar" class="size-5 text-accent-600" />
                            <span>{{ $g['month']->format('F') }}</span>
                            <span class="font-semibold text-gray-500">{{ $g['month']->format('Y') }}</span>
                        </h3>
                    @endforeach
                </div>

                <button type="button" @click="go(current + 1)" :disabled="current === total - 1"
                        class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white/60 text-gray-700 ring-1 ring-white/80 backdrop-blur transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Next month">
                    <x-icon name="chevron-right" class="size-5" />
                </button>
            </div>

            {{-- Scrollable month strip; a dot marks months with public holidays --}}
            <div x-ref="strip" class="cal-strip relative -mx-1 mb-4 flex snap-x gap-1.5 overflow-x-auto px-1 pb-1">
                @foreach ($grids as $i => $g)
                    <button type="button" @click="go({{ $i }})"
                            :class="current === {{ $i }} ? 'bg-accent-500 text-white shadow-sm' : 'bg-white/50 text-gray-600 hover:bg-white/80'"
                            class="relative shrink-0 cursor-pointer snap-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-white/70 transition">
                        {{ $g['month']->format($g['month']->year === $first->year ? 'M' : 'M y') }}
                        @if ($g['holidayCount'])
                            <span class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-brand-400 ring-2 ring-white" title="{{ $g['holidayCount'] }} public holiday{{ $g['holidayCount'] > 1 ? 's' : '' }}"></span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold tracking-wide text-gray-500 uppercase">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                    <div class="py-1 {{ $loop->index >= 5 ? 'text-gray-400' : '' }}">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Day grids: stacked so switching months doesn't shift the layout --}}
            <div class="mt-1 grid" @touchstart.passive="touchX = $event.touches[0].clientX" @touchend="swipe($event.changedTouches[0].clientX)">
                @foreach ($grids as $i => $g)
                    <div x-show="current === {{ $i }}" @if ($i) x-cloak @endif class="col-start-1 row-start-1 grid grid-cols-7 content-start gap-1 text-center text-sm">
                        @foreach ($g['cells'] as $c => $day)
                            @if ($day === null)
                                <div class="aspect-square"></div>
                                @continue
                            @endif
                            @php
                                $date = $g['month']->copy()->day($day);
                                $holiday = $holidays[$date->toDateString()] ?? null;
                                $isToday = $date->isSameDay($today);
                                $circle = match (true) {
                                    $isToday && $holiday => 'bg-accent-200 font-bold text-accent-900 ring-2 ring-brand-400',
                                    $isToday => 'bg-accent-200 font-bold text-accent-900 ring-1 ring-accent-400',
                                    (bool) $holiday => 'bg-brand-200 font-semibold text-brand-900 ring-1 ring-brand-400',
                                    $date->isBefore($today) => 'cal-halo text-gray-500',
                                    $c % 7 >= 5 => 'cal-halo font-semibold text-gray-600',
                                    default => 'cal-halo font-medium text-gray-900',
                                };
                            @endphp
                            <div class="flex aspect-square items-center justify-center">
                                <span class="cal-day flex size-9 items-center justify-center rounded-full tabular-nums sm:size-10 {{ $circle }}"
                                      style="--i: {{ $c }}"
                                      @if ($holiday) title="{{ $holiday->name }}" @endif
                                      @if ($isToday) aria-current="date" @endif>
                                    {{ $day }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex items-center justify-center gap-4 text-xs text-gray-600">
                <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-accent-200 ring-1 ring-accent-400"></span>Today</span>
                <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-brand-200 ring-1 ring-brand-400"></span>Public holiday</span>
            </div>
        </div>

        {{-- Upcoming holidays: click one to jump to its month --}}
        <div class="lg:col-span-2 lg:border-l lg:border-white/70 lg:pl-6">
            <h3 class="mb-3 text-sm font-semibold tracking-wide text-gray-600 uppercase">Upcoming SA public holidays</h3>
            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-500">No upcoming holidays are loaded. Add them under <em>Public holidays</em>.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($upcoming as $h)
                        @php
                            $away = (int) $today->diffInDays($h->date);
                            $index = ($h->date->year - $first->year) * 12 + $h->date->month - $first->month;
                        @endphp
                        <li>
                            <button type="button" @if ($index < $months->count()) @click="go({{ $index }})" @endif
                                    :class="current === {{ $index }} ? 'ring-brand-300 bg-white/80' : 'ring-white/70 bg-white/50'"
                                    class="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-left ring-1 backdrop-blur-sm transition hover:bg-white/80">
                                <div class="flex size-11 shrink-0 flex-col items-center justify-center rounded-full bg-brand-100 text-brand-800 ring-1 ring-brand-300">
                                    <span class="text-sm leading-none font-bold">{{ $h->date->format('j') }}</span>
                                    <span class="text-[10px] leading-tight font-semibold uppercase">{{ $h->date->format('M') }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $h->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $h->date->format('l') }}</p>
                                </div>
                                <span class="shrink-0 text-xs font-medium {{ $away === 0 ? 'text-accent-700' : 'text-gray-500' }}">
                                    {{ $away === 0 ? 'Today' : ($away === 1 ? 'Tomorrow' : 'in '.$away.' days') }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
