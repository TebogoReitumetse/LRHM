<x-layouts.app title="Leave categories">
    <x-page-header title="Leave categories" subtitle="Must match LivityICT's approved leave policy. Changes should be approved by management and are recorded in the audit trail." />

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
                                @unless ($c->is_active)<span class="badge bg-gray-100 text-gray-500 ring-gray-300">Inactive</span>@endunless
                            </div>
                        </div>
                        <button type="button" @click="edit = true" class="btn-secondary btn-sm">Edit</button>
                    </div>
                    <div x-show="edit" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <input name="name" value="{{ $c->name }}" class="form-input sm:col-span-3" required>
                        <input name="sort_order" type="number" value="{{ $c->sort_order }}" class="form-input" title="Sort order">
                        <textarea name="description" rows="2" class="form-input sm:col-span-4">{{ $c->description }}</textarea>
                        <div class="flex flex-wrap gap-4 text-sm sm:col-span-4">
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_document" value="1" class="form-check" @checked($c->requires_document)> Document required</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_reason" value="1" class="form-check" @checked($c->requires_reason)> Reason required</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="form-check" @checked($c->is_active)> Active</label>
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
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_document" value="1" class="form-check"> Supporting document required</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_reason" value="1" class="form-check"> Reason required</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="form-check" checked> Active</label>
                <button class="btn-primary w-full">Add category</button>
            </div>
        </form>
    </div>
</x-layouts.app>
