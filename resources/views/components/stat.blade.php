@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'href' => null, 'hint' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'accent' => 'bg-accent-50 text-accent-600',
        'red' => 'bg-red-50 text-red-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'gray' => 'bg-gray-100 text-gray-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    class="card flex items-center gap-4 p-4 {{ $href ? 'transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md' : '' }}">
    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <div class="min-w-0">
        <div class="text-2xl font-bold tabular-nums text-gray-900">{{ $value }}</div>
        <div class="text-sm leading-snug text-gray-500">{{ $label }}</div>
        @if ($hint)<div class="text-xs text-gray-400">{{ $hint }}</div>@endif
    </div>
</{{ $tag }}>
