<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111; }
        .head { border-bottom: 3px solid #F7941D; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 16px; margin: 0; } .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #ebfbf1; color: #05572d; text-align: left; border-bottom: 1px solid #00A14B; padding: 4px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 4px; vertical-align: top; }
        .no-print { margin-bottom: 12px; } @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@unless ($pdf)<div class="no-print"><button onclick="window.print()">Print</button></div>@endunless
<div class="head"><h1>{{ $title }}</h1><div class="muted">LivityICT (Pty) Ltd · {{ $subtitle }} · {{ $contracts->count() }} records</div></div>
<table>
    <tr><th>Reference</th><th>Name</th><th>Emp. no.</th><th>Department</th><th>Type</th><th>Start</th><th>End</th><th>Duration</th><th>Remaining</th><th>Status</th><th>Decision</th></tr>
    @foreach ($contracts as $c)
        <tr>
            <td>{{ $c->reference }}</td><td>{{ $c->full_name }}</td><td>{{ $c->employee_number }}</td><td>{{ $c->department?->name }}</td>
            <td>{{ $c->typeLabel() }}</td><td>{{ $c->start_date->format('d/m/Y') }}</td><td>{{ $c->end_date->format('d/m/Y') }}</td>
            <td>{{ $c->durationLabel() }}</td><td>{{ $c->remainingLabel() }}</td><td>{{ $c->status->label() }}</td>
            <td>{{ match ($c->renewal_decision) { 'renew' => 'Renewed', 'not_renew' => 'Not renewed', default => '' } }}</td>
        </tr>
    @endforeach
</table>
<p class="muted" style="margin-top:12px;font-size:8px">Confidential: contains personal information protected under POPIA.</p>
</body>
</html>
