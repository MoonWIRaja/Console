<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactoryContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\Validation\Factory as ValidationFactoryContract;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\MessageBag;
use Pterodactyl\Http\Controllers\Api\Internal\TicketDiscordBridgeController;
use Pterodactyl\Http\Middleware\EnforceSecurityPolicies;
use Pterodactyl\Http\Middleware\VerifyReCaptcha;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Services\Billing\BillingPaymentAttemptService;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Pterodactyl\Services\Billing\StripeClientFactory;
use Pterodactyl\Services\Billing\StripeCustomerService;
use Pterodactyl\Services\Billing\StripeInvoiceSyncService;
use Pterodactyl\Services\Billing\StripeSubscriptionSyncService;
use Pterodactyl\Services\Billing\StripeWebhookService;
use Pterodactyl\Services\DownDetector\DownDetectorDiscordInteractionService;
use Pterodactyl\Services\Security\Agents\SecurityAgentService;
use Pterodactyl\Services\Security\Agents\SecurityAgentSignatureService;
use Pterodactyl\Services\Security\AuthSecurityService;
use Pterodactyl\Services\Security\SecurityCenterSettingsService;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityRuntimePolicyService;
use Pterodactyl\Services\Tickets\TicketDiscordInteractionService;
use Pterodactyl\Services\Tickets\TicketDiscordService;
use Pterodactyl\Services\Tickets\TicketMessageService;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketSettingsService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\Security\SecurityAgent;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

$jsonMode = in_array('--json', $_SERVER['argv'] ?? [], true);

final class InMemoryCacheFactory implements CacheFactoryContract
{
    public function __construct(private CacheRepository $repository)
    {
    }

    public function store($name = null): CacheRepository
    {
        return $this->repository;
    }
}

final class MinimalResponseFactory implements ResponseFactoryContract
{
    public function make($content = '', $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    public function noContent($status = 204, array $headers = []): Response
    {
        return new Response('', $status, $headers);
    }

    public function view($view, $data = [], $status = 200, array $headers = []): Response
    {
        return new Response('', $status, $headers);
    }

    public function json($data = [], $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        return new JsonResponse($data, $status, $headers, $options);
    }

    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        $response = new JsonResponse($data, $status, $headers, $options);
        $response->setCallback((string) $callback);

        return $response;
    }

    public function stream($callback, $status = 200, array $headers = []): StreamedResponse
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment'): StreamedResponse
    {
        return new StreamedResponse($callback, 200, $headers);
    }

    public function download($file, $name = null, array $headers = [], $disposition = 'attachment'): BinaryFileResponse
    {
        return new BinaryFileResponse($file, 200, $headers);
    }

    public function file($file, array $headers = []): BinaryFileResponse
    {
        return new BinaryFileResponse($file, 200, $headers);
    }

    public function redirectTo($path, $status = 302, $headers = [], $secure = null): RedirectResponse
    {
        return new RedirectResponse((string) $path, $status, $headers);
    }

    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = []): RedirectResponse
    {
        return new RedirectResponse((string) $route, $status, $headers);
    }

    public function redirectToAction($action, $parameters = [], $status = 302, $headers = []): RedirectResponse
    {
        return new RedirectResponse(is_array($action) ? implode('@', $action) : (string) $action, $status, $headers);
    }

    public function redirectGuest($path, $status = 302, $headers = [], $secure = null): RedirectResponse
    {
        return new RedirectResponse((string) $path, $status, $headers);
    }

    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null): RedirectResponse
    {
        return new RedirectResponse((string) $default, $status, $headers);
    }
}

final class MinimalApplicationContainer extends Container
{
    public function abort($code, $message = '', array $headers = []): never
    {
        throw new HttpException((int) $code, (string) $message, null, $headers);
    }
}

final class DummyValidator implements ValidatorContract
{
    public function __construct(private array $data = [])
    {
    }

    public function validate(): array
    {
        return $this->data;
    }

    public function validated(): array
    {
        return $this->data;
    }

    public function fails(): bool
    {
        return false;
    }

    public function failed(): array
    {
        return [];
    }

    public function sometimes($attribute, $rules, callable $callback): static
    {
        return $this;
    }

    public function after($callback): static
    {
        return $this;
    }

    public function errors(): MessageBag
    {
        return new MessageBag();
    }

    public function getMessageBag(): MessageBag
    {
        return $this->errors();
    }
}

final class DummyValidationFactory implements ValidationFactoryContract
{
    public function make(array $data, array $rules, array $messages = [], array $attributes = []): ValidatorContract
    {
        return new DummyValidator($data);
    }

    public function extend($rule, $extension, $message = null): void
    {
    }

    public function extendImplicit($rule, $extension, $message = null): void
    {
    }

    public function replacer($rule, $replacer): void
    {
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function newRequest(string $method, string $uri, string $body = '', array $headers = [], string $ip = '127.0.0.1'): Request
{
    $request = Request::create($uri, $method, [], [], [], ['REMOTE_ADDR' => $ip], $body);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, (string) $value);
    }

    return $request;
}

function invokePrivate(object $object, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $arguments);
}

function bridgeHeaders(string $body, string $secret, ?int $timestamp = null, ?string $nonce = null, ?string $signature = null): array
{
    $timestamp ??= time();
    $nonce ??= 'bridge-selftest-' . bin2hex(random_bytes(4));
    $signature ??= hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);

    return [
        'X-Tickets-Timestamp' => (string) $timestamp,
        'X-Tickets-Nonce' => $nonce,
        'X-Tickets-Signature' => $signature,
    ];
}

$container = new MinimalApplicationContainer();
$config = new ConfigRepository([
    'security' => [
        'enabled' => true,
        'cache_store' => null,
    ],
]);
$cacheRepository = new CacheRepository(new ArrayStore());
$cacheFactory = new InMemoryCacheFactory($cacheRepository);

$container->instance('config', $config);
$container->instance(ConfigContract::class, $config);
$container->instance('cache', $cacheFactory);
$container->instance(ResponseFactoryContract::class, new MinimalResponseFactory());
$container->instance(ValidationFactoryContract::class, new DummyValidationFactory());

Container::setInstance($container);
Facade::setFacadeApplication($container);

$results = [];

$run = function (string $name, callable $callback) use (&$results, $cacheRepository): void {
    $cacheRepository->clear();
    $failure = null;

    try {
        $callback();
    } catch (Throwable $exception) {
        $failure = $exception;
    } finally {
        try {
            \Mockery::close();
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }
    }

    $results[] = [
        'name' => $name,
        'status' => $failure ? 'FAIL' : 'PASS',
        'detail' => $failure?->getMessage(),
    ];
};

function emptySecurityEvent(): SecurityEvent
{
    return new SecurityEvent();
}

$run('captcha_missing_token_is_blocked', function (): void {
    $dispatcher = \Mockery::mock(Dispatcher::class);
    $dispatcher->shouldNotReceive('dispatch');

    $auth = \Mockery::mock(AuthSecurityService::class);
    $auth->shouldReceive('getIdentifierFromRequest')->once()->andReturn('alice@example.com');
    $auth->shouldReceive('evaluate')->twice()->andReturn(
        ['locked' => false, 'retry_after' => 0],
        ['locked' => false, 'retry_after' => 15]
    );
    $auth->shouldReceive('registerFailure')->once()->andReturn(4);

    $middleware = new VerifyReCaptcha(
        $dispatcher,
        new ConfigRepository(['turnstile' => ['enabled' => true, 'secret_key' => 'test-secret']]),
        $auth
    );

    $request = newRequest('POST', '/auth/login', '', ['Accept' => 'application/json']);
    $request->request->set('email', 'alice@example.com');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    assertTrue($response instanceof JsonResponse, 'Expected JSON challenge response.');
    assertTrue($response->getStatusCode() === 429, 'Expected HTTP 429 for missing captcha token.');

    $payload = $response->getData(true);
    assertTrue(($payload['challenge_required'] ?? false) === true, 'Expected challenge_required=true.');
});

$run('runtime_ip_deny_policy_blocks_request', function (): void {
    $settings = \Mockery::mock(SecurityCenterSettingsService::class);
    $settings->shouldReceive('runtimeIpDenyMinutes')->andReturn(5);
    $settings->shouldReceive('runtimeFingerprintDenyMinutes')->andReturn(5);
    $settings->shouldReceive('runtimeRouteHoldMinutes')->andReturn(5);
    $settings->shouldReceive('trustedNetworks')->andReturn([]);
    $settings->shouldReceive('breakGlassTrustedNetworks')->andReturn([]);
    $settings->shouldReceive('emergencyToken')->andReturn(null);

    $runtime = new SecurityRuntimePolicyService($settings);
    $runtime->denyIp('203.0.113.50', 5, ['reason' => 'selftest']);

    $orchestrator = \Mockery::mock(SecurityOrchestratorService::class);
    $orchestrator->shouldReceive('record')->once()->andReturn(emptySecurityEvent());

    $middleware = new EnforceSecurityPolicies($runtime, $orchestrator);
    $request = newRequest('GET', '/admin/security', '', ['Accept' => 'application/json'], '203.0.113.50');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    assertTrue($response instanceof JsonResponse, 'Expected JSON response from runtime policy middleware.');
    assertTrue($response->getStatusCode() === 423, 'Expected HTTP 423 for denied IP.');
});

$run('security_agent_replay_nonce_is_blocked', function (): void {
    $agent = new SecurityAgent();
    $agent->id = 1;
    $agent->uuid = 'agent-selftest';
    $agent->node_id = 1;

    $agents = \Mockery::mock(SecurityAgentService::class);
    $agents->shouldReceive('resolve')->twice()->andReturn($agent);
    $agents->shouldReceive('activeSecrets')->once()->andReturn(['topsecret']);

    $settings = \Mockery::mock(SecurityCenterSettingsService::class);
    $settings->shouldReceive('config')->andReturn([
        'agent' => [
            'clock_skew_seconds' => 60,
            'nonce_ttl_seconds' => 60,
            'secret_rotation_grace_seconds' => 3600,
        ],
    ]);

    $orchestrator = \Mockery::mock(SecurityOrchestratorService::class);
    $orchestrator->shouldReceive('record')->once()->andReturn(emptySecurityEvent());

    $service = new SecurityAgentSignatureService($agents, $settings, $orchestrator);

    $body = '';
    $timestamp = time();
    $nonce = 'nonce-selftest';
    $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, 'topsecret');

    $request = newRequest('POST', '/api/internal/security/agents/heartbeat', $body, [
        'X-Security-Agent-Id' => 'agent-selftest',
        'X-Security-Timestamp' => (string) $timestamp,
        'X-Security-Nonce' => $nonce,
        'X-Security-Signature' => $signature,
    ], '198.51.100.10');
    $request->request->set('agent_id', 'agent-selftest');

    $resolved = $service->authenticate($request);
    assertTrue($resolved->uuid === 'agent-selftest', 'Expected first agent authentication to succeed.');

    try {
        $service->authenticate($request);
        throw new RuntimeException('Replay request was accepted by the security agent API.');
    } catch (AccessDeniedHttpException) {
    }
});

$run('stripe_webhook_missing_signature_is_rejected', function (): void {
    $stripe = \Mockery::mock(StripeClientFactory::class);
    $stripe->shouldReceive('webhookSecret')->once()->andReturn('whsec_selftest');

    $service = new StripeWebhookService(
        $stripe,
        \Mockery::mock(StripeCustomerService::class),
        \Mockery::mock(StripeInvoiceSyncService::class),
        \Mockery::mock(StripeSubscriptionSyncService::class),
        \Mockery::mock(BillingPaymentService::class),
        \Mockery::mock(BillingPaymentAttemptService::class),
    );

    try {
        $service->handle('{"id":"evt_test","type":"checkout.session.completed"}', null);
        throw new RuntimeException('Stripe webhook accepted a request without a signature header.');
    } catch (HttpException $exception) {
        assertTrue($exception->getStatusCode() === 422, 'Expected 422 for missing Stripe signature header.');
    }
});

$run('discord_bridge_invalid_signature_is_blocked', function (): void {
    $settings = \Mockery::mock(TicketSettingsService::class);
    $settings->shouldReceive('bridgeSharedSecret')->once()->andReturn('bridge-secret');
    $settings->shouldReceive('bridgeClockSkewSeconds')->once()->andReturn(60);

    $orchestrator = \Mockery::mock(SecurityOrchestratorService::class);
    $orchestrator->shouldReceive('record')->once()->andReturn(emptySecurityEvent());

    $controller = new TicketDiscordBridgeController(
        \Mockery::mock(TicketService::class),
        \Mockery::mock(TicketMessageService::class),
        $settings,
        \Mockery::mock(TicketDiscordService::class),
        \Mockery::mock(TicketDiscordInteractionService::class),
        \Mockery::mock(DownDetectorDiscordInteractionService::class),
        \Mockery::mock(SettingsRepositoryInterface::class),
        $orchestrator,
    );

    $body = '{"ok":true}';
    $headers = bridgeHeaders($body, 'bridge-secret', time(), 'bridge-invalid', 'not-valid');
    $request = newRequest('POST', '/api/internal/tickets/discord/events', $body, $headers, '198.51.100.20');

    try {
        invokePrivate($controller, 'abortIfInvalidSignature', [$request]);
        throw new RuntimeException('Discord bridge accepted an invalid signature.');
    } catch (HttpException $exception) {
        assertTrue($exception->getStatusCode() === 403, 'Expected HTTP 403 for invalid bridge signature.');
    }
});

$run('discord_bridge_replay_should_be_blocked', function (): void {
    $settings = \Mockery::mock(TicketSettingsService::class);
    $settings->shouldReceive('bridgeSharedSecret')->twice()->andReturn('bridge-secret');
    $settings->shouldReceive('bridgeClockSkewSeconds')->twice()->andReturn(60);
    $settings->shouldReceive('bridgeNonceTtlSeconds')->twice()->andReturn(300);

    $orchestrator = \Mockery::mock(SecurityOrchestratorService::class);
    $orchestrator->shouldReceive('record')->once()->andReturn(emptySecurityEvent());

    $controller = new TicketDiscordBridgeController(
        \Mockery::mock(TicketService::class),
        \Mockery::mock(TicketMessageService::class),
        $settings,
        \Mockery::mock(TicketDiscordService::class),
        \Mockery::mock(TicketDiscordInteractionService::class),
        \Mockery::mock(DownDetectorDiscordInteractionService::class),
        \Mockery::mock(SettingsRepositoryInterface::class),
        $orchestrator,
    );

    $body = '{"event_type":"HEARTBEAT"}';
    $request = newRequest(
        'POST',
        '/api/internal/tickets/discord/heartbeat',
        $body,
        bridgeHeaders($body, 'bridge-secret', time(), 'bridge-replay'),
        '198.51.100.30'
    );

    invokePrivate($controller, 'abortIfInvalidSignature', [$request]);

    try {
        invokePrivate($controller, 'abortIfInvalidSignature', [$request]);
        throw new RuntimeException('Replay request was accepted by the Discord bridge.');
    } catch (HttpException) {
    }
});

$passCount = count(array_filter($results, fn (array $result): bool => $result['status'] === 'PASS'));
$failCount = count($results) - $passCount;

if ($jsonMode) {
    echo json_encode([
        'summary' => [
            'passed' => $passCount,
            'failed' => $failCount,
            'total' => count($results),
            'exit_code' => $failCount > 0 ? 1 : 0,
        ],
        'results' => $results,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit($failCount > 0 ? 1 : 0);
}

echo "Security Self-Test Results\n";
echo "==========================\n";

foreach ($results as $result) {
    echo sprintf("[%s] %s\n", $result['status'], $result['name']);
    if ($result['detail']) {
        echo '       ' . $result['detail'] . "\n";
    }
}

echo "\nSummary: {$passCount} passed, {$failCount} failed.\n";

exit($failCount > 0 ? 1 : 0);
