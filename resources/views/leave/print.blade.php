@php
    use App\Enums\LeaveStatus;
    $a = $application;
    $approved = $a->status === LeaveStatus::Approved;
    $declined = $a->status === LeaveStatus::Declined;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Leave Application Form {{ $a->reference }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 14px; margin: 30px auto; max-width: 780px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #999; padding-bottom: 10px; }
        .head img { height: 64px; }
        .head .addr { text-align: right; font-size: 12px; line-height: 1.5; font-weight: bold; border-left: 1px solid #999; padding-left: 12px; }
        h1 { font-size: 26px; margin: 26px 0 10px; letter-spacing: .5px; }
        h2 { font-size: 17px; margin: 22px 0 8px; }
        ul { margin: 0; padding-left: 40px; }
        li { margin: 7px 0; }
        .line { display: inline-block; border-bottom: 1px solid #111; min-width: 260px; padding: 0 6px; vertical-align: bottom; }
        .line.short { min-width: 140px; }
        .line.wide { min-width: 420px; }
        .box { display: inline-block; width: 14px; height: 14px; border: 1px solid #111; text-align: center; line-height: 14px; font-size: 12px; font-weight: bold; margin-right: 6px; vertical-align: -2px; }
        .types li { list-style: disc; }
        .sig { height: 48px; vertical-align: bottom; }
        .row { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-top: 14px; }
        .muted { color: #666; font-size: 11px; margin-top: 30px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 10mm auto; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button></div>

<div class="head">
    <img src="{{ asset('images/logo.png') }}" alt="LivityICT">
    <div class="addr">Mahikeng<br>2745<br>Tel: 018 381 1418<br>Email: info@livityict.co.za</div>
</div>

<h1>LEAVE APPLICATION FORM</h1>

<h2>1. EMPLOYEE DETAILS</h2>
<ul>
    <li><strong>Employee Name:</strong> <span class="line wide">{{ $a->employee_name }}</span></li>
    <li><strong>Employee ID Number:</strong> <span class="line wide">{{ $a->id_number }}</span></li>
    <li><strong>Department/Position:</strong> <span class="line wide">{{ $a->departmentPosition() === '—' ? '' : $a->departmentPosition() }}</span></li>
</ul>

<h2>2. LEAVE REQUEST DETAILS</h2>
<p><strong>Type of Leave (Tick one):</strong></p>
<ul class="types">
    @foreach ($categories as $c)
        @php $ticked = $c->id === $a->leave_category_id; @endphp
        <li>
            <span class="box">{{ $ticked ? '✓' : '' }}</span>{{ $c->name }}{{ $c->requiresSpecification() ? ':' : '' }}
            @if ($note = $c->formNote())
                ({{ $note }})
            @endif
            @if ($c->requiresSpecification())
                <span class="line">{{ $ticked ? $a->other_leave_type : '' }}</span>
            @endif
        </li>
    @endforeach
</ul>

<p><strong>Period of Leave:</strong></p>
<ul>
    <li><strong>Start Date:</strong> <span class="line">{{ $a->start_date->format('d F Y') }}</span></li>
    <li><strong>End Date:</strong> <span class="line">{{ $a->end_date->format('d F Y') }}</span></li>
    <li><strong>Total Working Days:</strong> <span class="line short">{{ (float) $a->working_days }}</span>
        @if ($a->hasUnpaidDays())({{ $a->daysSplitLabel() }})@endif</li>
    <li><strong>Date Returning to Work:</strong> <span class="line short">{{ $a->return_date->format('d F Y') }}</span></li>
</ul>
@if ($a->documents->isNotEmpty())
    <p style="padding-left:40px;font-size:12px">Attached: {{ $a->documents->pluck('original_name')->implode(', ') }}</p>
@endif

<h2>3. EMPLOYEE DECLARATION</h2>
<p>I confirm that the above information is correct and that I will ensure all urgent tasks are handed over before my leave begins.</p>
<div class="row">
    <div><strong>Employee Signature:</strong>
        <span class="line wide" style="min-width:300px">@if ($a->employee_signature)<img class="sig" src="{{ $a->employee_signature }}" alt="">@endif</span>
    </div>
    <div><strong>Date:</strong> <span class="line short">{{ ($a->employee_signed_on ?? $a->declaration_accepted_at)?->format('d F Y') }}</span></div>
</div>

<h2>4. OFFICE USE ONLY (Approval)</h2>
<ul>
    <li><span class="box">{{ $approved ? 'X' : '' }}</span><strong>Approved</strong></li>
    <li><span class="box">{{ $declined ? 'X' : '' }}</span><strong>Declined</strong> (Reason: <span class="line wide" style="min-width:380px">{{ $declined ? $a->director_comment : '' }}</span>)</li>
</ul>
<div class="row">
    <div><strong>Manager/Owner Signature:</strong>
        <span class="line" style="min-width:280px">@if ($a->approver_signature)<img class="sig" src="{{ $a->approver_signature }}" alt="">@endif</span>
    </div>
    <div><strong>Date:</strong> <span class="line short">{{ $a->decided_at?->format('d/m/Y') }}</span></div>
</div>
<div class="row" style="justify-content:flex-start">
    <div><strong>Print Name:</strong> <span class="line wide">{{ $a->approver_name ?? ($a->decided_at ? $a->director?->name : '') }}</span></div>
</div>
<div class="row">
    <div><strong>Director Signature:</strong>
        <span class="line" style="min-width:280px">@if ($a->director_signature)<img class="sig" src="{{ $a->director_signature }}" alt="">@endif</span>
    </div>
    <div><strong>Director:</strong> <span class="line short">{{ $a->decided_at ? $a->director?->name : '' }}</span></div>
</div>

<p class="muted">
    Reference {{ $a->reference ?? 'DRAFT' }} · Status: {{ $a->status->label() }} · Printed {{ now()->format('d M Y H:i') }}.
    Electronically generated by the LivityICT HRMS. Confidential: contains personal information protected under POPIA.
</p>
</body>
</html>
