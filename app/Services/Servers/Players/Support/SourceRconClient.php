<?php

namespace Pterodactyl\Services\Servers\Players\Support;

use RuntimeException;

/**
 * A minimal Source RCON protocol client (the protocol Valve's engine defined and
 * that ARK, Palworld, Project Zomboid, Rust, and many others reuse verbatim).
 *
 * This exists because Wings' own "send a console command" API
 * (POST /api/servers/{uuid}/commands, wrapped by DaemonCommandRepository::send())
 * is fire-and-forget - confirmed empirically against a real server, it always
 * replies 204 No Content with an empty body. There is no way to read a command's
 * output through it. Every "live" player provider that tried to parse that
 * response (Ark/Terraria/ProjectZomboid/FiveM/Hytale/MinecraftBedrock) was
 * therefore always working with an empty string, silently returning zero
 * players regardless of what was actually running. Real RCON - a plain TCP
 * socket the panel connects to directly - is the only way to actually get a
 * command's output back.
 *
 * Protocol layout per packet (all integers little-endian):
 *   int32 size | int32 id | int32 type | body (null-terminated) | empty string (null)
 *
 * Packet types used here: 3 = SERVERDATA_AUTH, 2 = SERVERDATA_EXECCOMMAND (also
 * reused by the server for SERVERDATA_AUTH_RESPONSE), 0 = SERVERDATA_RESPONSE_VALUE.
 */
class SourceRconClient
{
    private const TYPE_RESPONSE_VALUE = 0;
    private const TYPE_EXEC_COMMAND = 2;
    private const TYPE_AUTH = 3;

    /** @var resource */
    private $socket;

    private int $nextRequestId = 1;

    /**
     * @throws RuntimeException if the socket can't connect or authentication fails.
     */
    public function __construct(string $host, int $port, string $password, float $timeout = 4.0)
    {
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr,
            $timeout
        );

        if ($socket === false) {
            throw new RuntimeException(sprintf('Could not connect to RCON at %s:%d (%s)', $host, $port, $errstr));
        }

        stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1_000_000));
        $this->socket = $socket;

        $this->authenticate($password);
    }

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    /**
     * Sends a command and returns its full output.
     *
     * The spec's own multi-packet technique (matching an empty follow-up
     * command's echoed id) turned out not to be portable: tested live against a
     * real Palworld server, every SERVERDATA_RESPONSE_VALUE came back with id=0
     * regardless of what request id was sent, so id-matching silently discarded
     * every real response. Reading with a short idle timeout instead - keep
     * consuming packets until none arrive for a brief window - works regardless
     * of whether a given game's RCON implementation preserves request ids.
     *
     * @throws RuntimeException on a socket-level failure.
     */
    public function execute(string $command): string
    {
        $this->send(self::TYPE_EXEC_COMMAND, $command);

        $this->setReadTimeout(0.4);
        $output = '';
        for ($i = 0; $i < 20; $i++) {
            $packet = $this->readPacket();
            if ($packet === null) {
                break;
            }

            $output .= $packet['body'];
        }

        return $output;
    }

    private function setReadTimeout(float $seconds): void
    {
        stream_set_timeout($this->socket, (int) $seconds, (int) (fmod($seconds, 1) * 1_000_000));
    }

    private function authenticate(string $password): void
    {
        $requestId = $this->send(self::TYPE_AUTH, $password);

        // The auth response's own body arrives as an empty SERVERDATA_RESPONSE_VALUE
        // packet first (id 0 body ""), then the real SERVERDATA_AUTH_RESPONSE - skip
        // past that first one rather than misreading it as the auth result.
        $packet = $this->readPacket();
        if ($packet !== null && $packet['type'] === self::TYPE_RESPONSE_VALUE) {
            $packet = $this->readPacket();
        }

        if ($packet === null || $packet['id'] !== $requestId) {
            throw new RuntimeException('RCON authentication failed (wrong password or protocol mismatch).');
        }
    }

    private function send(int $type, string $body): int
    {
        $id = $this->nextRequestId++;
        $payload = pack('V', $id) . pack('V', $type) . $body . "\x00\x00";
        $packet = pack('V', strlen($payload)) . $payload;

        $written = fwrite($this->socket, $packet);
        if ($written === false || $written !== strlen($packet)) {
            throw new RuntimeException('Failed to write RCON packet to socket.');
        }

        return $id;
    }

    /**
     * @return array{id: int, type: int, body: string}|null
     */
    private function readPacket(): ?array
    {
        $sizeRaw = $this->readExactly(4);
        if ($sizeRaw === null) {
            return null;
        }

        $size = unpack('V', $sizeRaw)[1];
        // Guard against a garbage/oversized length field turning into a runaway read.
        if ($size < 10 || $size > 1_048_576) {
            return null;
        }

        $body = $this->readExactly($size);
        if ($body === null) {
            return null;
        }

        $id = unpack('V', substr($body, 0, 4))[1];
        $type = unpack('V', substr($body, 4, 4))[1];
        // Convert Windows-style negative ids (auth failure sends -1) correctly on
        // 64-bit PHP, where unpack('V') never produces a negative number itself.
        if ($id > 0x7FFFFFFF) {
            $id -= 0x100000000;
        }

        $text = rtrim(substr($body, 8), "\x00");

        return ['id' => $id, 'type' => $type, 'body' => $text];
    }

    private function readExactly(int $length): ?string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out'] || feof($this->socket)) {
                    return null;
                }
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }
}
