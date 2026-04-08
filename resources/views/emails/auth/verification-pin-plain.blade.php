{{ config('mail.from.name') ?: $appName }} - Official Security Email

Hi {{ $recipientName }},

Use the verification code below to activate your account:

{{ $pin }}

This code expires in {{ $expiresInMinutes }} minutes.

Open panel: {{ $panelUrl }}

If you did not request this, you can ignore this email safely.

Regards,
{{ config('mail.from.name') ?: $appName }}
