<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} - Verify Your Account</title>
</head>
<body style="margin:0;padding:0;background-color:#050505;color:#f8f6ef;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#050505;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;border:1px solid #1f2a14;border-radius:14px;background-color:#0C0C0C;overflow:hidden;">
                    <tr>
                        <td style="padding:18px 24px;border-bottom:1px solid #1f2a14;background-color:#050505;">
                            <p style="margin:0;font-size:12px;letter-spacing:1.6px;color:#d9ff93;font-weight:700;text-transform:uppercase;">{{ $appName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 20px;">
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.35;color:#f8f6ef;">Verify your account</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#d7d7d7;">
                                Hi {{ $recipientName }}, use the verification code below to activate your account.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 8px;">
                                <tr>
                                    <td align="center" style="background-color:#0C0C0C;border:1px solid #2f5e1b;border-radius:10px;padding:14px 10px;">
                                        <span style="display:inline-block;font-size:34px;line-height:1;letter-spacing:10px;font-weight:700;color:#d9ff93;">
                                            {{ $pin }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0;font-size:13px;line-height:1.6;color:#9ca3af;">
                                This code expires in {{ $expiresInMinutes }} minutes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:999px;background-color:#a3ff12;">
                                        <a href="{{ $panelUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:11px 18px;font-size:12px;line-height:1;color:#0a1202;font-weight:700;letter-spacing:0.8px;text-decoration:none;text-transform:uppercase;">
                                            Open Panel
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 26px;">
                            <p style="margin:0;font-size:12px;line-height:1.7;color:#6b7280;">
                                If you did not request this, you can ignore this email safely.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
