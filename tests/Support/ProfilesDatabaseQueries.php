<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait ProfilesDatabaseQueries
{
    /**
     * @return array{
     *     result:mixed,
     *     query_count:int,
     *     sql_time_ms:float,
     *     wall_time_ms:float,
     *     query_patterns:list<array{count:int,sql:string}>
     * }
     */
    protected function profileQueries(callable $callback): array
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $startedAt = hrtime(true);
        $result = $callback();
        $finishedAt = hrtime(true);

        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        $sqlTimeMs = 0.0;
        $patternCounts = [];
        foreach ($queries as $query) {
            $sqlTimeMs += (float) ($query['time'] ?? 0.0);
            $sql = preg_replace('/\s+/', ' ', trim((string) ($query['query'] ?? '')));
            if ($sql === null || $sql === '') {
                continue;
            }

            $patternCounts[$sql] = (int) ($patternCounts[$sql] ?? 0) + 1;
        }

        arsort($patternCounts);
        $queryPatterns = [];
        foreach (array_slice($patternCounts, 0, 8, true) as $sql => $count) {
            $queryPatterns[] = [
                'count' => $count,
                'sql' => $sql,
            ];
        }

        return [
            'result' => $result,
            'query_count' => count($queries),
            'sql_time_ms' => round($sqlTimeMs, 2),
            'wall_time_ms' => round(($finishedAt - $startedAt) / 1_000_000, 2),
            'query_patterns' => $queryPatterns,
        ];
    }
}
