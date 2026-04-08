<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} - Verify Your Account</title>
</head>
@php
    $companyName = config('mail.from.name') ?: $appName;
    $logoUrl = config('app.logo') ? asset(config('app.logo')) : asset('favicons/apple-touch-icon.png');
@endphp
<body style="margin:0;padding:0;background-color:#0b1220;color:#e5e7eb;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0b1220;padding:26px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;border:1px solid #293244;border-radius:22px;background-color:#f8f6f1;overflow:hidden;box-shadow:0 26px 80px rgba(15,23,42,0.42), 0 10px 26px rgba(15,23,42,0.2);">
                    <tr>
                        <td style="height:8px;background-color:#f0b90b;"></td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 18px;border-bottom:1px solid #243041;background-color:#111827;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="middle">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td valign="middle" style="padding-right:14px;">
                                                    <div style="width:54px;height:54px;border-radius:16px;background-color:#ffffff;text-align:center;line-height:54px;border:1px solid rgba(17,24,39,0.08);box-shadow:0 10px 24px rgba(15,23,42,0.22);">
                                                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-width:38px;max-height:38px;vertical-align:middle;">
                                                    </div>
                                                </td>
                                                <td valign="middle">
                                                    <p style="margin:0;font-size:11px;letter-spacing:0.22em;color:#f8d36a;font-weight:700;text-transform:uppercase;">Security Email</p>
                                                    <p style="margin:7px 0 0;font-size:22px;line-height:1.25;color:#ffffff;font-weight:700;">{{ $companyName }}</p>
                                                    <p style="margin:6px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;">Account protection and verification workflow.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="top" align="right">
                                        <span style="display:inline-block;padding:9px 14px;border-radius:999px;background-color:#f0b90b;color:#111827;font-size:10px;line-height:1;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;box-shadow:inset 0 1px 0 rgba(255,255,255,0.28), 0 10px 18px rgba(15,23,42,0.18);">Secure Code</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 20px;background-color:#f8f6f1;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5decf;border-radius:18px;background-color:#fffcf5;margin-bottom:20px;box-shadow:0 12px 28px rgba(148,163,184,0.14);">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7280;">Verify Your Account</p>
                                    </td>
                                </tr>
                            </table>
                            <h1 style="margin:0 0 12px;font-size:28px;line-height:1.25;color:#111827;">Verify your account</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4b5563;">
                                Hi {{ $recipientName }}, use the verification code below to activate your account.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 8px;">
                                <tr>
                                    <td align="center" style="background-color:#111827;border:1px solid #334155;border-radius:16px;padding:18px 10px;box-shadow:0 18px 32px rgba(15,23,42,0.18);">
                                        <span style="display:inline-block;font-size:36px;line-height:1;letter-spacing:10px;font-weight:700;color:#f8d36a;">
                                            {{ $pin }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0;font-size:13px;line-height:1.7;color:#6b7280;">
                                This code expires in {{ $expiresInMinutes }} minutes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px;background-color:#f8f6f1;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background-color:#f0b90b;box-shadow:inset 0 1px 0 rgba(255,255,255,0.28), 0 12px 24px rgba(15,23,42,0.18);">
                                        <a href="{{ $panelUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:14px 22px;font-size:12px;line-height:1;color:#111827;font-weight:700;letter-spacing:0.08em;text-decoration:none;text-transform:uppercase;">
                                            Open Panel
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 28px;background-color:#f8f6f1;">
                            <p style="margin:0;font-size:12px;line-height:1.8;color:#6b7280;">
                                If you did not request this, you can ignore this email safely.
                            </p>
                            <p style="margin:14px 0 0;font-size:11px;line-height:1.8;color:#9ca3af;">
                                Official security correspondence from {{ $companyName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
