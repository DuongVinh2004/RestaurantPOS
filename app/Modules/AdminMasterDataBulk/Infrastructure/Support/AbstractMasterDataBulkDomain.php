<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Infrastructure\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

abstract class AbstractMasterDataBulkDomain
{
    /**
     * @return array{field:string,message:string}
     */
    protected function error(string $field, string $message): array
    {
        return [
            'field' => $field,
            'message' => $message,
        ];
    }

    /**
     * @return list<array{field:string,message:string}>
     */
    protected function validationErrors(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = $this->error((string) $field, (string) $message);
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    protected function rawRow(array $row): array
    {
        $raw = $row;
        unset($raw['_row_number']);

        return $raw;
    }

    protected function rowNumber(array $row): int
    {
        return max(1, (int) ($row['_row_number'] ?? 1));
    }

    protected function trimmed(mixed $value): string
    {
        return trim((string) $value);
    }

    protected function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    protected function booleanValue(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => (bool) $value,
        };
    }

    protected function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function decimalValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function isoDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

    protected function jsonValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    protected function projectSnapshot(array $snapshot, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $snapshot[$column] ?? null;
        }

        return $projected;
    }

    protected function sameSnapshot(array $before, array $after): bool
    {
        return json_encode($this->sortRecursive($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            === json_encode($this->sortRecursive($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function makeSummary(array $rows): array
    {
        $summary = [
            'total_rows' => count($rows),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'create_count' => 0,
            'update_count' => 0,
            'unchanged_count' => 0,
        ];

        foreach ($rows as $row) {
            $operation = (string) ($row['operation'] ?? 'invalid');
            if ($operation === 'invalid') {
                $summary['invalid_rows']++;
                continue;
            }

            $summary['valid_rows']++;

            if ($operation === 'create') {
                $summary['create_count']++;
                continue;
            }

            if ($operation === 'update') {
                $summary['update_count']++;
                continue;
            }

            $summary['unchanged_count']++;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    protected function applyDuplicateKeyErrors(array &$rows, string $keyField = 'match_key_value'): void
    {
        $duplicates = [];

        foreach ($rows as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            $value = $row[$keyField] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }

            $duplicates[$value][] = $index;
        }

        foreach ($duplicates as $value => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $lineNumbers = array_map(
                static fn (int $index): int => (int) ($rows[$index]['row_number'] ?? 0),
                $indexes
            );

            foreach ($indexes as $index) {
                $rows[$index]['errors'][] = $this->error(
                    'row',
                    sprintf('Duplicate upsert key [%s] also appears at row(s) %s.', $value, implode(', ', $lineNumbers))
                );
                $rows[$index]['operation'] = 'invalid';
                $rows[$index]['status'] = 'invalid';
            }
        }
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }
}
