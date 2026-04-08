<?php

namespace Pterodactyl\Services\Tickets;

use Illuminate\Support\Arr;

class TicketSettingsService
{
    public function enabled(): bool
    {
        return (bool) config('tickets.enabled', false);
    }

    public function autoCreateOnManualCheckout(): bool
    {
        return (bool) config('tickets.auto_create_on_manual_checkout', true);
    }

    public function resolveOnPaid(): bool
    {
        return (bool) config('tickets.resolve_on_paid', true);
    }

    public function launcherChannelId(): ?string
    {
        return $this->nullableString(config('tickets.discord.launcher_channel_id'));
    }

    public function activeParentChannelId(): ?string
    {
        return $this->nullableString(config('tickets.discord.active_parent_channel_id'));
    }

    public function logChannelId(): ?string
    {
        return $this->nullableString(config('tickets.discord.log_channel_id'));
    }

    public function launcherMessageId(): ?string
    {
        return $this->nullableString(config('tickets.discord.launcher_message_id'));
    }

    public function relayWebhookId(): ?string
    {
        return $this->nullableString(config('tickets.discord.relay_webhook_id'));
    }

    public function relayWebhookToken(): ?string
    {
        return $this->nullableString(config('tickets.discord.relay_webhook_token'));
    }

    public function bridgeSharedSecret(): ?string
    {
        return $this->nullableString(config('tickets.bridge.shared_secret'));
    }

    public function bridgeClockSkewSeconds(): int
    {
        return max((int) config('tickets.bridge.clock_skew_seconds', 60), 5);
    }

    public function bridgeNonceTtlSeconds(): int
    {
        return max((int) config('tickets.bridge.nonce_ttl_seconds', 300), 30);
    }

    public function lastHeartbeatAt(): ?string
    {
        return $this->nullableString(config('tickets.bridge.last_heartbeat_at'));
    }

    public function lastHeartbeatMeta(): array
    {
        return Arr::wrap(config('tickets.bridge.last_heartbeat_meta', []));
    }

    public function attachmentDisk(): string
    {
        return (string) config('tickets.attachments.disk', 'local');
    }

    public function maxFilesPerMessage(): int
    {
        return max((int) config('tickets.attachments.max_files_per_message', 5), 1);
    }

    public function maxFileSizeMb(): int
    {
        return max((int) config('tickets.attachments.max_file_size_mb', 20), 1);
    }

    public function allowedMimeTypes(): array
    {
        return array_values(array_filter(array_map('trim', Arr::wrap(config('tickets.attachments.allowed_mime_types', [])))));
    }

    public function staffRoleIds(): array
    {
        return array_values(array_filter(array_map('trim', Arr::wrap(config('tickets.discord.staff_role_ids', [])))));
    }

    public function staffRoleConfigured(): bool
    {
        return $this->staffRoleIds() !== [];
    }

    public function hasDiscordThreadConfig(): bool
    {
        return filled($this->activeParentChannelId());
    }

    public function hasLauncherConfig(): bool
    {
        return filled($this->launcherChannelId()) && $this->hasBotToken();
    }

    public function hasRelayWebhook(): bool
    {
        return filled($this->relayWebhookId()) && filled($this->relayWebhookToken());
    }

    public function hasBotToken(): bool
    {
        return filled(config('services.discord.bot_token'));
    }

    public function hasInteractionConfig(): bool
    {
        return $this->hasBotToken();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
