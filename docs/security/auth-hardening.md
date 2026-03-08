# Auth Hardening Deployment Guide

This repo now includes app-layer anti-abuse controls (risk scoring, honeypot, adaptive Turnstile) and infrastructure templates.

## 1) Environment

Set these variables in `.env`:

- `TURNSTILE_ENABLED=true`
- `TURNSTILE_SITE_KEY=...`
- `TURNSTILE_SECRET_KEY=...`
- `SECURITY_TRUSTED_IPS=127.0.0.1/32,10.0.0.0/8`
- `SECURITY_ALERT_EMAIL=security@example.com`

Optional tuning:

- `SECURITY_RISK_CHALLENGE_THRESHOLD=10`
- `SECURITY_RISK_LOCK_SHORT_THRESHOLD=18`
- `SECURITY_RISK_LOCK_LONG_THRESHOLD=30`
- `SECURITY_RISK_LOCK_SHORT_MINUTES=15`
- `SECURITY_RISK_LOCK_LONG_MINUTES=360`
- `SECURITY_RATE_PROFILE=balanced`

## 2) Cloudflare WAF / Rate Limit (Managed Challenge)

Create a custom rule targeting auth routes:

```text
(http.request.uri.path in {"/auth/login" "/auth/login/checkpoint" "/auth/signup" "/auth/signup/verify" "/auth/password" "/auth/password/reset"})
```

Recommended actions:

- Managed Challenge first.
- Escalate to temporary block for repeated offenders.
- Add trusted admin/VPN IP list to skip challenge when needed.

## 3) Nginx origin fallback limits

1. Refresh Cloudflare real IP ranges:

```bash
scripts/security/update-cloudflare-realip.sh /etc/nginx/cloudflare-realip.conf
```

2. Copy templates:

- `deploy/security/nginx/pterodactyl-auth-protection.conf` -> `/etc/nginx/conf.d/pterodactyl-auth-protection.conf`
- Merge `deploy/security/nginx/pterodactyl-auth-locations.conf` location blocks into your panel server block.

3. Validate and reload:

```bash
nginx -t && systemctl reload nginx
```

## 4) Honeyport listener

Install systemd unit:

```bash
cp deploy/security/systemd/pterodactyl-honeyport.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now pterodactyl-honeyport.service
```

The listener records hits via `AuthSecurityService`, raises risk score, and can trigger alert email.

## 5) Validation checklist

- Login/signup/forgot/reset works normally for legit user.
- Bots triggering honeypot fields receive generic retry errors.
- Repeated failures trigger `challenge_required` and lock windows.
- Alert email received for lock/honeyport escalation.
