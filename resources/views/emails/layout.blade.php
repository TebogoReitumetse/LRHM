@php
    $logoPath = public_path('images/logo.png');
    $logoSrc = isset($message) && file_exists($logoPath) ? $message->embed($logoPath) : asset('images/logo.png');
    $font = 'Instrument Sans, Segoe UI, Helvetica, Arial, sans-serif';
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('subject', 'LivityICT HRMS')</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; background-color: #f3f4f6; }
        a { color: #e07a0b; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .stack td { display: block !important; width: 100% !important; }
            .detail-label { padding-bottom: 2px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:{{ $font }}; color:#111827; -webkit-font-smoothing:antialiased;">
    {{-- Inbox preview text --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">@yield('preheader')&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- Header --}}
                    <tr>
                        <td class="px" style="background-color:#030712; border-radius:14px 14px 0 0; padding:22px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:14px; vertical-align:middle;">
                                        <img src="{{ $logoSrc }}" width="52" height="52" alt="LivityICT" style="display:block; width:52px; height:52px; border:0; border-radius:50%;">
                                    </td>
                                    <td style="vertical-align:middle; font-family:{{ $font }};">
                                        <div style="font-size:18px; line-height:22px; font-weight:700; color:#ffffff;">Livity<span style="color:#f9a43c;">ICT</span></div>
                                        <div style="font-size:11px; line-height:16px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:#2cc273;">HR Management</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {{-- Brand stripe: orange and green from the logo --}}
                    <tr>
                        <td style="font-size:0; line-height:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="70%" height="4" style="background-color:#f7941d; font-size:0; line-height:0;">&nbsp;</td>
                                    <td width="30%" height="4" style="background-color:#00a14b; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="px" style="background-color:#ffffff; padding:36px 32px 32px; font-family:{{ $font }};">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="px" style="background-color:#f9fafb; border-top:1px solid #e5e7eb; border-radius:0 0 14px 14px; padding:20px 32px; font-family:{{ $font }}; font-size:12px; line-height:18px; color:#6b7280;">
                            This is an automated message from the LivityICT HR Management System. Please do not reply to this email.
                            <br>&copy; {{ now()->year }} LivityICT (Pty) Ltd. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
