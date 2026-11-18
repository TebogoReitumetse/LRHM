@extends('emails.layout')

@php
    /** Colours follow the HRMS theme: brand orange, accent green, plus amber/red for attention states. */
    $tones = [
        'brand' => ['bar' => '#f7941d', 'soft' => '#fff7ec', 'text' => '#934a10'],
        'success' => ['bar' => '#00a14b', 'soft' => '#ebfbf1', 'text' => '#05572d'],
        'warning' => ['bar' => '#f59e0b', 'soft' => '#fffbeb', 'text' => '#92400e'],
        'danger' => ['bar' => '#dc2626', 'soft' => '#fef2f2', 'text' => '#b91c1c'],
        'neutral' => ['bar' => '#6b7280', 'soft' => '#f3f4f6', 'text' => '#374151'],
    ];
    $tone = $tones[$content['tone']] ?? $tones['neutral'];
    $font = 'Instrument Sans, Segoe UI, Helvetica, Arial, sans-serif';
@endphp

@section('subject', $subject)
@section('preheader', $content['intro'])

@section('content')
    {{-- Status pill --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
        <tr>
            <td style="background-color:{{ $tone['soft'] }}; border-radius:999px; padding:5px 12px; font-family:{{ $font }}; font-size:11px; line-height:14px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; color:{{ $tone['text'] }};">
                <span style="color:{{ $tone['bar'] }};">&#9679;</span>&nbsp; {{ $application->status->label() }}
            </td>
        </tr>
    </table>

    <h1 style="margin:0 0 20px; font-family:{{ $font }}; font-size:24px; line-height:32px; font-weight:700; color:#111827;">{{ $content['headline'] }}</h1>

    <p style="margin:0 0 12px; font-size:15px; line-height:24px; color:#374151;">Hello {{ $recipientName }},</p>
    <p style="margin:0 0 24px; font-size:15px; line-height:24px; color:#374151;">{{ $content['intro'] }}</p>

    {{-- Application summary --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb; border-left:4px solid {{ $tone['bar'] }}; border-radius:10px; background-color:#f9fafb;">
        <tr>
            <td style="padding:6px 20px 10px;">
                <div style="padding:12px 0 6px; font-family:{{ $font }}; font-size:11px; line-height:16px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#6b7280;">Application details</div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    @foreach ($details as $label => $value)
                        <tr class="stack">
                            <td class="detail-label" width="38%" valign="top" style="padding:8px 12px 8px 0; {{ $loop->last ? '' : 'border-bottom:1px solid #eef0f3;' }} font-family:{{ $font }}; font-size:13px; line-height:20px; color:#6b7280;">{{ $label }}</td>
                            <td valign="top" style="padding:8px 0; {{ $loop->last ? '' : 'border-bottom:1px solid #eef0f3;' }} font-family:{{ $font }}; font-size:14px; line-height:20px; font-weight:600; color:#111827;">
                                @if ($label === 'Status')
                                    <span style="color:{{ $tone['text'] }};">{{ $value }}</span>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if ($content['note'])
        <p style="margin:20px 0 0; font-size:14px; line-height:22px; color:#374151;">{{ $content['note'] }}</p>
    @endif

    @if ($comment)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
            <tr>
                <td style="background-color:{{ $tone['soft'] }}; border-radius:10px; padding:14px 18px; font-family:{{ $font }};">
                    <div style="font-size:11px; line-height:16px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:{{ $tone['text'] }};">{{ $commentLabel }}</div>
                    <div style="margin-top:4px; font-size:14px; line-height:22px; color:#1f2937; font-style:italic;">&ldquo;{{ $comment }}&rdquo;</div>
                </td>
            </tr>
        </table>
    @endif

    {{-- Call to action --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;">
        <tr>
            <td align="center" bgcolor="#f7941d" style="border-radius:8px; background-color:#f7941d;">
                <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 26px; font-family:{{ $font }}; font-size:15px; line-height:20px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $content['action'] }} &rarr;</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0; padding-top:20px; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; color:#9ca3af;">
        If the button does not work, copy this link into your browser:<br>
        <a href="{{ $url }}" style="color:#e07a0b; word-break:break-all;">{{ $url }}</a>
    </p>
@endsection
