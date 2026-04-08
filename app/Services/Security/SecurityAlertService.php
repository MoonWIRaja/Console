<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SecurityAlertService
{
    public function send(string $type, string $subject, array $context = []): void
    {
        $email = trim((string) config('security.alerts.email'));
        if ($email === '') {
            return;
        }

        $cooldown = max(30, (int) config('security.alerts.cooldown_seconds', 300));
        $fingerprint = sha1($type . '|' . (string) Arr::get($context, 'ip', '') . '|' . (string) Arr::get($context, 'route', ''));
        $cacheKey = "security:alerts:mail:{$fingerprint}";
        $cacheStore = config('security.cache_store');

        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        if (!$cache->add($cacheKey, true, now()->addSeconds($cooldown))) {
            return;
        }

        $subjectLine = '[Panel Security] ' . $subject;
        $body = $this->formatBody($type, $context);

        try {
            Mail::raw($body, function ($message) use ($email, $subjectLine) {
                $message->to($email)->subject($subjectLine);
            });
        } catch (\Throwable $exception) {
            Log::warning('Failed sending security alert email.', [
                'type' => $type,
                'email' => $email,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function formatBody(string $type, array $context): string
    {
        $lines = [
            'Security event detected.',
            '',
            'Type: ' . $type,
            'Time: ' . now()->toIso8601String(),
        ];

        foreach ($context as $key => $value) {
            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value));
        }

        return implode("\n", $lines);
    }
}
