<?php

declare(strict_types=1);

namespace App\Platform\Backup\DisasterRecovery;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DisasterRecoveryDatabaseProbe
{
    /**
     * @param array{host: string, port: string, username: string, password: string, database: string} $targetConnection
     * @return array<string, mixed>
     */
    public function inspect(array $targetConnection): array
    {
        return $this->withProbeConnection($targetConnection, function (string $connectionName, ConnectionInterface $connection) use ($targetConnection): array {
            $database = (string) ($targetConnection['database'] ?? '');
            $schema = Schema::connection($connectionName);
            $sampleLimit = max(1, (int) config('booking_disaster_recovery.report_sample_limit', 3));

            $requiredTables = [];
            foreach ((array) config('booking_disaster_recovery.required_tables', []) as $table) {
                $requiredTables[(string) $table] = [
                    'exists' => $schema->hasTable((string) $table),
                ];
            }

            $schemaSummary = [
                'table_count' => (int) $connection->table('information_schema.tables')
                    ->where('table_schema', $database)
                    ->where('table_type', 'BASE TABLE')
                    ->count(),
                'view_count' => (int) $connection->table('information_schema.tables')
                    ->where('table_schema', $database)
                    ->where('table_type', 'VIEW')
                    ->count(),
                'trigger_count' => (int) $connection->table('information_schema.triggers')
                    ->where('trigger_schema', $database)
                    ->count(),
                'procedure_count' => (int) $connection->table('information_schema.routines')
                    ->where('routine_schema', $database)
                    ->where('routine_type', 'PROCEDURE')
                    ->count(),
                'function_count' => (int) $connection->table('information_schema.routines')
                    ->where('routine_schema', $database)
                    ->where('routine_type', 'FUNCTION')
                    ->count(),
                'event_count' => (int) $connection->table('information_schema.events')
                    ->where('event_schema', $database)
                    ->count(),
            ];

            $samples = [];
            $errors = [];
            $warnings = [];

            foreach ((array) config('booking_disaster_recovery.sample_tables', []) as $table => $definition) {
                $table = (string) $table;
                $definition = is_array($definition) ? $definition : [];
                $orderBy = (string) ($definition['order_by'] ?? 'id');
                $columns = array_values(array_filter(array_map('strval', (array) ($definition['columns'] ?? []))));
                $expectsNonZero = (bool) ($definition['expect_nonzero'] ?? false);

                if (! $schema->hasTable($table)) {
                    $samples[$table] = [
                        'exists' => false,
                        'row_count' => null,
                        'rows' => [],
                    ];
                    $errors[] = sprintf('Critical sample table [%s] is missing from the restored target.', $table);

                    continue;
                }

                try {
                    $rowCount = (int) $connection->table($table)->count();
                    $rows = $connection->table($table)
                        ->orderBy($orderBy)
                        ->limit($sampleLimit)
                        ->get($columns)
                        ->map(static fn (object $row): array => (array) $row)
                        ->all();

                    $samples[$table] = [
                        'exists' => true,
                        'row_count' => $rowCount,
                        'rows' => $rows,
                    ];

                    if ($expectsNonZero && $rowCount === 0) {
                        $warnings[] = sprintf('Anchor table [%s] restored with zero rows.', $table);
                    }
                } catch (Throwable $exception) {
                    $samples[$table] = [
                        'exists' => true,
                        'row_count' => null,
                        'rows' => [],
                        'error' => $exception->getMessage(),
                    ];
                    $errors[] = sprintf('Unable to sample restored table [%s]: %s', $table, $exception->getMessage());
                }
            }

            return [
                'ok' => ($errors === []),
                'schema_summary' => $schemaSummary,
                'required_tables' => $requiredTables,
                'samples' => $samples,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * @param array{host: string, port: string, username: string, password: string, database: string} $targetConnection
     * @param callable(string, ConnectionInterface): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function withProbeConnection(array $targetConnection, callable $callback): array
    {
        $default = (string) config('database.default');
        $baseConfig = config('database.connections.' . $default);
        if (! is_array($baseConfig)) {
            throw new \RuntimeException(sprintf('Base database connection [%s] is not configured.', $default));
        }

        $connectionName = 'drill_restore_probe';
        config([
            'database.connections.' . $connectionName => array_merge($baseConfig, [
                'host' => $targetConnection['host'],
                'port' => $targetConnection['port'],
                'database' => $targetConnection['database'],
                'username' => $targetConnection['username'],
                'password' => $targetConnection['password'],
            ]),
        ]);

        DB::purge($connectionName);

        try {
            $connection = DB::connection($connectionName);

            return $callback($connectionName, $connection);
        } finally {
            DB::purge($connectionName);
            config(['database.connections.' . $connectionName => null]);
        }
    }
}
