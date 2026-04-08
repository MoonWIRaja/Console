<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Pterodactyl\Services\Subdomains\ServerSubdomainService;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class ServerDeletionService
{
    private const DAEMON_DELETE_RETRY_ATTEMPTS = 5;

    private const DAEMON_DELETE_INSTALLING_RETRY_ATTEMPTS = 8;

    private const DAEMON_DELETE_RETRY_DELAY_US = 500000;

    private const DAEMON_DELETE_INSTALLING_RETRY_DELAY_US = 1000000;

    protected bool $force = false;

    /**
     * ServerDeletionService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private DatabaseManagementService $databaseManagementService,
        private ServerSubdomainService $serverSubdomainService,
    ) {
    }

    /**
     * Set if the server should be forcibly deleted from the panel (ignoring daemon errors) or not.
     */
    public function withForce(bool $bool = true): self
    {
        $this->force = $bool;

        return $this;
    }

    /**
     * Delete a server from the panel, clear any allocation notes, and remove any associated databases from hosts.
     *
     * @throws \Throwable
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function handle(Server $server): void
    {
        try {
            $this->deleteFromDaemon($server);
        } catch (DaemonConnectionException $exception) {
            // If Wings already reports that the server does not exist anymore we can safely
            // continue deleting it from the panel and refunding any parent resources.
            if (!$this->force && !$this->wasServerAlreadyDeleted($exception)) {
                throw $exception;
            }

            Log::warning($exception);
        }

        $this->connection->transaction(function () use ($server) {
            foreach ($server->subdomains()->with('domain')->get() as $subdomain) {
                try {
                    $this->serverSubdomainService->delete($subdomain);
                } catch (\Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // Best effort fallback: remove the panel record so the server can still be deleted
                    // even if the upstream DNS provider rejects or times out during cleanup.
                    $subdomain->delete();

                    Log::warning($exception);
                }
            }

            foreach ($server->databases as $database) {
                try {
                    $this->databaseManagementService->delete($database);
                } catch (\Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // Oh well, just try to delete the database entry we have from the database
                    // so that the server itself can be deleted. This will leave it dangling on
                    // the host instance, but we couldn't delete it anyways so not sure how we would
                    // handle this better anyways.
                    //
                    // @see https://github.com/pterodactyl/panel/issues/2085
                    $database->delete();

                    Log::warning($exception);
                }
            }

            // clear any allocation notes for the server
            $server->allocations()->update(['notes' => null]);


            $server->delete();
        });
    }

    /**
     * Delete the server from Wings, retrying a few times for transient 5xx/timeout failures.
     *
     * @throws DaemonConnectionException
     */
    private function deleteFromDaemon(Server $server): void
    {
        $maxAttempts = $server->isInstalled()
            ? self::DAEMON_DELETE_RETRY_ATTEMPTS
            : self::DAEMON_DELETE_INSTALLING_RETRY_ATTEMPTS;
        $baseDelay = $server->isInstalled()
            ? self::DAEMON_DELETE_RETRY_DELAY_US
            : self::DAEMON_DELETE_INSTALLING_RETRY_DELAY_US;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $this->daemonServerRepository->setServer($server)->delete();

                return;
            } catch (DaemonConnectionException $exception) {
                if ($this->wasServerAlreadyDeleted($exception)) {
                    return;
                }

                if (!$this->shouldRetryDaemonDelete($exception, $attempt, $maxAttempts)) {
                    if ($this->isServerMissingFromDaemon($server)) {
                        return;
                    }

                    throw $exception;
                }

                Log::warning('Retrying failed Wings server deletion request.', [
                    'server_id' => $server->id,
                    'server_uuid' => $server->uuid,
                    'server_status' => $server->status,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'status_code' => $exception->getStatusCode(),
                    'request_id' => $exception->getRequestId(),
                ]);

                usleep(min(5_000_000, $baseDelay * $attempt));
            }
        }
    }

    private function shouldRetryDaemonDelete(DaemonConnectionException $exception, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts
            && ($exception->getStatusCode() >= 500 || $exception->getStatusCode() === Response::HTTP_CONFLICT);
    }

    private function wasServerAlreadyDeleted(DaemonConnectionException $exception): bool
    {
        if ($exception->getStatusCode() === Response::HTTP_NOT_FOUND) {
            return true;
        }

        $response = method_exists($exception->getPrevious(), 'getResponse')
            ? $exception->getPrevious()->getResponse()
            : null;

        if ($response?->getStatusCode() === Response::HTTP_NOT_FOUND) {
            return true;
        }

        $body = trim((string) $response?->getBody());
        if ($body === '') {
            return false;
        }

        $decoded = json_decode($body, true);
        $message = Str::lower((string) ($decoded['error'] ?? $body));

        return Str::contains($message, 'requested resource does not exist on this instance');
    }

    private function isServerMissingFromDaemon(Server $server): bool
    {
        try {
            $this->daemonServerRepository->setServer($server)->getDetails();
        } catch (DaemonConnectionException $exception) {
            return $this->wasServerAlreadyDeleted($exception);
        }

        return false;
    }
}
