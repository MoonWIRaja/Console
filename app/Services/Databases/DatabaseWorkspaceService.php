<?php

namespace Pterodactyl\Services\Databases;

use PDO;
use Pterodactyl\Models\Database;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

class DatabaseWorkspaceService
{
    private const CONNECTION_NAME = 'dynamic_client_database';
    private const DEFAULT_PAGE_SIZE = 25;
    private const MAX_PAGE_SIZE = 100;
    private const MAX_QUERY_ROWS = 250;

    public function __construct(
        private ConfigRepository $config,
        private DatabaseManager $databaseManager,
        private Encrypter $encrypter,
        private InternalHostResolver $hostResolver,
    ) {
    }

    public function getHealth(Database $database): array
    {
        $start = microtime(true);

        try {
            $connection = $this->connection($database);
            $connection->selectOne('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            $version = $connection->selectOne('SELECT VERSION() AS version');
            $tables = $this->tableStatuses($connection);

            return [
                'reachable' => true,
                'latency_ms' => $latency,
                'server_version' => (string) ($version->version ?? 'Unknown'),
                'table_count' => count($tables),
                'estimated_rows' => array_sum(array_map(fn (array $table) => (int) $table['rows'], $tables)),
                'size_bytes' => array_sum(array_map(fn (array $table) => (int) $table['size_bytes'], $tables)),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $exception) {
            $formatted = $this->formatException($exception);

            if ($formatted instanceof DisplayException) {
                return $this->unreachableHealth($formatted->getMessage());
            }

            throw $formatted;
        } finally {
            $this->disconnect();
        }
    }

    public function getTables(Database $database): array
    {
        $connection = $this->connection($database);

        try {
            return $this->tableStatuses($connection);
        } catch (\Throwable $exception) {
            throw $this->formatException($exception);
        } finally {
            $this->disconnect();
        }
    }

    public function getTableRows(Database $database, string $table, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): array
    {
        $connection = $this->connection($database);
        $table = trim($table);
        $page = max(1, $page);
        $perPage = min(self::MAX_PAGE_SIZE, max(1, $perPage));

        try {
            $validatedTable = $this->ensureTableExists($connection, $table);
            $quotedTable = $this->quoteIdentifier($validatedTable);

            $count = $connection->selectOne("SELECT COUNT(*) AS aggregate FROM {$quotedTable}");
            $total = (int) ($count->aggregate ?? 0);
            $offset = ($page - 1) * $perPage;
            $totalPages = max((int) ceil($total / max($perPage, 1)), 1);

            $columns = collect($connection->select("SHOW FULL COLUMNS FROM {$quotedTable}"))
                ->map(fn ($column) => [
                    'name' => (string) ($column->Field ?? ''),
                    'type' => (string) ($column->Type ?? ''),
                    'nullable' => ($column->Null ?? '') === 'YES',
                    'key' => (string) ($column->Key ?? ''),
                    'default' => $column->Default,
                    'extra' => (string) ($column->Extra ?? ''),
                    'comment' => (string) ($column->Comment ?? ''),
                ])
                ->values()
                ->all();

            $statement = $connection->getPdo()->query(
                sprintf('SELECT * FROM %s LIMIT %d OFFSET %d', $quotedTable, $perPage, $offset)
            );
            $rows = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

            return [
                'table' => $validatedTable,
                'columns' => $columns,
                'rows' => $rows,
                'pagination' => [
                    'total' => $total,
                    'count' => count($rows),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                ],
            ];
        } catch (\Throwable $exception) {
            throw $this->formatException($exception);
        } finally {
            $this->disconnect();
        }
    }

    public function executeQuery(Database $database, string $query, bool $allowMutations): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new DisplayException('Please provide a SQL query to execute.');
        }

        if ($this->containsMultipleStatements($query)) {
            throw new DisplayException('The SQL dashboard only supports running one statement at a time. Use Import for multi-statement SQL.');
        }

        $type = $this->queryType($query);
        if ($type === '') {
            throw new DisplayException('Unable to determine the query type for this statement.');
        }

        if (($unsupportedReason = $this->unsupportedQueryReason($query)) !== null) {
            throw new DisplayException($unsupportedReason);
        }

        $connection = $this->connection($database);
        $startedAt = microtime(true);

        try {
            if ($this->isReadQueryType($type)) {
                $statement = $connection->getPdo()->query($query);
                if (!$statement) {
                    throw new DisplayException('The SQL query did not return a result set.');
                }

                $columns = [];
                for ($index = 0; $index < $statement->columnCount(); $index++) {
                    $meta = $statement->getColumnMeta($index) ?: [];
                    $columns[] = (string) ($meta['name'] ?? ('column_' . $index));
                }

                $rows = [];
                $truncated = false;
                while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    if (count($rows) >= self::MAX_QUERY_ROWS) {
                        $truncated = true;
                        break;
                    }

                    $rows[] = $row;
                }

                return [
                    'type' => $type,
                    'mode' => 'read',
                    'columns' => $columns,
                    'rows' => $rows,
                    'row_count' => count($rows),
                    'truncated' => $truncated,
                    'affected_rows' => null,
                    'execution_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'message' => $truncated
                        ? 'Query executed successfully. Results were limited to the first 250 rows.'
                        : 'Query executed successfully.',
                ];
            }

            if (!$allowMutations) {
                throw new DisplayException('This account can only run read-only SQL queries from the dashboard.');
            }

            $affected = $connection->affectingStatement($query);

            return [
                'type' => $type,
                'mode' => 'write',
                'columns' => [],
                'rows' => [],
                'row_count' => 0,
                'truncated' => false,
                'affected_rows' => $affected,
                'execution_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'message' => 'Statement executed successfully.',
            ];
        } catch (\Throwable $exception) {
            throw $this->formatException($exception);
        } finally {
            $this->disconnect();
        }
    }

    public function import(Database $database, string $sql): array
    {
        if (trim($sql) === '') {
            throw new DisplayException('The SQL import is empty.');
        }

        ['sql' => $sanitizedSql, 'skipped_statements' => $skippedStatements] = $this->sanitizeImportSql($sql);
        if (trim($sanitizedSql) === '') {
            throw new DisplayException(
                'The SQL import only contained administrative statements that cannot be executed inside this database. ' .
                'Remove CREATE DATABASE, USE, GRANT, or similar server-level statements and try again.'
            );
        }

        $startedAt = microtime(true);
        $host = $this->hostResolver->forDatabaseHost($database->host);
        try {
            [$stdout, $stderr] = $this->runClientCommand(
                $database,
                $this->resolveBinary(['mysql', '/usr/bin/mysql', '/bin/mysql']),
                [
                    '--default-character-set=utf8mb4',
                    '--host=' . $host,
                    '--port=' . $database->host->port,
                    '--user=' . $database->username,
                    $database->database,
                ],
                $sanitizedSql
            );
        } catch (DisplayException $exception) {
            throw $this->formatImportException($database, $exception);
        }

        return [
            'message' => $skippedStatements > 0
                ? 'SQL import completed successfully. Unsupported database-level statements were skipped automatically.'
                : 'SQL import completed successfully.',
            'bytes' => strlen($sanitizedSql),
            'skipped_statements' => $skippedStatements,
            'execution_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'output' => trim($stdout ?: $stderr),
        ];
    }

    public function export(Database $database, bool $schemaOnly = false): array
    {
        $startedAt = microtime(true);
        $host = $this->hostResolver->forDatabaseHost($database->host);
        [$stdout] = $this->runClientCommand(
            $database,
            $this->resolveBinary(['mysqldump', '/usr/bin/mysqldump', '/bin/mysqldump']),
            array_values(array_filter([
                '--default-character-set=utf8mb4',
                '--skip-comments',
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                $schemaOnly ? '--no-data' : null,
                '--host=' . $host,
                '--port=' . $database->host->port,
                '--user=' . $database->username,
                $database->database,
            ]))
        );

        return [
            'filename' => sprintf(
                '%s-%s.sql',
                $database->database,
                now()->format('Ymd-His')
            ),
            'contents' => $stdout,
            'schema_only' => $schemaOnly,
            'execution_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }

    private function tableStatuses(ConnectionInterface $connection): array
    {
        return collect($connection->select('SHOW TABLE STATUS'))
            ->map(fn ($table) => [
                'name' => (string) ($table->Name ?? ''),
                'engine' => (string) ($table->Engine ?? ''),
                'rows' => (int) ($table->Rows ?? 0),
                'collation' => (string) ($table->Collation ?? ''),
                'size_bytes' => (int) (($table->Data_length ?? 0) + ($table->Index_length ?? 0)),
                'data_length' => (int) ($table->Data_length ?? 0),
                'index_length' => (int) ($table->Index_length ?? 0),
                'created_at' => $table->Create_time,
                'updated_at' => $table->Update_time,
                'comment' => (string) ($table->Comment ?? ''),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function connection(Database $database): ConnectionInterface
    {
        $database->loadMissing('host');
        $host = $this->hostResolver->forDatabaseHost($database->host);

        $base = $this->config->get('database.connections.mysql', []);
        $this->config->set('database.connections.' . self::CONNECTION_NAME, array_merge($base, [
            'driver' => $base['driver'] ?? 'mysql',
            'host' => $host,
            'port' => $database->host->port,
            'database' => $database->database,
            'username' => $database->username,
            'password' => $this->encrypter->decrypt($database->password),
            'charset' => $base['charset'] ?? 'utf8mb4',
            'collation' => $base['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => $base['strict'] ?? false,
        ]));

        $this->databaseManager->purge(self::CONNECTION_NAME);

        return $this->databaseManager->connection(self::CONNECTION_NAME);
    }

    private function disconnect(): void
    {
        $this->databaseManager->disconnect(self::CONNECTION_NAME);
        $this->databaseManager->purge(self::CONNECTION_NAME);
    }

    private function unreachableHealth(string $message): array
    {
        return [
            'reachable' => false,
            'latency_ms' => 0,
            'server_version' => 'Unavailable',
            'table_count' => 0,
            'estimated_rows' => 0,
            'size_bytes' => 0,
            'checked_at' => now()->toIso8601String(),
            'error_message' => $message,
        ];
    }

    private function ensureTableExists(ConnectionInterface $connection, string $table): string
    {
        $databaseName = (string) $connection->getDatabaseName();
        $result = $connection->selectOne(
            'SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [$databaseName, $table]
        );

        if (is_null($result)) {
            throw new DisplayException('The requested table could not be found in this database.');
        }

        return $table;
    }

    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function containsMultipleStatements(string $query): bool
    {
        $statementCount = 0;
        $length = strlen($query);
        $singleQuote = false;
        $doubleQuote = false;
        $backtick = false;
        $lineComment = false;
        $blockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $query[$index];
            $next = $index + 1 < $length ? $query[$index + 1] : null;
            $prev = $index > 0 ? $query[$index - 1] : null;

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                }

                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }

                continue;
            }

            if (!$singleQuote && !$doubleQuote && !$backtick) {
                if ($char === '-' && $next === '-') {
                    $lineComment = true;
                    $index++;
                    continue;
                }

                if ($char === '#') {
                    $lineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $blockComment = true;
                    $index++;
                    continue;
                }
            }

            if ($char === '\'' && !$doubleQuote && !$backtick && $prev !== '\\') {
                $singleQuote = !$singleQuote;
                continue;
            }

            if ($char === '"' && !$singleQuote && !$backtick && $prev !== '\\') {
                $doubleQuote = !$doubleQuote;
                continue;
            }

            if ($char === '`' && !$singleQuote && !$doubleQuote) {
                $backtick = !$backtick;
                continue;
            }

            if ($char === ';' && !$singleQuote && !$doubleQuote && !$backtick) {
                $statementCount++;
            }
        }

        if ($statementCount === 0) {
            return false;
        }

        return $statementCount > 1 || preg_replace('/;+[\s\r\n]*$/', '', $query) !== str_replace(';', '', preg_replace('/;+[\s\r\n]*$/', '', $query));
    }

    private function queryType(string $query): string
    {
        $cleaned = trim($query);

        while (true) {
            if (preg_match('/^--.*?(?:\r\n|\r|\n)/s', $cleaned, $matches)) {
                $cleaned = ltrim(substr($cleaned, strlen($matches[0])));
                continue;
            }

            if (preg_match('/^#.*?(?:\r\n|\r|\n)/s', $cleaned, $matches)) {
                $cleaned = ltrim(substr($cleaned, strlen($matches[0])));
                continue;
            }

            if (preg_match('/^\/\*.*?\*\//s', $cleaned, $matches)) {
                $cleaned = ltrim(substr($cleaned, strlen($matches[0])));
                continue;
            }

            break;
        }

        if ($cleaned === '') {
            return '';
        }

        preg_match('/^([a-z]+)/i', $cleaned, $matches);

        return strtolower($matches[1] ?? '');
    }

    private function isReadQueryType(string $type): bool
    {
        return in_array($type, ['select', 'show', 'describe', 'desc', 'explain'], true);
    }

    private function unsupportedQueryReason(string $query): ?string
    {
        $cleaned = trim($query);

        $patterns = [
            '/^\s*source\b/i' => 'SOURCE statements are not supported in the panel query console.',
            '/^\s*use\b/i' => 'USE statements are not supported. The panel already runs queries inside your assigned database.',
            '/^\s*(?:create|drop|alter)\s+(?:database|schema)\b/i' => 'Database-level statements such as CREATE DATABASE are not allowed here. Run table and data statements inside your assigned database only.',
            '/^\s*(?:create|alter|drop|rename)\s+user\b/i' => 'User management statements are not allowed from the panel database console.',
            '/^\s*(?:grant|revoke)\b/i' => 'Privilege management statements are not allowed from the panel database console.',
            '/^\s*set\s+password\b/i' => 'Password management statements are not allowed from the panel database console.',
            '/^\s*flush\s+privileges\b/i' => 'FLUSH PRIVILEGES is not allowed from the panel database console.',
            '/^\s*lock\s+tables\b/i' => 'LOCK TABLES is not supported in the panel database console.',
            '/^\s*unlock\s+tables\b/i' => 'UNLOCK TABLES is not supported in the panel database console.',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $cleaned) === 1) {
                return $message;
            }
        }

        return null;
    }

    private function resolveBinary(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) {
                return $candidate;
            }

            if (!str_contains($candidate, DIRECTORY_SEPARATOR)) {
                return $candidate;
            }
        }

        throw new DisplayException('The required MySQL client binary is not installed on the panel host.');
    }

    private function runClientCommand(Database $database, string $binary, array $arguments, ?string $stdin = null): array
    {
        $database->loadMissing('host');

        $command = implode(' ', array_merge(
            [escapeshellcmd($binary)],
            array_map(fn (string $argument) => str_starts_with($argument, '--')
                ? preg_replace('/=([^=]+)$/', '=' . escapeshellarg(substr($argument, strpos($argument, '=') + 1)), $argument) ?: escapeshellarg($argument)
                : escapeshellarg($argument), $arguments)
        ));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, ['MYSQL_PWD' => $this->encrypter->decrypt($database->password)]);
        if (!is_resource($process)) {
            throw new DisplayException('Unable to start the MySQL client process for this database action.');
        }

        try {
            if (!is_null($stdin)) {
                fwrite($pipes[0], $stdin);
            }

            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
        } catch (\Throwable $exception) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_terminate($process);

            throw $exception;
        }

        if ($exitCode !== 0) {
            throw new DisplayException(
                trim($stderr) !== '' ? trim($stderr) : 'The MySQL client command exited unexpectedly.'
            );
        }

        return [$stdout ?: '', $stderr ?: ''];
    }

    private function sanitizeImportSql(string $sql): array
    {
        $sanitized = str_replace("\r\n", "\n", $sql);
        $skippedStatements = 0;

        $statementPatterns = [
            '/^\s*CREATE\s+(?:DATABASE|SCHEMA)\b.*?;\s*$/ims',
            '/^\s*DROP\s+(?:DATABASE|SCHEMA)\b.*?;\s*$/ims',
            '/^\s*ALTER\s+(?:DATABASE|SCHEMA)\b.*?;\s*$/ims',
            '/^\s*USE\s+(?:`[^`]+`|[a-zA-Z0-9_$]+)\s*;\s*$/ims',
            '/^\s*LOCK\s+TABLES\b.*?;\s*$/ims',
            '/^\s*UNLOCK\s+TABLES\s*;\s*$/ims',
            '/^\s*(?:CREATE|ALTER|DROP|RENAME)\s+USER\b.*?;\s*$/ims',
            '/^\s*(?:GRANT|REVOKE)\b.*?;\s*$/ims',
            '/^\s*SET\s+PASSWORD\b.*?;\s*$/ims',
            '/^\s*FLUSH\s+PRIVILEGES\b.*?;\s*$/ims',
            '/^\s*SOURCE\b.*?;\s*$/ims',
        ];

        foreach ($statementPatterns as $pattern) {
            $sanitized = preg_replace_callback(
                $pattern,
                function () use (&$skippedStatements) {
                    $skippedStatements++;

                    return '';
                },
                $sanitized
            ) ?? $sanitized;
        }

        $sanitized = preg_replace(
            '/\bDEFINER\s*=\s*(?:`[^`]+`|\'[^\']+\'|"[^"]+"|[^@\s]+)\s*@\s*(?:`[^`]+`|\'[^\']+\'|"[^"]+"|[^ \t\r\n*;]+)/i',
            '',
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace('/\bSQL\s+SECURITY\s+DEFINER\b/i', 'SQL SECURITY INVOKER', $sanitized) ?? $sanitized;

        return [
            'sql' => trim($sanitized) . "\n",
            'skipped_statements' => $skippedStatements,
        ];
    }

    private function formatImportException(Database $database, DisplayException $exception): DisplayException
    {
        $message = $exception->getMessage();

        if (preg_match("/Access denied for user .* to database '([^']+)'/i", $message, $matches) === 1) {
            $referencedDatabase = (string) ($matches[1] ?? '');
            if ($referencedDatabase !== '' && strcasecmp($referencedDatabase, $database->database) !== 0) {
                return new DisplayException(
                    sprintf(
                        'This SQL dump still references the database "%s". Imports from the panel can only run inside "%s". ' .
                        'Remove CREATE DATABASE, USE, or database-qualified table names before importing again.',
                        $referencedDatabase,
                        $database->database
                    ),
                    $exception
                );
            }
        }

        if (preg_match('/ERROR 1227|SUPER privilege|SET_USER_ID|TRIGGER command denied|LOCK TABLES/i', $message) === 1) {
            return new DisplayException(
                'This SQL dump contains privileged statements that cannot be run by the panel database user. ' .
                'Remove definer, lock table, user, or server-level statements and try again.',
                $exception
            );
        }

        return $exception;
    }

    private function formatException(\Throwable $exception): \Exception
    {
        if ($exception instanceof DisplayException) {
            return $exception;
        }

        if ($exception instanceof QueryException && str_contains($exception->getMessage(), 'SQLSTATE[HY000] [1129]')) {
            return new DisplayException(
                'The configured database host is temporarily blocking the panel because of too many connection errors. ' .
                'An administrator needs to unblock the panel IP on the database server and verify the stored database host credentials.',
                $exception
            );
        }

        if ($exception instanceof \PDOException && str_contains($exception->getMessage(), 'SQLSTATE[HY000] [1129]')) {
            return new DisplayException(
                'The configured database host is temporarily blocking the panel because of too many connection errors. ' .
                'An administrator needs to unblock the panel IP on the database server and verify the stored database host credentials.',
                $exception
            );
        }

        if ($exception instanceof \PDOException || $exception instanceof QueryException) {
            return new DisplayException($exception->getMessage(), $exception);
        }

        return $exception instanceof \Exception
            ? $exception
            : new \RuntimeException($exception->getMessage(), (int) $exception->getCode(), $exception);
    }
}
