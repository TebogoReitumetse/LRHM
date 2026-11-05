<x-layouts.app title="Leave register">
    <x-page-header title="Leave register" subtitle="Central record of all submitted leave applications and management decisions.">
        <a href="{{ route('register.export', ['format' => 'csv'] + request()->query()) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel (CSV)</a>
        <a href="{{ route('register.export', ['format' => 'pdf'] + request()->query()) }}" class="btn-secondary"><x-icon name="document" class="size-4" /> PDF</a>
        <a href="{{ route('register.export', ['format' => 'print'] + request()->query()) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
    </x-page-header>

    <form method="GET" class="card mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <div class="sm:col-span-2 xl:col-span-2">
                <label class="form-label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                    <input name="search" value="{{ $filters['search'] ?? '' }}" class="form-input pl-9" placeholder="Reference, name or employee no.">
                </div>
            </div>
            <div>
                <label class="form-label">Employee</label>
                <select name="employee" class="form-input"><option value="">All</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(($filters['employee'] ?? '') == $e->id)>{{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Department</label>
                <select name="department" class="form-input"><option value="">All</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-input"><option value="">All</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input"><option value="">All</option>
                    @foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-2 xl:col-span-1 xl:grid-cols-1">
                <div><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input"></div>
                <div><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input"></div>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button class="btn-primary"><x-icon name="filter" class="size-4" /> Apply filters</button>
            <a href="{{ route('register.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($applications->isEmpty())
            <x-empty icon="search" title="No applications match" text="Try widening the date range or clearing filters." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Employee</th><th>Category</th><th>Leave dates</th><th>Days</th><th>Submitted</th><th>Status</th><th>Manager</th><th>Director decision</th></tr></thead>
                    <tbody>
                    @foreach ($applications as $a)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('leave.show', $a) }}'">
                            <td class="font-medium whitespace-nowrap text-brand-700">{{ $a->reference }}</td>
                            <td><div class="font-medium text-gray-900">{{ $a->employee_name }}</div><div class="text-xs text-gray-500">{{ $a->employee_number }} · {{ $a->department }}</div></td>
                            <td>{{ $a->category->name }}</td>
                            <td class="whitespace-nowrap">{{ $a->start_date->format('d M Y') }} – {{ $a->end_date->format('d M Y') }}<div class="text-xs text-gray-500">Return {{ $a->return_date->format('d M Y') }}</div></td>
                            <td class="tabular-nums">{{ (float) $a->working_days }}</td>
                            <td class="whitespace-nowrap">{{ $a->submitted_at?->format('d M Y') }}</td>
                            <td><x-status :status="$a->status" /></td>
                            <td class="max-w-48 text-xs">{{ $a->manager?->name ?? '—' }}@if ($a->manager_comment)<div class="truncate text-gray-500" title="{{ $a->manager_comment }}">{{ $a->manager_comment }}</div>@endif</td>
                            <td class="text-xs whitespace-nowrap">{{ $a->director?->name ?? '—' }}<div class="text-gray-500">{{ $a->decided_at?->format('d M Y H:i') }}</div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $applications->links() }}
        @endif
    </div>
</x-layouts.app>
