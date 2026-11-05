@php use App\Enums\ContractStatus; use App\Models\Contract; @endphp
<x-layouts.app title="Contract register">
    <x-page-header title="Contract register" subtitle="All employee and contractor contracts. Earlier versions stay in each contract's history.">
        <a href="{{ route('contracts.export', ['format' => 'csv'] + request()->query()) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel (CSV)</a>
        <a href="{{ route('contracts.export', ['format' => 'pdf'] + request()->query()) }}" class="btn-secondary"><x-icon name="document" class="size-4" /> PDF</a>
        <a href="{{ route('contracts.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New contract</a>
    </x-page-header>

    <form method="GET" class="card mb-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        <div class="sm:col-span-2"><label class="form-label">Name, employee no. or reference</label>
            <input name="search" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Search…"></div>
        <div><label class="form-label">Department</label>
            <select name="department" class="form-input"><option value="">All</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Contract type</label>
            <select name="type" class="form-input"><option value="">All</option>
                @foreach (Contract::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Status</label>
            <select name="status" class="form-input"><option value="">Current (excl. history)</option>
                @foreach (ContractStatus::options() as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label class="form-label">Expiry period</label>
            <select name="expiry" class="form-input"><option value="">Any</option>
                @foreach (['30' => 'Within 30 days', '60' => 'Within 60 days', '90' => 'Within 90 days', 'this_month' => 'This month', 'expired' => 'Expired', 'awaiting_decision' => 'Awaiting decision'] as $k => $v)
                    <option value="{{ $k }}" @selected(($filters['expiry'] ?? '') === (string) $k)>{{ $v }}</option>
                @endforeach
            </select></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="form-label">Ends from</label><input type="date" name="expiry_from" value="{{ $filters['expiry_from'] ?? '' }}" class="form-input px-2"></div>
            <div><label class="form-label">to</label><input type="date" name="expiry_to" value="{{ $filters['expiry_to'] ?? '' }}" class="form-input px-2"></div>
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7">
            <button class="btn-primary"><x-icon name="filter" class="size-4" /> Filter</button>
            <a href="{{ route('contracts.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($contracts->isEmpty())
            <x-empty icon="briefcase" title="No contracts found" text="Create a contract record or adjust your filters.">
                <a href="{{ route('contracts.create') }}" class="btn-primary">New contract</a>
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Name</th><th>Type</th><th>Start</th><th>End</th><th>Duration</th><th>Remaining</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($contracts as $c)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('contracts.show', $c) }}'">
                            <td class="font-medium whitespace-nowrap text-brand-700">{{ $c->reference }}</td>
                            <td><div class="font-medium text-gray-900">{{ $c->full_name }}</div><div class="text-xs text-gray-500">{{ $c->employee_number }}{{ $c->department ? ' · '.$c->department->name : '' }}</div></td>
                            <td class="text-xs">{{ $c->typeLabel() }}</td>
                            <td class="whitespace-nowrap">{{ $c->start_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">{{ $c->end_date->format('d M Y') }}</td>
                            <td class="text-xs whitespace-nowrap">{{ $c->durationLabel() }}</td>
                            <td class="text-xs whitespace-nowrap {{ $c->urgencyClass() }}">{{ $c->remainingLabel() }}</td>
                            <td><x-status :status="$c->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $contracts->links() }}
        @endif
    </div>
</x-layouts.app>
