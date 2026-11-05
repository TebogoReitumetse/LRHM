<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · LivityICT HRMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white font-sans text-gray-900 antialiased">
<div class="flex min-h-full">
    {{-- Brand panel --}}
    <div class="relative hidden flex-1 flex-col justify-between overflow-hidden bg-gradient-to-br from-accent-50 via-white to-brand-50 p-10 lg:flex">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="" class="size-12 rounded-full">
            <span class="text-lg font-bold">Livity<span class="text-brand-500">ICT</span> <span class="font-medium text-gray-500">HRMS</span></span>
        </div>
        <img src="{{ asset('images/banner.png') }}" alt="Illustration of a LivityICT team member working at a desk" class="mx-auto w-full max-w-2xl">
        <div class="max-w-xl">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900">Leave and contracts, <span class="text-brand-500">managed digitally</span>.</h2>
            <p class="mt-3 text-gray-600">Submit and track leave applications, follow approvals from Manager to Director, and keep contract renewals on schedule in one secure place.</p>
            <div class="mt-6 flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full bg-accent-500/10 px-3 py-1 text-accent-700">Digital leave forms</span>
                <span class="rounded-full bg-brand-500/10 px-3 py-1 text-brand-700">Approval workflow</span>
                <span class="rounded-full bg-accent-500/10 px-3 py-1 text-accent-700">Contract expiry reminders</span>
                <span class="rounded-full bg-brand-500/10 px-3 py-1 text-brand-700">POPIA-aware</span>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-[480px] lg:flex-none xl:w-[540px]">
        <div class="mx-auto w-full max-w-sm">
            <img src="{{ asset('images/logo.png') }}" alt="LivityICT" class="size-20 rounded-full shadow-md">
            <h1 class="mt-6 text-2xl font-bold tracking-tight">Sign in to your account</h1>
            <p class="mt-1 text-sm text-gray-500">LivityICT Human Resource Management System</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <x-field label="Username" name="username">
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" class="form-input">
                </x-field>
                <x-field label="Password" name="password">
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input">
                </x-field>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="form-check"> Keep me signed in on this device
                </label>
                <button class="btn-primary w-full py-2.5">Sign in</button>
            </form>

            <p class="mt-8 rounded-lg bg-gray-50 p-3 text-xs text-gray-500">
                Forgot your password? Contact the system administrator or your Manager to have it reset.
                Access is monitored and recorded in line with LivityICT policy and POPIA.
            </p>
        </div>
    </div>
</div>
</body>
</html>
