@props(['label' => null, 'name' => null, 'help' => null, 'required' => false])
<div {{ $attributes }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="form-label">
            {{ $label }} @if ($required)<span class="text-brand-600">*</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @if ($name)
        @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @endif
</div>
