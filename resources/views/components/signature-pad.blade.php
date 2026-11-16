@props(['name', 'value' => null, 'disabled' => false, 'upload' => false])
{{-- Draw-to-sign box (or upload a PNG). Posts the signature as a PNG data URL in a hidden input. --}}
<div x-data="signaturePad(@js($value))" {{ $attributes->class('w-full') }}>
    <div class="relative h-36 overflow-hidden rounded-lg border border-dashed border-gray-300 bg-white">
        <canvas x-ref="canvas" class="absolute inset-0 block size-full touch-none {{ $disabled ? 'pointer-events-none' : 'cursor-crosshair' }}"
                @pointerdown="start($event)" @pointermove="move($event)" @pointerup="end()" @pointerleave="end()" @pointercancel="end()"></canvas>
        <div class="pointer-events-none absolute inset-x-6 bottom-7 border-b border-gray-400"></div>
        <span x-show="empty" class="pointer-events-none absolute inset-x-0 bottom-9 text-center text-xs text-gray-400">
            Sign here with your mouse or finger{{ $upload ? ', or upload a PNG image' : '' }}
        </span>
    </div>
    @unless ($disabled)
        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
            @if ($upload)
                <label class="cursor-pointer text-xs font-medium text-brand-700 hover:underline">
                    Upload signature (PNG)
                    <input type="file" accept="image/png" class="sr-only" @change="upload($event)">
                </label>
            @endif
            <button type="button" @click="clear()" x-show="!empty" class="text-xs font-medium text-brand-700 hover:underline">Clear and sign again</button>
        </div>
        <p x-show="error" x-text="error" class="mt-1 text-xs text-red-600"></p>
    @endunless
    <input type="hidden" name="{{ $name }}" :value="value">
</div>
