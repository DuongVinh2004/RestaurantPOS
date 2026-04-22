<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Infrastructure\Files\Parsers;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use JsonException;

final class MasterDataImportSourceParser
{
    private const MAX_ROWS = 500;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{format:string,columns:list<string>,rows:list<array<string,mixed>>}
     */
    public function parse(array $payload): array
    {
        if (is_array($payload['rows'] ?? null)) {
            return $this->parseRowsArray((array) $payload['rows']);
        }

        $file = $payload['file'] ?? null;
        $format = strtolower((string) ($payload['format'] ?? $this->guessFormat($file)));
        $content = $this->resolveContent($payload, $file);

        if ($content === '') {
            throw ValidationException::withMessages([
                'file' => ['Import source is empty.'],
            ]);
        }

        return match ($format) {
            'json' => $this->parseJsonContent($content),
            'csv' => $this->parseCsvContent($content),
            default => throw ValidationException::withMessages([
                'format' => ['Unsupported import format.'],
            ]),
        };
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{format:string,columns:list<string>,rows:list<array<string,mixed>>}
     */
    private function parseRowsArray(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => ['rows payload cannot be empty.'],
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'rows' => ['rows payload exceeds the maximum allowed 500 rows per import.'],
            ]);
        }

        $columns = [];
        $parsedRows = [];

        foreach ($rows as $index => $row) {
            $columns = array_values(array_unique(array_merge($columns, array_keys($row))));
            $parsedRows[] = array_merge([
                '_row_number' => $index + 1,
            ], $row);
        }

        return [
            'format' => 'json',
            'columns' => $columns,
            'rows' => $parsedRows,
        ];
    }

    /**
     * @return array{format:string,columns:list<string>,rows:list<array<string,mixed>>}
     */
    private function parseJsonContent(string $content): array
    {
        try {
            $decoded = json_decode($this->removeBom($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'content' => ['Import json payload is malformed.'],
            ]);
        }

        $rows = is_array($decoded) && array_is_list($decoded)
            ? $decoded
            : (is_array($decoded['rows'] ?? null) ? $decoded['rows'] : null);

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages([
                'content' => ['Import json payload must be a non-empty array of rows or an object with rows.'],
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'content' => ['Import payload exceeds the maximum allowed 500 rows per import.'],
            ]);
        }

        $columns = [];
        $parsedRows = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'content' => ['Each json row must be an object.'],
                ]);
            }

            $columns = array_values(array_unique(array_merge($columns, array_keys($row))));
            $parsedRows[] = array_merge([
                '_row_number' => $index + 1,
            ], $row);
        }

        return [
            'format' => 'json',
            'columns' => $columns,
            'rows' => $parsedRows,
        ];
    }

    /**
     * @return array{format:string,columns:list<string>,rows:list<array<string,mixed>>}
     */
    private function parseCsvContent(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'content' => ['Unable to read import csv payload.'],
            ]);
        }

        fwrite($handle, $this->removeBom($content));
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === [null] || $header === []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'content' => ['Import csv payload must include a header row.'],
            ]);
        }

        $columns = array_map(static fn (mixed $value): string => trim((string) $value), $header);
        $columns = array_values(array_filter($columns, static fn (string $value): bool => $value !== ''));

        if ($columns === []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'content' => ['Import csv header row is empty.'],
            ]);
        }

        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $cells = array_pad($data, count($columns), null);
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = $cells[$index] ?? null;
            }

            $hasValue = collect($row)->contains(static fn (mixed $value): bool => trim((string) ($value ?? '')) !== '');
            if (! $hasValue) {
                continue;
            }

            $rows[] = array_merge([
                '_row_number' => $lineNumber,
            ], $row);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'content' => ['Import csv payload has no data rows.'],
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'content' => ['Import payload exceeds the maximum allowed 500 rows per import.'],
            ]);
        }

        return [
            'format' => 'csv',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveContent(array $payload, mixed $file): string
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getPathname();
            $content = $path !== '' ? file_get_contents($path) : false;

            return $content !== false ? (string) $content : '';
        }

        return (string) ($payload['content'] ?? '');
    }

    private function guessFormat(mixed $file): string
    {
        if (! $file instanceof UploadedFile) {
            return '';
        }

        return match (strtolower((string) $file->getClientOriginalExtension())) {
            'json' => 'json',
            'csv', 'txt' => 'csv',
            default => '',
        };
    }

    private function removeBom(string $content): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    }
}
