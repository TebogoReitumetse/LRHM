<x-layouts.app title="Public holidays">
    <x-page-header title="Public holidays" subtitle="Excluded when calculating working days. Weekends are always excluded." />

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($years as $y)
            <a href="{{ route('admin.holidays.index', ['year' => $y]) }}" @class(['badge px-3 py-1', 'bg-gray-900 text-white ring-gray-900' => $y == $year, 'bg-white text-gray-600 ring-gray-300' => $y != $year])>{{ $y }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            @if ($holidays->isEmpty())
                <x-empty icon="sun" title="No holidays captured for {{ $year }}" text="Add the official public holidays so working days are calculated correctly." />
            @else
                <table class="table">
                    <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($holidays as $h)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $h->date->format('d M Y') }}</td>
                            <td class="{{ $h->date->isWeekend() ? 'text-gray-400' : '' }}">{{ $h->date->format('l') }}</td>
                            <td>{{ $h->name }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.holidays.destroy', $h) }}" onsubmit="return confirm('Remove this holiday?')">@csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.holidays.store') }}" class="card self-start">
            @csrf
            <div class="card-header"><h2 class="card-title">Add public holiday</h2></div>
            <div class="card-body space-y-3">
                <x-field label="Date" name="date" required help="If a holiday falls on a Sunday, also add the following Monday."><input type="date" name="date" class="form-input" required></x-field>
                <x-field label="Name" name="name" required><input name="name" class="form-input" required></x-field>
                <button class="btn-primary w-full">Add holiday</button>
            </div>
        </form>
    </div>
</x-layouts.app>
