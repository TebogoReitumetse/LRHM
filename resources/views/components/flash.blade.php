@php
    $messages = [
        'success' => ['bg-accent-50 text-accent-800 ring-accent-200', 'check'],
        'warning' => ['bg-amber-50 text-amber-800 ring-amber-200', 'exclamation'],
        'error' => ['bg-red-50 text-red-800 ring-red-200', 'x-circle'],
    ];
    $any = session('success') || session('warning') || session('error') || session('credentials') || $errors->any();
@endphp
@if ($any)
<div class="mb-6 space-y-3">
    @foreach ($messages as $key => [$classes, $icon])
        @if (session($key))
            <div x-data="{ show: true }" x-show="show" class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1 {{ $classes }}">
                <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0" />
                <div class="flex-1">{{ session($key) }}</div>
                <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss"><x-icon name="x" class="size-4" /></button>
            </div>
        @endif
    @endforeach

    @if (session('credentials'))
        <div class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900 ring-1 ring-brand-200">
            <p class="font-semibold">One-time login details: share these securely with the user.</p>
            <p class="mt-1 font-mono">Username: {{ session('credentials.username') }} &nbsp;·&nbsp; Temporary password: {{ session('credentials.password') }}</p>
            <p class="mt-1 text-xs">The user must set a new password at first sign-in. These details will not be shown again.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
            <x-icon name="exclamation" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">Please check the following:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endif
