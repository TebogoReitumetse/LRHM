{{-- Plain-language summary of the leave minimums in the Basic Conditions of Employment Act 75 of 1997 (BCEA). --}}
@php
    $sections = [
        [
            'title' => 'Annual leave',
            'ref' => 'Section 20',
            'points' => [
                'At least 21 consecutive days per 12-month cycle — 15 working days if you work a 5-day week.',
                'You may agree instead to 1 day for every 17 days worked.',
                'Leave must be granted within 6 months after the end of the cycle.',
                'Public holidays that fall within your leave are not counted as leave days.',
                'Unused leave may only be paid out when employment ends.',
            ],
        ],
        [
            'title' => 'Sick leave',
            'ref' => 'Sections 22–23',
            'points' => [
                'Paid sick leave equal to the days you would normally work in 6 weeks, per 36-month cycle — 30 days for a 5-day week.',
                'In your first 6 months: 1 day of paid sick leave for every 26 days worked.',
                'A medical certificate may be required if you are off for more than 2 consecutive days, or on more than 2 occasions in 8 weeks.',
            ],
        ],
        [
            'title' => 'Family responsibility leave',
            'ref' => 'Section 27',
            'points' => [
                '3 paid days per annual cycle, if you have worked here for more than 4 months and work at least 4 days a week.',
                'Applies when your child is sick, or on the death of a spouse or life partner, parent, adoptive parent, grandparent, child, adopted child, grandchild or sibling.',
                'Reasonable proof may be requested.',
            ],
        ],
        [
            'title' => 'Maternity, parental & adoption leave',
            'ref' => 'Sections 25–25C',
            'points' => [
                'Maternity: at least 4 consecutive months, starting up to 4 weeks before the expected birth. No work for 6 weeks after the birth unless a doctor or midwife certifies you fit.',
                'Parental leave: 10 consecutive days on the birth of your child.',
                'Adoption or commissioning parent (surrogacy): 10 consecutive weeks for one parent, 10 days for the other.',
                'These are not paid by the employer under the BCEA, but you can claim benefits from the UIF.',
            ],
        ],
    ];
@endphp
<details {{ $attributes->merge(['class' => 'card group mt-4']) }}>
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4 select-none [&::-webkit-details-marker]:hidden">
        <x-icon name="shield" class="size-5 shrink-0 text-brand-600" />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-gray-900">Your leave rights under the Basic Conditions of Employment Act</p>
            <p class="text-xs text-gray-500">The legal minimums every employee in South Africa is entitled to</p>
        </div>
        <x-icon name="chevron-down" class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
    </summary>

    <div class="border-t border-gray-100 px-5 py-5">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @foreach ($sections as $section)
                <div>
                    <h4 class="flex flex-wrap items-baseline gap-x-2 text-sm font-semibold text-gray-900">
                        {{ $section['title'] }}
                        <span class="text-xs font-normal text-gray-400">{{ $section['ref'] }}</span>
                    </h4>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                        @foreach ($section['points'] as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex items-start gap-2 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-500">
            <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>This is a summary of the minimums in the Basic Conditions of Employment Act 75 of 1997. Company policy may be more generous (for example, annual leave above). For the full wording or your specific situation, speak to HR or see the Department of Employment and Labour.</p>
        </div>
    </div>
</details>
