<?php

namespace Pterodactyl\Services\Minecraft;

use Throwable;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/**
 * Aggregates a unique roster of "original" (premium) Minecraft players across every
 * Minecraft server hosted on the panel.
 *
 * Rules (per product requirement):
 *  - Only servers running with online-mode=true contribute players. Offline/cracked
 *    servers are skipped because their accounts are not real Mojang accounts.
 *  - Only premium (version-4) UUIDs are kept. Offline UUIDs are version-3 (name hash),
 *    so this is a second guard that guarantees mc-heads.net renders a real skin.
 *  - Players are de-duplicated by UUID first, then by case-insensitive name, so a
 *    player present on several servers only appears once.
 */
class MinecraftRosterService
{
    public const CACHE_KEY = 'minecraft.roster.premium.v1';

    public const CACHE_TTL = 300; // 5 minutes — keeps the roster fresh for new players/servers

    public const ONLINE_CACHE_KEY = 'minecraft.roster.online.v1';

    public const ONLINE_CACHE_TTL = 180; // seconds — comfortably survives between background scans

    /**
     * Return the cached roster, building it if necessary.
     *
     * @return array<int, array{name: string, uuid: string}>
     */
    public function get(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());
    }

    /**
     * Lower-cased names of players currently online on any Minecraft server. Cached
     * briefly so the live-console "online glow" stays close to real-time without
     * hammering RCON on every page load.
     *
     * @return array<int, string>
     */
    public function onlineNames(): array
    {
        return Cache::get(self::ONLINE_CACHE_KEY, []);
    }

    /**
     * Recompute the "currently online" set and store it in the cache. Run from the
     * scheduled command (minecraft:scan-online) so request paths only read the cache.
     *
     * @return array<int, string>
     */
    public function refreshOnline(): array
    {
        $names = $this->buildOnline();
        Cache::put(self::ONLINE_CACHE_KEY, $names, self::ONLINE_CACHE_TTL);

        return $names;
    }

    /**
     * Build the roster by reading every Minecraft server's usercache.json through Wings.
     *
     * @return array<int, array{name: string, uuid: string}>
     */
    public function build(): array
    {
        $byUuid = [];

        $servers = Server::query()
            ->whereHas('egg.nest', fn ($q) => $q->where('name', 'Minecraft'))
            ->get();

        foreach ($servers as $server) {
            try {
                $repo = app(DaemonFileRepository::class)->setServer($server);

                // Gate on online-mode=true.
                $props = $repo->getContent('server.properties', 256 * 1024);
                if (!preg_match('/^\s*online-mode\s*=\s*true\s*$/mi', (string) $props)) {
                    continue;
                }

                $raw = $repo->getContent('usercache.json', 8 * 1024 * 1024);
            } catch (Throwable $e) {
                // Server offline file missing, proxy without a usercache, unreachable node, etc.
                continue;
            }

            $entries = json_decode((string) $raw, true);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                $name = is_array($entry) ? ($entry['name'] ?? null) : null;
                $uuid = is_array($entry) ? ($entry['uuid'] ?? null) : null;
                if (!is_string($name) || !is_string($uuid)) {
                    continue;
                }

                $name = trim($name);
                // Valid Minecraft usernames only (1-16 of [A-Za-z0-9_]).
                if (!preg_match('/^[A-Za-z0-9_]{1,16}$/', $name)) {
                    continue;
                }

                // Premium accounts only: a version-4 UUID has '4' as its 13th hex digit.
                $hex = str_replace('-', '', strtolower($uuid));
                if (strlen($hex) !== 32 || $hex[12] !== '4') {
                    continue;
                }

                // De-dupe by UUID (a player joined to several servers counts once).
                $byUuid[$hex] = $name;
            }
        }

        // Second de-dupe pass by case-insensitive name.
        $seenNames = [];
        $roster = [];
        foreach ($byUuid as $hex => $name) {
            $key = strtolower($name);
            if (isset($seenNames[$key])) {
                continue;
            }
            $seenNames[$key] = true;
            $roster[] = ['name' => $name, 'uuid' => $hex];
        }

        return $roster;
    }

    /**
     * Scan every Minecraft server for its currently-online players via the live
     * player provider (RCON). Best-effort: servers that are offline or unreachable
     * are skipped silently.
     *
     * @return array<int, string>
     */
    private function buildOnline(): array
    {
        $online = [];
        $directory = app(\Pterodactyl\Services\Servers\Players\PlayerDirectoryService::class);

        $servers = Server::query()
            ->whereHas('egg.nest', fn ($q) => $q->where('name', 'Minecraft'))
            ->get();

        foreach ($servers as $server) {
            try {
                $result = $directory->list($server, 'online');
            } catch (Throwable $e) {
                continue;
            }

            foreach (($result['items'] ?? []) as $player) {
                $name = is_array($player) ? ($player['name'] ?? null) : null;
                if (is_string($name) && $name !== '') {
                    $online[strtolower($name)] = true;
                }
            }
        }

        return array_keys($online);
    }
}
