<x-layouts.app title="Notifications">
    <x-page-header title="Notifications">
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            <button class="btn-secondary"><x-icon name="check" class="size-4" /> Mark all as read</button>
        </form>
    </x-page-header>

    <div class="card overflow-hidden">
        @if ($notifications->isEmpty())
            <x-empty icon="bell" title="No notifications" text="Updates about your leave applications and contracts will appear here." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    <li>
                        <a href="{{ route('notifications.open', $n->id) }}" class="flex items-start gap-4 px-5 py-4 hover:bg-brand-50/40 {{ $n->read_at ? '' : 'bg-brand-50/50' }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ ($n->data['icon'] ?? '') === 'contract' ? 'bg-accent-100 text-accent-700' : 'bg-brand-100 text-brand-700' }}">
                                <x-icon :name="($n->data['icon'] ?? '') === 'contract' ? 'briefcase' : 'calendar'" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm {{ $n->read_at ? 'text-gray-700' : 'font-semibold text-gray-900' }}">{{ $n->data['title'] ?? 'Notification' }}</p>
                                @if (! empty($n->data['body']))<p class="mt-0.5 truncate text-sm text-gray-500">{{ $n->data['body'] }}</p>@endif
                                <p class="mt-0.5 text-xs text-gray-400">{{ $n->created_at->diffForHumans() }}</p>
                            </div>
                            @unless ($n->read_at)<span class="mt-2 size-2 rounded-full bg-brand-500"></span>@endunless
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $notifications->links() }}
        @endif
    </div>
</x-layouts.app>
