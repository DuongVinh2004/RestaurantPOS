<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Application\UseCases\Import;

use App\Modules\MasterDataExchange\Application\UseCases\Registry\ResolveMasterDataDomainHandler;
use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Modules\MasterDataExchange\Infrastructure\Files\Parsers\MasterDataImportSourceParser;

final class ValidateMasterDataImportHandler
{
    public function __construct(
        private readonly ResolveMasterDataDomainHandler $resolveMasterDataDomainHandler,
        private readonly MasterDataImportSourceParser $masterDataImportSourceParser,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *     domain:MasterDataDomain,
     *     analysis:array{rows:list<array<string,mixed>>,summary:array<string,int>},
     *     format:string,
     *     report:array<string,mixed>
     * }
     */
    public function handle(string $domainKey, array $payload): array
    {
        $domain = $this->resolveMasterDataDomainHandler->handle($domainKey);
        $parsed = $this->masterDataImportSourceParser->parse($payload);
        $analysis = $domain->analyze($parsed['rows']);
        $report = $this->buildReport(
            $domain,
            $parsed['format'],
            (string) ($payload['mode'] ?? 'dry_run'),
            $analysis,
            $this->schemaErrors($domain, $parsed['columns']),
        );

        return [
            'domain' => $domain,
            'analysis' => $analysis,
            'format' => $parsed['format'],
            'report' => $report,
        ];
    }

    /**
     * @return list<array{field:string,message:string}>
     */
    private function schemaErrors(MasterDataDomain $domain, array $columns): array
    {
        $errors = [];
        $allowedColumns = $domain->importColumns();
        $requiredColumns = $domain->requiredColumns();

        $unknownColumns = array_values(array_diff($columns, $allowedColumns));
        if ($unknownColumns !== []) {
            $errors[] = [
                'field' => 'columns',
                'message' => 'Unknown columns: '.implode(', ', $unknownColumns).'.',
            ];
        }

        $missingColumns = array_values(array_diff($requiredColumns, $columns));
        if ($missingColumns !== []) {
            $errors[] = [
                'field' => 'columns',
                'message' => 'Missing required columns: '.implode(', ', $missingColumns).'.',
            ];
        }

        return $errors;
    }

    /**
     * @param  array{rows:list<array<string,mixed>>,summary:array<string,int>}  $analysis
     * @param  list<array{field:string,message:string}>  $schemaErrors
     * @return array<string,mixed>
     */
    private function buildReport(MasterDataDomain $domain, string $format, string $mode, array $analysis, array $schemaErrors): array
    {
        $canCommit = $schemaErrors === [] && (int) ($analysis['summary']['invalid_rows'] ?? 0) === 0;

        return [
            'domain' => $domain->key(),
            'label' => $domain->label(),
            'format' => $format,
            'mode' => $mode,
            'can_commit' => $canCommit,
            'schema' => [
                'columns' => $domain->importColumns(),
                'required_columns' => $domain->requiredColumns(),
                'errors' => $schemaErrors,
            ],
            'summary' => $analysis['summary'],
            'rows' => array_map(function (array $row): array {
                unset($row['match_key_value'], $row['_apply']);

                return $row;
            }, $analysis['rows']),
            'commit' => null,
        ];
    }
}
