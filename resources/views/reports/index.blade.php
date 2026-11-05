<x-layouts.app title="Reports">
    <x-page-header title="Leave reports" subtitle="Generate summaries and histories, then view on screen or export to PDF, Excel (CSV) or print." />

    <form method="GET" action="{{ route('reports.run') }}" x-data="{ type: '{{ old('type', 'monthly') }}' }" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            @foreach ($types as $key => [$label, $desc])
                <label class="card flex cursor-pointer items-start gap-4 p-4 transition" :class="type === '{{ $key }}' ? 'ring-2 ring-brand-400 border-brand-300' : 'hover:border-gray-300'">
                    <input type="radio" name="type" value="{{ $key }}" x-model="type" class="mt-1 size-4 border-gray-300 text-brand-500 focus:ring-brand-500">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $label }}</p>
                        <p class="text-sm text-gray-500">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="card xl:sticky xl:top-24 xl:self-start">
            <div class="card-header"><h2 class="card-title">Report options</h2></div>
            <div class="card-body space-y-4">
                <div x-show="type === 'monthly'">
                    <x-field label="Month" name="month" required>
                        <input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-input" :disabled="type !== 'monthly'">
                    </x-field>
                </div>
                <div x-show="type === 'employee'" x-cloak>
                    <x-field label="Employee" name="employee" required>
                        <select name="employee" class="form-input" :disabled="type !== 'employee'">
                            @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_number }})</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <div x-show="type === 'status'" x-cloak>
                    <x-field label="Outcome" name="status_group">
                        <select name="status_group" class="form-input" :disabled="type !== 'status'">
                            <option value="">All (approved, declined & pending)</option>
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                            <option value="pending">Pending</option>
                        </select>
                    </x-field>
                </div>
                <div x-show="type !== 'monthly'" class="grid grid-cols-2 gap-3">
                    <x-field label="From" name="from"><input type="date" name="from" class="form-input" :disabled="type === 'monthly'" value="{{ now()->startOfYear()->toDateString() }}"></x-field>
                    <x-field label="To" name="to"><input type="date" name="to" class="form-input" :disabled="type === 'monthly'" value="{{ now()->endOfYear()->toDateString() }}"></x-field>
                </div>
                <x-field label="Department (optional)" name="department">
                    <select name="department" class="form-input"><option value="">All departments</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </x-field>
                <div class="grid grid-cols-2 gap-2 border-t border-gray-100 pt-4">
                    <button name="format" value="html" class="btn-primary col-span-2"><x-icon name="eye" class="size-4" /> View report</button>
                    <button name="format" value="pdf" class="btn-secondary"><x-icon name="document" class="size-4" /> PDF</button>
                    <button name="format" value="csv" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel</button>
                    <button name="format" value="print" formtarget="_blank" class="btn-secondary col-span-2"><x-icon name="printer" class="size-4" /> Print</button>
                </div>
            </div>
        </div>
    </form>

    <p class="mt-6 text-xs text-gray-500">Leave balances are not shown. They will be added once LivityICT approves leave entitlement rules and opening balances.</p>
</x-layouts.app>
