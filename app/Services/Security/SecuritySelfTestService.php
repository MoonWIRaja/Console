<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Security\SecurityEvent;

class SecuritySelfTestService
{
    public function __construct(private SecurityOrchestratorService $orchestrator)
    {
    }

    public function run(?User $actor = null, ?string $sourceIp = null): array
    {
        $process = new Process([
            $this->resolvePhpBinary(),
            base_path('scripts/security-selftest.php'),
            '--json',
        ], base_path());

        $process->setTimeout(120);
        $process->run();

        $payload = $this->decodePayload($process);
        $results = array_values(array_filter(
            Arr::wrap($payload['results'] ?? []),
            static fn (mixed $entry) => is_array($entry)
        ));
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [
            'passed' => 0,
            'failed' => 0,
            'total' => count($results),
            'exit_code' => $process->getExitCode(),
        ];

        $failed = (int) ($summary['failed'] ?? 0);
        $summaryText = $failed > 0
            ? sprintf('Security self-test completed with %d failing check(s).', $failed)
            : 'Security self-test completed with all checks passing.';

        $event = $this->orchestrator->record('security_self_test_run', [
            'class' => 'security_validation',
            'surface' => 'self_test',
            'severity' => $failed > 0 ? 'medium' : 'low',
            'confidence' => 100,
            'source_ip' => $sourceIp,
            'actor' => $actor,
            'summary' => $summaryText,
            'evidence' => [
                'checks' => $results,
                'summary' => $summary + [
                    'ran_at' => now()->toIso8601String(),
                ],
                'stdout' => $payload['stdout'] ?? null,
                'stderr' => $payload['stderr'] ?? null,
                'command' => $process->getCommandLine(),
            ],
            'blocked' => false,
            'verdict' => $failed > 0 ? SecurityVocabulary::VERDICT_FAILED_TO_BLOCK : SecurityVocabulary::VERDICT_OBSERVED,
            'mitigation_stage' => SecurityVocabulary::STAGE_OBSERVE,
            'execute_actions' => false,
            'correlation_id' => (string) Str::uuid(),
        ]);

        return [
            'summary' => $summary + [
                'ran_at' => now()->toIso8601String(),
            ],
            'results' => $results,
            'stdout' => $payload['stdout'] ?? null,
            'stderr' => $payload['stderr'] ?? null,
            'event' => $event,
        ];
    }

    public function latest(): ?array
    {
        $event = SecurityEvent::query()
            ->with(['rule', 'incident', 'actor'])
            ->whereHas('rule', fn ($query) => $query->where('key', 'security_self_test_run'))
            ->latest()
            ->first();

        if (!$event) {
            return null;
        }

        $summary = is_array($event->evidence['summary'] ?? null) ? $event->evidence['summary'] : [];
        $checks = array_values(array_filter(
            Arr::wrap($event->evidence['checks'] ?? []),
            static fn (mixed $entry) => is_array($entry)
        ));

        return [
            'event' => $event,
            'summary' => $summary,
            'checks' => $checks,
            'stdout' => $event->evidence['stdout'] ?? null,
            'stderr' => $event->evidence['stderr'] ?? null,
        ];
    }

    private function decodePayload(Process $process): array
    {
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        $decoded = $this->extractJsonPayload($stdout)
            ?? $this->extractJsonPayload($stdout . "\n" . $stderr);

        if (is_array($decoded)) {
            return $decoded + [
                'stdout' => $stdout,
                'stderr' => $stderr,
            ];
        }

        $exitCode = $process->getExitCode();
        $stderrText = trim($stderr);
        $stdoutText = trim($stdout);
        $detail = $stderrText !== ''
            ? $stderrText
            : ($stdoutText !== '' ? Str::limit(preg_replace('/\s+/', ' ', $stdoutText) ?? $stdoutText, 280) : 'Security self-test did not return valid JSON output.');

        return [
            'summary' => [
                'passed' => 0,
                'failed' => 1,
                'total' => 1,
                'exit_code' => $exitCode,
            ],
            'results' => [[
                'name' => 'self_test_runner',
                'status' => 'FAIL',
                'detail' => sprintf(
                    '%s (exit code: %s; command: %s)',
                    $detail,
                    $exitCode ?? 'unknown',
                    $process->getCommandLine()
                ),
            ]],
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function resolvePhpBinary(): string
    {
        $finder = new PhpExecutableFinder();
        $binary = $finder->find(false);

        if (is_string($binary) && $binary !== '') {
            return $binary;
        }

        if ($this->isCliPhpBinary(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $fallback = rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';

        return is_file($fallback) ? $fallback : PHP_BINARY;
    }

    private function isCliPhpBinary(string $binary): bool
    {
        $name = strtolower(basename($binary));

        return str_contains($name, 'php') && !str_contains($name, 'fpm') && !str_contains($name, 'cgi');
    }

    private function extractJsonPayload(string $output): ?array
    {
        $trimmed = trim($output);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
