@props(['icon' => 'document', 'title' => 'Nothing here yet', 'text' => null])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-500">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <h3 class="mt-3 text-sm font-semibold text-gray-900">{{ $title }}</h3>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-gray-500">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
