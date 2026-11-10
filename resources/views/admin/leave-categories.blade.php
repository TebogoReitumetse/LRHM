<x-layouts.app title="Leave categories">
    <x-page-header title="Leave categories" subtitle="Must match LivityICT's approved leave policy. Changes should be approved by management and are recorded in the audit trail. BCEA minimums (5-day week): annual 15 working days per year, sick 30 days per 3 years, family responsibility 3 days per year." />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            @foreach ($categories as $c)
                <form method="POST" action="{{ route('admin.leave-categories.update', $c) }}" class="card p-4" x-data="{ edit: false }">
                    @csrf @method('PUT')
                    <div x-show="!edit" class="flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold {{ $c->is_active ? '' : 'text-gray-400 line-through' }}">{{ $c->name }}</p>
                            <p class="text-sm text-gray-500">{{ $c->description }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($c->requires_document)<span class="badge bg-brand-50 text-brand-800 ring-brand-200">Document required</span>@endif
                                @if ($c->requires_reason)<span class="badge bg-sky-50 text-sky-700 ring-sky-200">Reason required</span>@endif
                                @if ($c->entitlement_days !== null)
                                    <span class="badge bg-accent-50 text-accent-800 ring-accent-200">{{ \App\Services\LeaveBalance::days($c->entitlement_days) }} working days {{ $c->cycleLabel() }}{{ $c->entitlement_for_contractors ? ' · incl. contractors' : '' }}</span>
                                @else
                                    <span class="badge bg-gray-50 text-gray-500 ring-gray-200">No balance tracked</span>
                                @endif
                                @unless ($c->is_active)<span class="badge bg-gray-100 text-gray-500 ring-gray-300">Inactive</span>@endunless
                            </div>
                        </div>
                        <button type="button" @click="edit = true" class="btn-secondary btn-sm">Edit</button>
                    </div>
                    <div x-show="edit" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <input name="name" value="{{ $c->name }}" class="form-input sm:col-span-3" required>
                        <input name="sort_order" type="number" value="{{ $c->sort_order }}" class="form-input" title="Sort order">
                        <textarea name="description" rows="2" class="form-input sm:col-span-4">{{ $c->description }}</textarea>
                        <label class="text-sm sm:col-span-2"><span class="form-label">Entitlement (working days)</span>
                            <input name="entitlement_days" type="number" step="0.5" min="0" value="{{ $c->entitlement_days }}" class="form-input" placeholder="Blank = not tracked"></label>
                        <label class="text-sm sm:col-span-2"><span class="form-label">Cycle length (months)</span>
                            <input name="entitlement_cycle_months" type="number" min="1" value="{{ $c->entitlement_cycle_months }}" class="form-input"></label>
                        <div class="flex flex-wrap gap-4 text-sm sm:col-span-4">
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_document" value="1" class="form-check" @checked($c->requires_document)> Document required</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_reason" value="1" class="form-check" @checked($c->requires_reason)> Reason required</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="form-check" @checked($c->is_active)> Active</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="entitlement_for_contractors" value="1" class="form-check" @checked($c->entitlement_for_contractors)> Entitlement applies to contractors</label>
                        </div>
                        <div class="flex gap-2 sm:col-span-4"><button class="btn-primary btn-sm">Save</button><button type="button" @click="edit = false" class="btn-secondary btn-sm">Cancel</button></div>
                    </div>
                </form>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.leave-categories.store') }}" class="card self-start">
            @csrf
            <div class="card-header"><h2 class="card-title">Add category</h2></div>
            <div class="card-body space-y-3">
                <x-field label="Name" name="name" required><input name="name" class="form-input" required></x-field>
                <x-field label="Description" name="description"><textarea name="description" rows="2" class="form-input"></textarea></x-field>
                <x-field label="Sort order" name="sort_order"><input type="number" name="sort_order" value="{{ $categories->max('sort_order') + 1 }}" class="form-input"></x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Entitlement (days)" name="entitlement_days"><input type="number" step="0.5" min="0" name="entitlement_days" class="form-input" placeholder="Blank = none"></x-field>
                    <x-field label="Cycle (months)" name="entitlement_cycle_months"><input type="number" min="1" name="entitlement_cycle_months" value="12" class="form-input"></x-field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_document" value="1" class="form-check"> Supporting document required</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="entitlement_for_contractors" value="1" class="form-check"> Entitlement applies to contractors</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_reason" value="1" class="form-check"> Reason required</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="form-check" checked> Active</label>
                <button class="btn-primary w-full">Add category</button>
            </div>
        </form>
    </div>
</x-layouts.app>
