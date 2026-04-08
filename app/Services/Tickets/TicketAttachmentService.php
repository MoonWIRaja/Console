<?php

namespace Pterodactyl\Services\Tickets;

use RuntimeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Services\Security\SecurityArtifactInspectionService;

class TicketAttachmentService
{
    public function __construct(
        private TicketSettingsService $settings,
        private TicketUrlService $urlService,
        private SecurityArtifactInspectionService $inspection,
    ) {
    }

    /**
     * @param UploadedFile[] $files
     *
     * @return TicketAttachment[]
     */
    public function storeUploads(TicketMessage $message, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store(sprintf('tickets/%d/%d', $message->ticket_id, $message->id), [
                'disk' => $this->settings->attachmentDisk(),
            ]);

            $contents = $file->getRealPath() && is_file($file->getRealPath())
                ? file_get_contents($file->getRealPath())
                : null;

            $attachment = TicketAttachment::query()->create([
                'ticket_message_id' => $message->id,
                'disk' => $this->settings->attachmentDisk(),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256' => is_string($contents) ? hash('sha256', $contents) : null,
            ]);

            $this->inspection->inspectTicketAttachment($attachment, $contents);
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    public function importFromRemote(TicketMessage $message, string $url, string $filename, ?string $mimeType = null, ?string $discordAttachmentId = null): TicketAttachment
    {
        $response = Http::timeout(30)->get($url);
        if (!$response->successful()) {
            throw new RuntimeException('Failed to download a Discord attachment for ticket sync.');
        }

        $contents = $response->body();
        $path = sprintf(
            'tickets/%d/%d/import-%s-%s',
            $message->ticket_id,
            $message->id,
            $discordAttachmentId ?: uniqid(),
            preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename)
        );

        Storage::disk($this->settings->attachmentDisk())->put($path, $contents);

        $attachment = TicketAttachment::query()->create([
            'ticket_message_id' => $message->id,
            'disk' => $this->settings->attachmentDisk(),
            'path' => $path,
            'original_name' => $filename,
            'mime_type' => $mimeType ?: $response->header('Content-Type'),
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'discord_attachment_id' => $discordAttachmentId,
            'source_url' => $url,
        ]);

        $this->inspection->inspectTicketAttachment($attachment, $contents);

        return $attachment;
    }

    public function toArray(TicketAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => (int) $attachment->size_bytes,
            'download_url' => $this->urlService->signedAttachmentUrl($attachment),
            'source_url' => $attachment->source_url,
        ];
    }
}
