@php use App\Services\LeaveBalance; @endphp
<x-layouts.app title="Leave balances">
    <x-page-header title="Leave balances" subtitle="Days taken, pending and available for each employee in their current leave cycle. Entitlements are set under Leave categories.">
        <a href="{{ route('balances.export', request()->query()) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel (CSV)</a>
    </x-page-header>

    <form method="GET" class="card mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2">
                <label class="form-label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                    <input name="search" value="{{ $filters['search'] ?? '' }}" class="form-input pl-9" placeholder="Name or employee no.">
                </div>
            </div>
            <div>
                <label class="form-label">Department</label>
                <select name="department" class="form-input"><option value="">All</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Employment type</label>
                <select name="type" class="form-input">
                    <option value="">All</option>
                    <option value="employee" @selected(($filters['type'] ?? '') === 'employee')>Employees</option>
                    <option value="contractor" @selected(($filters['type'] ?? '') === 'contractor')>Contractors</option>
                </select>
            </div>
            <div class="flex flex-col justify-end gap-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="low" value="1" class="form-check" @checked($filters['low'] ?? false)> {{ $tracked->first()?->name ?? 'Annual leave' }}: 3 days or fewer left</label>
                <div class="flex gap-2">
                    <button class="btn-primary flex-1"><x-icon name="filter" class="size-4" /> Filter</button>
                    @if (array_filter($filters))<a href="{{ route('balances.index') }}" class="btn-secondary">Clear</a>@endif
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        @if ($users->isEmpty())
            <x-empty icon="users" title="No employees found" text="Try changing the filters." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            @foreach ($tracked as $c)
                                <th class="min-w-44">{{ $c->name }} <span class="font-normal normal-case text-gray-400">({{ LeaveBalance::days($c->entitlement_days) }} {{ $c->cycleLabel() }})</span></th>
                            @endforeach
                            <th>Other leave<br><span class="font-normal normal-case text-gray-400">taken this year</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $u)
                        @php $mine = $balances[$u->id] ?? collect(); @endphp
                        <tr class="cursor-pointer" onclick="location.href='{{ route('balances.show', $u) }}'">
                            <td class="font-medium text-gray-900">
                                <a href="{{ route('balances.show', $u) }}" class="hover:text-brand-600">{{ $u->name }}</a>
                                <div class="text-xs font-normal text-gray-500">
                                    {{ $u->employee_number }}{{ $u->department ? ' · '.$u->department->name : '' }}
                                    @if ($u->isContractor())<span class="badge ml-1 bg-gray-100 text-gray-600 ring-gray-300">Contractor</span>@endif
                                </div>
                            </td>
                            @foreach ($tracked as $c)
                                @php $b = $mine[$c->id] ?? null; @endphp
                                <td>
                                    @if ($b && $b->tracked())
                                        @php
                                            $color = $b->isOverdrawn() ? 'bg-red-500' : ($b->availableAfterPending() <= max(1, $b->entitlement * 0.2) ? 'bg-amber-500' : 'bg-accent-500');
                                        @endphp
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-base font-bold tabular-nums {{ $b->isOverdrawn() ? 'text-red-700' : 'text-gray-900' }}">{{ LeaveBalance::days($b->available()) }}</span>
                                            <span class="text-xs text-gray-500">left of {{ LeaveBalance::days($b->entitlement) }}</span>
                                        </div>
                                        <div class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                                            <div class="{{ $color }}" style="width: {{ $b->percentTaken() }}%"></div>
                                            <div class="{{ $color }} opacity-40" style="width: {{ $b->percentPending() }}%"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">{{ LeaveBalance::days($b->taken) }} taken · {{ LeaveBalance::days($b->pending) }} pending</div>
                                    @else
                                        <span class="text-xs text-gray-400">Not entitled{{ $b && $b->taken ? ' · '.LeaveBalance::days($b->taken).' taken' : '' }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="tabular-nums">
                                {{ LeaveBalance::days($mine->reject(fn ($b) => $b->tracked() || $tracked->contains('id', $b->category->id))->sum('taken')) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
                Working days. <strong>Taken</strong> = approved leave (including approved leave still to come). <strong>Pending</strong> = submitted and awaiting a decision.
                Cycles start on the employee's employment start date, or 1 January if none is recorded.
            </p>
        @endif
    </div>
</x-layouts.app>
