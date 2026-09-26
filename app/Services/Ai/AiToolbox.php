<?php

namespace Pterodactyl\Services\Ai;

use Throwable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Ai\AiUserMemory;
use Pterodactyl\Services\Billing\BillingCatalogService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

/**
 * Every server tool goes through the Wings API for one server the user can
 * access. Wings confines file operations to that server's container volume,
 * so the assistant never gets a shell, root, or access to the host.
 */
class AiToolbox
{
    public const MODE_ASK = 'ask';
    public const MODE_AGENT = 'agent';

    private const READ_LIMIT = 100 * 1024;
    private const MAX_MEMORIES = 40;
    private const HIDDEN_ROOTS = ['.recycle_bin', '.steam', '.backups'];

    /** Tools that change something and therefore need the user's approval. */
    public const WRITE_TOOLS = [
        'write_file' => Permission::ACTION_FILE_UPDATE,
        'create_directory' => Permission::ACTION_FILE_CREATE,
        'delete_files' => Permission::ACTION_FILE_DELETE,
        'rename_file' => Permission::ACTION_FILE_UPDATE,
        'send_console_command' => Permission::ACTION_CONTROL_CONSOLE,
        'power_action' => null,
    ];

    private const READ_TOOLS = [
        'get_server_status' => null,
        'list_files' => Permission::ACTION_FILE_READ,
        'read_file' => Permission::ACTION_FILE_READ_CONTENT,
    ];

    public function __construct(
        private DaemonFileRepository $files,
        private DaemonPowerRepository $power,
        private DaemonServerRepository $servers,
        private DaemonCommandRepository $commands,
    ) {
    }

    /**
     * Servers the user may use the assistant on: owned servers, or subuser access with ai.read.
     *
     * @return \Illuminate\Support\Collection<int, Server>
     */
    public function aiServers(User $user)
    {
        return $user->accessibleServers()->with(['egg', 'node'])->get()
            ->filter(fn (Server $server) => $user->can(Permission::ACTION_AI_READ, $server))
            ->values();
    }

    public static function isWriteTool(string $name): bool
    {
        return array_key_exists($name, self::WRITE_TOOLS);
    }

    /**
     * Tool definitions in OpenAI function-calling format.
     */
    /**
     * $private is true in the panel and in Discord DMs; billing details are never offered in public channels.
     */
    public function definitions(string $channel, string $mode, bool $private = true): array
    {
        $serverArg = [
            'type' => 'string',
            'description' => 'Server identifier (short id like "bb1a9231" or the exact server name). Optional when the chat is already attached to a server.',
        ];

        $tools = [
            ...$this->publicDefinitions(),
            $this->tool('remember_about_user', 'Save a short, durable fact about the user (preferences, nickname, what they play, their setup). Do not store secrets or passwords.', [
                'fact' => ['type' => 'string', 'description' => 'One concise fact, max 300 characters.'],
            ], ['fact']),
            $this->tool('forget_about_user', 'Delete a remembered fact about the user by its id.', [
                'memory_id' => ['type' => 'integer'],
            ], ['memory_id']),
        ];

        if ($private) {
            $tools[] = $this->billingDefinition();
        }

        if ($channel === 'discord') {
            return $tools;
        }

        $tools[] = $this->tool('list_my_servers', 'List the game servers this user can access, with their id, name, game and status.', [], []);
        $tools[] = $this->tool('get_server_status', 'Get live state (running/offline), CPU, memory and disk usage plus plan limits for a server.', [
            'server' => $serverArg,
        ], []);
        $tools[] = $this->tool('list_files', 'List files and folders in a directory of the server.', [
            'server' => $serverArg,
            'directory' => ['type' => 'string', 'description' => 'Directory path, e.g. "/" or "/config".'],
        ], ['directory']);
        $tools[] = $this->tool('read_file', 'Read a text file from the server (logs, configs). Use tail_lines for big logs such as logs/latest.log.', [
            'server' => $serverArg,
            'path' => ['type' => 'string'],
            'tail_lines' => ['type' => 'integer', 'description' => 'Only return the last N lines.'],
        ], ['path']);

        if ($mode !== self::MODE_AGENT) {
            return $tools;
        }

        $tools[] = $this->tool('write_file', 'Create or overwrite a text file with the full new content. The user must approve before it is written.', [
            'server' => $serverArg,
            'path' => ['type' => 'string'],
            'content' => ['type' => 'string', 'description' => 'The complete new file content.'],
            'reason' => ['type' => 'string', 'description' => 'Short explanation shown to the user.'],
        ], ['path', 'content', 'reason']);
        $tools[] = $this->tool('create_directory', 'Create a folder. Needs user approval.', [
            'server' => $serverArg,
            'path' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ], ['path', 'reason']);
        $tools[] = $this->tool('delete_files', 'Delete files or folders. Needs user approval.', [
            'server' => $serverArg,
            'paths' => ['type' => 'array', 'items' => ['type' => 'string']],
            'reason' => ['type' => 'string'],
        ], ['paths', 'reason']);
        $tools[] = $this->tool('rename_file', 'Rename or move a file. Needs user approval.', [
            'server' => $serverArg,
            'from' => ['type' => 'string'],
            'to' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ], ['from', 'to', 'reason']);
        $tools[] = $this->tool('send_console_command', 'Send a command to the server console (e.g. "say hi", "whitelist add Steve"). Needs user approval.', [
            'server' => $serverArg,
            'command' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ], ['command', 'reason']);
        $tools[] = $this->tool('power_action', 'Start, stop, restart or kill the server. Needs user approval.', [
            'server' => $serverArg,
            'action' => ['type' => 'string', 'enum' => ['start', 'stop', 'restart', 'kill']],
            'reason' => ['type' => 'string'],
        ], ['action', 'reason']);

        return $tools;
    }

    /**
     * Tools anyone may use, including Discord users who have not linked a panel account.
     */
    public function publicDefinitions(): array
    {
        return [
            $this->tool('get_plans_and_pricing', 'Get the nodes (locations) customers can buy servers on, their monthly prices per vCore / GB RAM / 10GB disk, remaining stock and the games available. Pass cpu, ram_gb and disk_gb to calculate the monthly price of a specific plan.', [
                'node' => ['type' => 'string', 'description' => 'Optional node name to focus on.'],
                'cpu' => ['type' => 'integer', 'description' => 'vCores for a price quote.'],
                'ram_gb' => ['type' => 'integer', 'description' => 'RAM in GB for a price quote.'],
                'disk_gb' => ['type' => 'integer', 'description' => 'Disk in GB for a price quote.'],
            ], []),
        ];
    }

    public function billingDefinition(): array
    {
        return $this->tool('get_my_billing', "Get the user's own server subscriptions: status, next renewal/due date, last payment, monthly price, auto-renew and unpaid invoices. Includes servers shared with them with billing permission.", [
            'server' => ['type' => 'string', 'description' => 'Optional server name or short id to focus on.'],
        ], []);
    }

    public function myBilling(User $user, array $args): string
    {
        $subuserServerIds = \Pterodactyl\Models\Subuser::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($subuser) => in_array(Permission::ACTION_BILLING_READ, $subuser->permissions ?? [], true))
            ->pluck('server_id')
            ->all();

        $subscriptions = \Pterodactyl\Models\BillingSubscription::query()
            ->with(['server', 'lastPaidInvoice'])
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('server_id', $subuserServerIds))
            ->whereNotNull('server_id')
            ->latest()
            ->get();

        $wanted = mb_strtolower(trim((string) ($args['server'] ?? '')));
        if ($wanted !== '') {
            $subscriptions = $subscriptions->filter(fn ($s) => str_contains(mb_strtolower((string) $s->server_name), $wanted) || ($s->server?->uuidShort === $wanted));
        }

        if ($subscriptions->isEmpty()) {
            return 'No billing subscriptions found for this user' . ($wanted !== '' ? ' matching "' . $wanted . '"' : '') . '.';
        }

        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))->toDayDateTimeString() : null;
        $manual = (bool) config('billing.gateway.manual_mode', false) || config('billing.gateway.default') === 'manual';

        $rows = $subscriptions->map(function ($s) use ($user, $date, $manual) {
            $unpaid = \Pterodactyl\Models\BillingInvoice::query()
                ->where('subscription_id', $s->id)
                ->whereIn('status', [\Pterodactyl\Models\BillingInvoice::STATUS_OPEN, \Pterodactyl\Models\BillingInvoice::STATUS_FAILED, \Pterodactyl\Models\BillingInvoice::STATUS_PROCESSING])
                ->get(['invoice_number', 'type', 'grand_total', 'due_at', 'status']);

            return [
                'server' => $s->server_name,
                'server_id' => $s->server?->uuidShort,
                'you_are' => $s->user_id === $user->id ? 'owner' : 'subuser with billing access',
                'status' => $s->status,
                'plan' => sprintf('%d vCore, %d GB RAM, %d GB disk', $s->cpu_cores, $s->memory_gb, $s->disk_gb),
                'price_per_period' => (float) $s->recurring_total,
                'period_months' => (int) $s->renewal_period_months,
                'next_renewal_due' => $date($s->renews_at),
                'suspended_if_unpaid_after' => $date($s->grace_suspend_at),
                'deleted_if_unpaid_after' => $date($s->grace_delete_at),
                'auto_renew' => $manual ? false : (bool) $s->auto_renew,
                'can_renew_now' => $s->isRenewWindowOpen(),
                'last_payment' => $s->lastPaidInvoice ? [
                    'invoice' => $s->lastPaidInvoice->invoice_number,
                    'amount' => (float) $s->lastPaidInvoice->grand_total,
                    'paid_at' => $date($s->lastPaidInvoice->paid_at),
                ] : null,
                'unpaid_invoices' => $unpaid->map(fn ($i) => [
                    'invoice' => $i->invoice_number,
                    'type' => $i->type,
                    'amount' => (float) $i->grand_total,
                    'due' => $date($i->due_at),
                    'status' => $i->status,
                ])->values(),
            ];
        })->values();

        return json_encode([
            'currency' => (string) config('billing.currency', 'MYR'),
            'timezone' => config('app.timezone'),
            'billing_page' => rtrim((string) config('app.url'), '/') . '/billing',
            'subscriptions' => $rows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function isPublicTool(string $name): bool
    {
        return $name === 'get_plans_and_pricing';
    }

    public function plansAndPricing(array $args): string
    {
        $catalog = app(BillingCatalogService::class)->getClientCatalog();
        $currency = (string) config('billing.currency', 'MYR');
        $wanted = mb_strtolower(trim((string) ($args['node'] ?? '')));
        $cpu = max(0, (int) ($args['cpu'] ?? 0));
        $ram = max(0, (int) ($args['ram_gb'] ?? 0));
        $disk = max(0, (int) ($args['disk_gb'] ?? 0));

        $nodes = [];
        foreach ($catalog as $entry) {
            if ($wanted !== '' && !str_contains(mb_strtolower((string) $entry['display_name']), $wanted)) {
                continue;
            }

            $pricing = $entry['pricing'];
            $node = [
                'node' => $entry['display_name'],
                'description' => $entry['description'],
                'available_to_buy' => (bool) ($entry['availability']['is_available'] ?? false),
                'price_per_month' => [
                    'per_vcore' => $pricing['per_vcore'],
                    'per_gb_ram' => $pricing['per_gb_ram'],
                    'per_10gb_disk' => $pricing['per_10gb_disk'],
                ],
                'max_you_can_buy_now' => [
                    'cpu_vcores' => $entry['limits']['max_cpu'],
                    'ram_gb' => $entry['limits']['max_memory_gb'],
                    'disk_gb' => $entry['limits']['max_disk_gb'],
                ],
                'included' => [
                    'ports' => $entry['defaults']['allocation_limit'],
                    'databases' => $entry['defaults']['database_limit'],
                    'backups' => $entry['defaults']['backup_limit'],
                ],
                'games' => collect($entry['games'])->groupBy('nest_name')->map(fn ($games) => $games->pluck('display_name')->values())->all(),
            ];

            if ($cpu || $ram || $disk) {
                $diskUnits = (int) ceil($disk / 10);
                $cpuTotal = round($cpu * $pricing['per_vcore'], 2);
                $ramTotal = round($ram * $pricing['per_gb_ram'], 2);
                $diskTotal = round($diskUnits * $pricing['per_10gb_disk'], 2);
                $node['quote'] = [
                    'requested' => ['cpu_vcores' => $cpu, 'ram_gb' => $ram, 'disk_gb' => $diskUnits * 10],
                    'breakdown' => ['cpu' => $cpuTotal, 'ram' => $ramTotal, 'disk' => $diskTotal],
                    'total_per_month' => round($cpuTotal + $ramTotal + $diskTotal, 2),
                    'fits_current_stock' => $cpu <= $entry['limits']['max_cpu'] && $ram <= $entry['limits']['max_memory_gb'] && $disk <= $entry['limits']['max_disk_gb'],
                ];
            }

            $nodes[] = $node;
        }

        return json_encode([
            'currency' => $currency,
            'billing' => 'Monthly, custom resources (you pick vCores, RAM and disk; disk is sold in 10GB steps). Prices are before any tax or coupon.',
            'order_at' => rtrim((string) config('app.url'), '/') . '/billing',
            'nodes' => $nodes ?: 'No node matches that name.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function tool(string $name, string $description, array $properties, array $required): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => $required,
                ],
            ],
        ];
    }

    /**
     * Resolves the server a tool call targets, limited to servers the user can access.
     */
    public function resolveServer(User $user, ?string $identifier, ?Server $default): Server
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            if ($default) {
                return $default;
            }

            throw new AiToolException('No server selected. Call list_my_servers and ask the user which server they mean.');
        }

        $candidates = $this->aiServers($user);

        $match = $candidates->first(fn (Server $s) => $s->uuidShort === $identifier || $s->uuid === $identifier);
        if (!$match) {
            $byName = $candidates->filter(fn (Server $s) => mb_strtolower($s->name) === mb_strtolower($identifier));
            if ($byName->count() > 1) {
                throw new AiToolException('More than one server is named "' . $identifier . '". Use the short id instead.');
            }
            $match = $byName->first();
        }

        if (!$match) {
            throw new AiToolException('Server "' . $identifier . '" was not found among the servers this user can use the assistant on. Subusers need the "AI: read" permission from the server owner.');
        }

        return $match;
    }

    public function assertAllowed(User $user, Server $server, string $tool, array $args): void
    {
        if ($server->isSuspended()) {
            throw new AiToolException('This server is suspended.');
        }

        if (!$user->can(Permission::ACTION_AI_READ, $server)) {
            throw new AiToolException('The user does not have the "AI: read" permission on this server. The server owner can grant it on the Users page.');
        }

        if (self::isWriteTool($tool) && !$user->can(Permission::ACTION_AI_AGENT, $server)) {
            throw new AiToolException('The user does not have the "AI: agent" permission on this server, so changes cannot be proposed. Explain the steps instead; the owner can grant it on the Users page.');
        }

        $permission = self::WRITE_TOOLS[$tool] ?? self::READ_TOOLS[$tool] ?? null;

        if ($tool === 'power_action') {
            $permission = match ($args['action'] ?? '') {
                'start' => Permission::ACTION_CONTROL_START,
                'stop', 'kill' => Permission::ACTION_CONTROL_STOP,
                'restart' => Permission::ACTION_CONTROL_RESTART,
                default => throw new AiToolException('Unknown power action.'),
            };
        }

        if ($permission && !$user->can($permission, $server)) {
            throw new AiToolException('The user does not have the "' . $permission . '" permission on this server.');
        }

        foreach (['path', 'directory', 'from', 'to'] as $key) {
            if (isset($args[$key])) {
                $this->assertPathAllowed((string) $args[$key]);
            }
        }
        foreach ((array) ($args['paths'] ?? []) as $path) {
            $this->assertPathAllowed((string) $path);
        }
    }

    private function assertPathAllowed(string $path): void
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), fn ($s) => $s !== '' && $s !== '.'));

        if (in_array('..', $segments, true)) {
            throw new AiToolException('Paths may not contain "..".');
        }

        $backupDir = trim((string) config('backups.local_container_directory', '/.backups'), '/');
        $hidden = array_filter([...self::HIDDEN_ROOTS, $backupDir]);

        if (isset($segments[0]) && in_array($segments[0], $hidden, true)) {
            throw new AiToolException('That folder is managed by the panel and cannot be accessed.');
        }
    }

    /**
     * Executes a read-only or memory tool immediately.
     */
    public function runImmediate(User $user, ?Server $default, string $name, array $args, string $channel): string
    {
        return match ($name) {
            'get_plans_and_pricing' => $this->plansAndPricing($args),
            'get_my_billing' => $this->myBilling($user, $args),
            'remember_about_user' => $this->remember($user, (string) ($args['fact'] ?? ''), $channel),
            'forget_about_user' => $this->forget($user, (int) ($args['memory_id'] ?? 0)),
            'list_my_servers' => $this->listServers($user),
            'get_server_status', 'list_files', 'read_file' => $this->runServerRead($user, $default, $name, $args),
            default => throw new AiToolException('Unknown tool "' . $name . '".'),
        };
    }

    private function runServerRead(User $user, ?Server $default, string $name, array $args): string
    {
        $server = $this->resolveServer($user, $args['server'] ?? null, $default);
        $this->assertAllowed($user, $server, $name, $args);

        return match ($name) {
            'get_server_status' => $this->status($server),
            'list_files' => $this->listFiles($server, (string) ($args['directory'] ?? '/')),
            'read_file' => $this->readFile($server, (string) $args['path'], isset($args['tail_lines']) ? (int) $args['tail_lines'] : null),
        };
    }

    /**
     * Executes an approved write action.
     */
    public function runWrite(User $user, Server $server, string $name, array $args): string
    {
        $this->assertAllowed($user, $server, $name, $args);

        $result = match ($name) {
            'write_file' => $this->writeFile($server, (string) $args['path'], (string) ($args['content'] ?? '')),
            'create_directory' => $this->createDirectory($server, (string) $args['path']),
            'delete_files' => $this->deleteFiles($server, (array) ($args['paths'] ?? [])),
            'rename_file' => $this->renameFile($server, (string) $args['from'], (string) $args['to']),
            'send_console_command' => $this->sendCommand($server, (string) $args['command']),
            'power_action' => $this->powerAction($server, (string) $args['action']),
            default => throw new AiToolException('Unknown tool "' . $name . '".'),
        };

        $logArgs = $args;
        unset($logArgs['content'], $logArgs['server']);
        Activity::event('server:ai.' . $name)->subject($server)->property($logArgs)->log();

        return $result;
    }

    private function remember(User $user, string $fact, string $channel): string
    {
        $fact = trim(mb_substr($fact, 0, 300));
        if ($fact === '') {
            throw new AiToolException('Fact is empty.');
        }

        if (AiUserMemory::query()->where('user_id', $user->id)->count() >= self::MAX_MEMORIES) {
            AiUserMemory::query()->where('user_id', $user->id)->orderBy('id')->first()?->delete();
        }

        $memory = AiUserMemory::query()->create(['user_id' => $user->id, 'fact' => $fact, 'source' => $channel]);

        return 'Saved memory #' . $memory->id . '.';
    }

    private function forget(User $user, int $id): string
    {
        $deleted = AiUserMemory::query()->where('user_id', $user->id)->where('id', $id)->delete();

        return $deleted ? 'Forgot memory #' . $id . '.' : 'No memory #' . $id . ' found.';
    }

    private function listServers(User $user): string
    {
        $servers = $this->aiServers($user);

        if ($servers->isEmpty()) {
            return 'The user has no servers the assistant can access. Subusers need the "AI: read" permission from the server owner.';
        }

        return $servers->map(fn (Server $s) => sprintf(
            '- id=%s name="%s" game=%s node=%s status=%s owner=%s',
            $s->uuidShort,
            $s->name,
            $s->egg?->name ?? 'unknown',
            $s->node?->name ?? 'unknown',
            $s->status ?? 'installed',
            $s->owner_id === $user->id ? 'yes' : 'no (subuser)',
        ))->implode("\n");
    }

    private function status(Server $server): string
    {
        try {
            $details = $this->servers->setServer($server)->getDetails();
        } catch (Throwable $exception) {
            throw new AiToolException('Could not reach the node for this server: ' . $exception->getMessage());
        }

        $util = $details['utilization'] ?? [];

        return json_encode([
            'id' => $server->uuidShort,
            'name' => $server->name,
            'game' => $server->egg?->name,
            'state' => $details['state'] ?? 'unknown',
            'cpu_percent' => round((float) ($util['cpu_absolute'] ?? 0), 1),
            'memory_mb' => round(((int) ($util['memory_bytes'] ?? 0)) / 1048576),
            'disk_mb' => round(((int) ($util['disk_bytes'] ?? 0)) / 1048576),
            'uptime_seconds' => (int) (($util['uptime'] ?? 0) / 1000),
            'limits' => [
                'cpu_percent' => $server->cpu,
                'memory_mb' => $server->memory,
                'disk_mb' => $server->disk,
            ],
            'note' => 'Plan limits are paid allocations; never suggest changing them yourself.',
        ], JSON_UNESCAPED_SLASHES);
    }

    private function listFiles(Server $server, string $directory): string
    {
        try {
            $entries = $this->files->setServer($server)->getDirectory($directory);
        } catch (Throwable $exception) {
            throw new AiToolException('Could not list "' . $directory . '": ' . $exception->getMessage());
        }

        $isRoot = trim($directory, '/') === '';
        $lines = [];
        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($isRoot && in_array($name, self::HIDDEN_ROOTS, true)) {
                continue;
            }
            $lines[] = sprintf('%s %s (%s bytes, modified %s)', ($entry['file'] ?? true) ? 'file' : 'dir ', $name, $entry['size'] ?? 0, $entry['modified'] ?? '?');
            if (count($lines) >= 300) {
                $lines[] = '... (truncated)';
                break;
            }
        }

        return $lines === [] ? 'Directory is empty.' : implode("\n", $lines);
    }

    private function readFile(Server $server, string $path, ?int $tail): string
    {
        try {
            $content = $this->files->setServer($server)->getContent($path, $tail ? null : self::READ_LIMIT * 4);
        } catch (Throwable $exception) {
            throw new AiToolException('Could not read "' . $path . '": ' . $exception->getMessage());
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            return 'This file is binary and cannot be shown.';
        }

        if ($tail) {
            $lines = preg_split('/\r?\n/', rtrim($content));
            $content = implode("\n", array_slice($lines, -max(1, min($tail, 1000))));
        }

        if (strlen($content) > self::READ_LIMIT) {
            return substr($content, -self::READ_LIMIT) . "\n\n[truncated: only the last 100KB is shown]";
        }

        return $content === '' ? '(empty file)' : $content;
    }

    private function writeFile(Server $server, string $path, string $content): string
    {
        $this->files->setServer($server)->putContent($path, $content);

        return 'Wrote ' . strlen($content) . ' bytes to ' . $path . '.';
    }

    private function createDirectory(Server $server, string $path): string
    {
        $path = '/' . trim($path, '/');
        $this->files->setServer($server)->createDirectory(basename($path), dirname($path));

        return 'Created folder ' . $path . '.';
    }

    private function deleteFiles(Server $server, array $paths): string
    {
        $done = [];
        foreach ($paths as $path) {
            $path = '/' . trim((string) $path, '/');
            if ($path === '/') {
                throw new AiToolException('Refusing to delete the server root.');
            }
            $this->files->setServer($server)->deleteFiles(dirname($path), [basename($path)]);
            $done[] = $path;
        }

        return 'Deleted: ' . implode(', ', $done);
    }

    private function renameFile(Server $server, string $from, string $to): string
    {
        $this->files->setServer($server)->renameFiles('/', [['from' => ltrim($from, '/'), 'to' => ltrim($to, '/')]]);

        return 'Renamed ' . $from . ' to ' . $to . '.';
    }

    private function sendCommand(Server $server, string $command): string
    {
        $this->commands->setServer($server)->send($command);

        return 'Sent console command: ' . $command;
    }

    private function powerAction(Server $server, string $action): string
    {
        $this->power->setServer($server)->send($action);

        return 'Power action "' . $action . '" sent.';
    }
}
