<x-layouts.app title="Backups">
    <x-page-header title="Database backups" subtitle="Automatic backups run daily at 01:00. The latest {{ config('hrms.backup_keep') }} are kept.">
        <form method="POST" action="{{ route('admin.backups.store') }}">@csrf
            <button class="btn-primary"><x-icon name="database" class="size-4" /> Back up now</button>
        </form>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            @if (empty($backups))
                <x-empty icon="database" title="No backups yet" text="Create the first backup now, and make sure the scheduler is running." />
            @else
                <table class="table">
                    <thead><tr><th>File</th><th>Created</th><th>Size</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($backups as $b)
                        <tr>
                            <td class="font-mono text-xs">{{ $b['name'] }}</td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($b['time'])->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ number_format($b['size'] / 1024, 1) }} KB</td>
                            <td class="text-right"><a href="{{ route('admin.backups.download', $b['name']) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Download</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">Restore procedure</h2></div>
            <div class="card-body space-y-2 text-sm text-gray-600">
                <p>Restoring replaces all current data, so it is done by the administrator from the server command line:</p>
                <pre class="overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100">php artisan hrms:restore hrms-YYYYMMDD-HHMMSS.sql.gz</pre>
                <p>Uploaded documents are stored in <code class="font-mono text-xs">storage/app/private</code>. Include this folder in the server's file backups.</p>
                <p>Test a restore on a separate copy of the system regularly to confirm backups are usable.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
