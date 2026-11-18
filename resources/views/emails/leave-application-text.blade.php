LivityICT HR Management
=======================

{{ $content['headline'] }}

Hello {{ $recipientName }},

{{ $content['intro'] }}

APPLICATION DETAILS
@foreach ($details as $label => $value)
- {{ $label }}: {{ $value }}
@endforeach
@if ($content['note'])

{{ $content['note'] }}
@endif
@if ($comment)

{{ $commentLabel }}:
"{{ $comment }}"
@endif

{{ $content['action'] }}: {{ $url }}

--
This is an automated message from the LivityICT HR Management System. Please do not reply to this email.
