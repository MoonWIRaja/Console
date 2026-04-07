<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Arr;
use Pterodactyl\Models\TicketAttachment;

class SecurityArtifactInspectionService
{
    public function __construct(
        private SecurityCenterSettingsService $settings,
        private SecurityOrchestratorService $orchestrator,
    ) {
    }

    public function inspectTicketAttachment(TicketAttachment $attachment, ?string $contents = null): void
    {
        if (!(bool) config('security.upload.enabled', true)) {
            return;
        }

        $signals = [];
        $filename = strtolower((string) $attachment->original_name);
        $mime = strtolower((string) $attachment->mime_type);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $suspiciousExtensions = $this->settings->uploadSuspiciousExtensions();

        if ($extension !== '' && in_array($extension, $suspiciousExtensions, true)) {
            $signals[] = 'suspicious_extension:' . $extension;
        }

        if (preg_match('/\.(php|phtml|phar|js|exe|dll|jar|sh|ps1|bat|cmd|scr|com|msi)\.[a-z0-9]{2,5}$/i', $filename) === 1) {
            $signals[] = 'double_extension';
        }

        if ($mime !== '' && preg_match('/(x-php|x-sh|x-msdownload|x-dosexec|javascript|powershell|x-executable|x-shellscript)/i', $mime) === 1) {
            $signals[] = 'suspicious_mime:' . $mime;
        }

        if (is_string($contents) && $contents !== '') {
            if (str_starts_with($contents, "MZ")) {
                $signals[] = 'windows_executable_header';
            }

            if (str_starts_with($contents, "\x7F" . 'ELF')) {
                $signals[] = 'elf_binary_header';
            }

            if (preg_match('/^\#\!\s*\/.*\b(bash|sh|zsh|python|python3|perl|php|pwsh|powershell)\b/i', $contents) === 1) {
                $signals[] = 'script_shebang';
            }

            if (preg_match('/<\?(php|=)|eval\s*\(|base64_decode\s*\(|shell_exec\s*\(|passthru\s*\(/i', $contents) === 1) {
                $signals[] = 'script_payload_pattern';
            }
        }

        if ($signals === []) {
            return;
        }

        $confidence = min(98, 60 + (count($signals) * 10));
        $executeActions = (bool) config('security.upload.quarantine_on_suspicious', true);

        $this->orchestrator->record('upload_suspicious_attachment', [
            'severity' => count($signals) >= 3 ? 'high' : 'medium',
            'confidence' => $confidence,
            'source_ip' => null,
            'target' => $attachment,
            'summary' => 'Attachment matched custom suspicious-file heuristics and was routed to quarantine review.',
            'evidence' => [
                'attachment_id' => $attachment->id,
                'ticket_message_id' => $attachment->ticket_message_id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'signals' => $signals,
            ],
            'execute_actions' => $executeActions,
            'blocked' => $executeActions,
            'mitigation_stage' => $executeActions ? SecurityVocabulary::STAGE_QUARANTINE : SecurityVocabulary::STAGE_OBSERVE,
            'verdict' => $executeActions ? SecurityVocabulary::VERDICT_QUARANTINED : SecurityVocabulary::VERDICT_OBSERVED,
            'quarantine' => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
                'original_name' => $attachment->original_name,
                'sha256' => $attachment->sha256,
                'reason' => 'Suspicious attachment heuristics matched: ' . implode(', ', Arr::wrap($signals)),
                'meta' => [
                    'signals' => $signals,
                ],
            ],
        ]);
    }
}
