{{-- Shared printable / PDF layout for leave reports. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111; }
        .head { border-bottom: 3px solid #F7941D; padding-bottom: 6px; margin-bottom: 10px; }
        .head table td { border: 0; padding: 0; vertical-align: middle; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 11px; margin: 14px 0 4px; color: #006e35; }
        .muted { color: #666; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #fff7ec; color: #773e11; text-align: left; font-weight: bold; border-bottom: 1px solid #F7941D; padding: 4px; }
        table.data td { border-bottom: 1px solid #e5e7eb; padding: 4px; vertical-align: top; }
        .num { text-align: right; }
        .summary { width: 49%; display: inline-block; vertical-align: top; margin-right: 1%; }
        .foot { margin-top: 14px; font-size: 8px; color: #888; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@unless ($pdf)<div class="no-print"><button onclick="window.print()">Print</button></div>@endunless
<div class="head">
    <table><tr>
        @if (! $pdf || extension_loaded('gd'))
            <td style="width:56px"><img src="{{ $pdf ? public_path('images/logo.png') : asset('images/logo.png') }}" style="width:48px;height:48px"></td>
        @endif
        <td><h1>{{ $title }}</h1><div class="muted">LivityICT (Pty) Ltd · {{ $subtitle }} · Generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }}</div></td>
    </tr></table>
</div>

@if (! empty($summary))
    @foreach ($summary as $heading => $rows)
        <div class="summary">
            <h2>{{ ucfirst($heading) }}</h2>
            <table class="data">
                <tr><th></th><th class="num">Applications</th><th class="num">Employees</th><th class="num">Days</th><th class="num">Approved days</th></tr>
                @foreach ($rows as $label => $r)
                    <tr><td>{{ $label }}</td><td class="num">{{ $r['applications'] }}</td><td class="num">{{ $r['employees'] }}</td><td class="num">{{ $r['days'] }}</td><td class="num">{{ $r['approved_days'] }}</td></tr>
                @endforeach
            </table>
        </div>
    @endforeach
@endif

<h2>Applications ({{ $applications->count() }})</h2>
<table class="data">
    <tr><th>Reference</th><th>Employee</th><th>Emp. no.</th><th>Department</th><th>Category</th><th>Start</th><th>End</th><th>Return</th><th class="num">Days</th><th>Submitted</th><th>Status</th><th>Manager comment</th><th>Director</th><th>Decided</th></tr>
    @foreach ($applications as $a)
        <tr>
            <td>{{ $a->reference }}</td><td>{{ $a->employee_name }}</td><td>{{ $a->employee_number }}</td><td>{{ $a->department }}</td>
            <td>{{ $a->category->name }}</td><td>{{ $a->start_date->format('d/m/Y') }}</td><td>{{ $a->end_date->format('d/m/Y') }}</td>
            <td>{{ $a->return_date->format('d/m/Y') }}</td><td class="num">{{ (float) $a->working_days }}</td><td>{{ $a->submitted_at?->format('d/m/Y') }}</td>
            <td>{{ $a->status->label() }}</td><td>{{ \Illuminate\Support\Str::limit($a->manager_comment, 80) }}</td>
            <td>{{ $a->director?->name }}</td><td>{{ $a->decided_at?->format('d/m/Y H:i') }}</td>
        </tr>
    @endforeach
    <tr><td colspan="8" style="font-weight:bold">Total</td><td class="num" style="font-weight:bold">{{ (float) $applications->sum('working_days') }}</td><td colspan="5"></td></tr>
</table>
<p class="foot">Confidential: contains personal information protected under POPIA. Do not distribute beyond authorised management.</p>
</body>
</html>
