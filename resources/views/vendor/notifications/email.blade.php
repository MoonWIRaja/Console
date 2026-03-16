<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style type="text/css" rel="stylesheet" media="all">
        @media only screen and (max-width: 640px) {
            .email-shell,
            .email-footer {
                width: 100% !important;
            }

            .email-body_cell,
            .email-header,
            .email-highlight_cell {
                padding: 24px !important;
            }

            .button {
                width: 100% !important;
            }
        }
    </style>
</head>

@php
    $companyName = config('mail.from.name') ?: config('app.name');
    $logoUrl = config('app.logo') ? asset(config('app.logo')) : asset('favicons/apple-touch-icon.png');
    $badgeLabel = match ($level ?? 'info') {
        'error' => 'Attention',
        'success' => 'Confirmed',
        default => 'Notification',
    };
    $accentColor = match ($level ?? 'info') {
        'error' => '#f97316',
        'success' => '#22c55e',
        default => '#f0b90b',
    };
    $buttonColor = match ($level ?? 'info') {
        'error' => '#ea580c',
        'success' => '#16a34a',
        default => '#f0b90b',
    };
    $buttonTextColor = match ($level ?? 'info') {
        'error', 'success' => '#ffffff',
        default => '#111827',
    };
@endphp

<body style="margin:0; padding:0; width:100%; background-color:#0b1220; color:#e5e7eb;">
    <span style="display:none !important; visibility:hidden; mso-hide:all; font-size:1px; color:#0b1220; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        {{ $subject ?? $companyName }}
    </span>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%; margin:0; padding:0; background-color:#0b1220;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table class="email-shell" width="640" cellpadding="0" cellspacing="0" role="presentation" style="width:640px; max-width:640px; background-color:#f8f6f1; border:1px solid #293244; border-radius:22px; overflow:hidden; box-shadow:0 26px 80px rgba(15,23,42,0.42), 0 10px 26px rgba(15,23,42,0.2);">
                    <tr>
                        <td style="height:8px; background-color:{{ $accentColor }};"></td>
                    </tr>
                    <tr>
                        <td class="email-header" style="padding:24px 30px 18px; background-color:#111827; border-bottom:1px solid #243041;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td valign="middle">
                                        <table cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td valign="middle" style="padding-right:14px;">
                                                    <div style="width:54px; height:54px; border-radius:16px; background-color:#ffffff; text-align:center; line-height:54px; border:1px solid rgba(17,24,39,0.08); box-shadow:0 10px 24px rgba(15,23,42,0.22);">
                                                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-width:38px; max-height:38px; vertical-align:middle;">
                                                    </div>
                                                </td>
                                                <td valign="middle">
                                                    <p style="margin:0; font-size:11px; line-height:1.2; letter-spacing:0.22em; text-transform:uppercase; color:#f8d36a; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                                                        Official Email
                                                    </p>
                                                    <p style="margin:7px 0 0; font-size:22px; line-height:1.25; font-weight:700; color:#ffffff; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                                                        {{ $companyName }}
                                                    </p>
                                                    <p style="margin:6px 0 0; font-size:12px; line-height:1.5; color:#94a3b8; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                                                        Professional service updates, billing confirmations, and account security notices.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="top" align="right">
                                        <span style="display:inline-block; padding:9px 14px; border-radius:999px; background-color:{{ $accentColor }}; color:#111827; font-size:10px; line-height:1; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif; box-shadow:inset 0 1px 0 rgba(255,255,255,0.28), 0 10px 18px rgba(15,23,42,0.18);">
                                            {{ $badgeLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-highlight_cell" style="padding:20px 30px 0; background-color:#f8f6f1;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e5decf; border-radius:18px; background-color:#fffcf5; box-shadow:0 12px 28px rgba(148,163,184,0.14);">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                                            {{ $subject ?? $companyName }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-body_cell" style="padding:30px; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                            <h1 style="margin:0 0 18px; color:#111827; font-size:28px; line-height:1.25; font-weight:700;">
                                @if (! empty($greeting))
                                    {{ $greeting }}
                                @else
                                    @if (($level ?? 'info') === 'error')
                                        Attention required
                                    @else
                                        Hello
                                    @endif
                                @endif
                            </h1>

                            @foreach ($introLines as $line)
                                <p style="margin:0 0 14px; color:#4b5563; font-size:15px; line-height:1.7;">
                                    {{ $line }}
                                </p>
                            @endforeach

                            @if (isset($actionText))
                                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:26px 0 26px;">
                                    <tr>
                                        <td align="left">
                                            <table cellpadding="0" cellspacing="0" role="presentation">
                                                <tr>
                                                    <td style="border-radius:999px; background-color:{{ $buttonColor }}; box-shadow:inset 0 1px 0 rgba(255,255,255,0.28), 0 12px 24px rgba(15,23,42,0.18);">
                                                        <a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" class="button" style="display:inline-block; min-width:220px; padding:14px 22px; border-radius:999px; color:{{ $buttonTextColor }}; font-size:13px; line-height:1; font-weight:700; letter-spacing:0.08em; text-align:center; text-decoration:none; text-transform:uppercase; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                                                            {{ $actionText }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @foreach ($outroLines as $line)
                                <p style="margin:0 0 14px; color:#4b5563; font-size:15px; line-height:1.7;">
                                    {{ $line }}
                                </p>
                            @endforeach

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:28px; border-top:1px solid #e5decf;">
                                <tr>
                                    <td style="padding-top:18px;">
                                        <p style="margin:0; color:#111827; font-size:14px; line-height:1.7; font-weight:600;">
                                            {!! nl2br(e($salutation ?? ("Regards,\n" . $companyName))) !!}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="email-footer" width="640" cellpadding="0" cellspacing="0" role="presentation" style="width:640px; max-width:640px; margin-top:18px;">
                    <tr>
                        <td align="center" style="padding:0 18px; font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
                            <p style="margin:0; color:#9ca3af; font-size:12px; line-height:1.7;">
                                Official correspondence from {{ $companyName }}.
                            </p>
                            <p style="margin:6px 0 0; color:#6b7280; font-size:11px; line-height:1.7;">
                                &copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
