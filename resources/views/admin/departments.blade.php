<x-layouts.app title="Departments">
    <x-page-header title="Departments" />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            <ul class="divide-y divide-gray-100">
                @foreach ($departments as $d)
                    <li x-data="{ edit: false }" class="px-5 py-3">
                        <div x-show="!edit" class="flex items-center gap-3">
                            <div class="flex-1"><p class="font-medium">{{ $d->name }}</p><p class="text-xs text-gray-500">{{ $d->users_count }} staff{{ $d->description ? ' · '.$d->description : '' }}</p></div>
                            <button @click="edit = true" class="btn-secondary btn-sm">Edit</button>
                            <form method="POST" action="{{ route('admin.departments.destroy', $d) }}" onsubmit="return confirm('Remove {{ $d->name }}?')">@csrf @method('DELETE')
                                <button class="btn-secondary btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ route('admin.departments.update', $d) }}" class="flex flex-col gap-2 sm:flex-row">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $d->name }}" class="form-input" required>
                            <input name="description" value="{{ $d->description }}" class="form-input" placeholder="Description">
                            <button class="btn-primary btn-sm">Save</button>
                            <button type="button" @click="edit = false" class="btn-secondary btn-sm">Cancel</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
        <form method="POST" action="{{ route('admin.departments.store') }}" class="card self-start">
            @csrf
            <div class="card-header"><h2 class="card-title">Add department</h2></div>
            <div class="card-body space-y-3">
                <x-field label="Name" name="name" required><input name="name" class="form-input" required></x-field>
                <x-field label="Description" name="description"><input name="description" class="form-input"></x-field>
                <button class="btn-primary w-full">Add</button>
            </div>
        </form>
    </div>
</x-layouts.app>
