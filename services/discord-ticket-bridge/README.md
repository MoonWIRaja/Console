# Discord Ticket Bridge

Node sidecar ini dengar Discord gateway events untuk private ticket threads dan juga launcher interactions seperti button, select menu, dan modal submit. Semua event itu dihantar ke panel Laravel melalui internal HMAC bridge.

## Keperluan

- Node.js 20+
- Bot Discord yang sama seperti panel
- `MESSAGE CONTENT INTENT` diaktifkan pada Discord application
- Settings panel sudah diisi pada `/admin/tickets/settings`

## Environment

Salin `.env.example` dan isi:

- `DISCORD_BOT_TOKEN`
- `PANEL_INTERNAL_BASE_URL`
- `TICKET_BRIDGE_SHARED_SECRET`
- `DISCORD_RELAY_WEBHOOK_ID`
- `DISCORD_ENABLE_GUILD_MEMBERS` (`true` only if the privileged Server Members intent is enabled for the bot)
- `DISCORD_ENABLE_MESSAGE_CONTENT` (`true` only if the privileged Message Content intent is enabled for the bot)
- `TICKET_BRIDGE_HEARTBEAT_SECONDS`

`DISCORD_RELAY_WEBHOOK_ID` digunakan untuk abaikan mesej relay webhook sendiri supaya loop tak berlaku.
`DISCORD_ENABLE_GUILD_MEMBERS=true` hanya perlu jika anda mahu cache member/role Discord yang lebih lengkap pada sidecar.
`DISCORD_ENABLE_MESSAGE_CONTENT=true` diperlukan jika anda mahu mesej teks yang ditaip dalam Discord thread diimport semula ke panel. Launcher interactions tidak memerlukannya.

## Run

```bash
cd services/discord-ticket-bridge
npm install
npm start
```

## Lifecycle

- `MESSAGE_CREATE`, `MESSAGE_UPDATE`, `MESSAGE_DELETE`
- `THREAD_UPDATE`, `THREAD_DELETE`
- `INTERACTION_CREATE` untuk launcher `Payment`, `Refund`, dan `Support`
- heartbeat berkala ke `/api/internal/tickets/discord/heartbeat`

Semua request ke panel akan ditandatangani menggunakan:

- `X-Tickets-Timestamp`
- `X-Tickets-Nonce`
- `X-Tickets-Signature`

Signature dibina dengan `HMAC-SHA256` untuk payload `timestamp + "\n" + nonce + "\n" + body` berdasarkan `TICKET_BRIDGE_SHARED_SECRET`. Panel akan reject request yang timestamp terlalu lari, header tak lengkap, atau `nonce` yang dimainkan semula.
