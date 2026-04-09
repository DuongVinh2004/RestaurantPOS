<?php

declare(strict_types=1);

namespace App\Services\Admin\MasterDataBulk;

use App\Services\Admin\MasterDataBulk\Contracts\MasterDataBulkDomain;
use App\Services\Admin\MasterDataBulk\Support\MasterDataImportSourceParser;
use App\Support\AuditEvent;
use Illuminate\Support\Facades\DB;

class AdminMasterDataBulkService
{
    public function __construct(
        private readonly MasterDataBulkRegistry $registry,
        private readonly MasterDataImportSourceParser $parser,
    ) {
    }

    /**
     * @return array{format:string,rows:list<array<string,mixed>>,columns:list<string>,filename:string,meta:array<string,mixed>}
     */
    public function export(string $domainKey, string $format): array
    {
        $domain = $this->registry->for($domainKey);
        $rows = $domain->exportRows($format);

        return [
            'format' => $format,
            'rows' => $rows,
            'columns' => $domain->importColumns(),
            'filename' => $domainKey . '_export_' . now('UTC')->format('Ymd_His') . '.' . $format,
            'meta' => [
                'action' => 'admin_master_data_export',
                'domain' => $domain->key(),
                'label' => $domain->label(),
                'format' => $format,
                'row_count' => count($rows),
                'columns' => $domain->importColumns(),
                'required_columns' => $domain->requiredColumns(),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function import(string $domainKey, array $payload, int $actorUserId): array
    {
        $domain = $this->registry->for($domainKey);
        $parsed = $this->parser->parse($payload);
        $schemaErrors = $this->schemaErrors($domain, $parsed['columns']);
        $analysis = $domain->analyze($parsed['rows']);

        $report = $this->buildReport($domain, $parsed['format'], (string) ($payload['mode'] ?? 'dry_run'), $analysis, $schemaErrors);

        if ((string) ($payload['mode'] ?? 'dry_run') === 'dry_run') {
            return [
                'status' => 200,
                'data' => $report,
                'meta' => [
                    'action' => 'admin_master_data_import_dry_run',
                ],
            ];
        }

        if (! $report['can_commit']) {
            return [
                'status' => 422,
                'data' => $report,
                'meta' => [
                    'action' => 'admin_master_data_import_commit_rejected',
                ],
            ];
        }

        $batchId = 'bulk-import-' . $domain->key() . '-' . str_replace('-', '', (string) \Illuminate\Support\Str::uuid());

        $applied = DB::transaction(function () use ($domain, $analysis, $actorUserId, $batchId, $parsed): array {
            $result = $domain->apply($analysis['rows'], $actorUserId);
            $this->recordBatchAudit($domain, $result, $actorUserId, $batchId, $parsed['format']);

            return $result;
        });

        return [
            'status' => 200,
            'data' => array_merge($report, [
                'commit' => [
                    'batch_id' => $batchId,
                    'committed_at' => now('UTC')->toIso8601String(),
                    'created' => (int) $applied['created'],
                    'updated' => (int) $applied['updated'],
                    'unchanged' => (int) $applied['unchanged'],
                ],
            ]),
            'meta' => [
                'action' => 'admin_master_data_import_committed',
            ],
        ];
    }

    /**
     * @return list<array{field:string,message:string}>
     */
    private function schemaErrors(MasterDataBulkDomain $domain, array $columns): array
    {
        $errors = [];
        $allowedColumns = $domain->importColumns();
        $requiredColumns = $domain->requiredColumns();

        $unknownColumns = array_values(array_diff($columns, $allowedColumns));
        if ($unknownColumns !== []) {
            $errors[] = [
                'field' => 'columns',
                'message' => 'Unknown columns: ' . implode(', ', $unknownColumns) . '.',
            ];
        }

        $missingColumns = array_values(array_diff($requiredColumns, $columns));
        if ($missingColumns !== []) {
            $errors[] = [
                'field' => 'columns',
                'message' => 'Missing required columns: ' . implode(', ', $missingColumns) . '.',
            ];
        }

        return $errors;
    }

    /**
     * @param array{rows:list<array<string,mixed>>,summary:array<string,int>} $analysis
     * @param list<array{field:string,message:string}> $schemaErrors
     * @return array<string,mixed>
     */
    private function buildReport(MasterDataBulkDomain $domain, string $format, string $mode, array $analysis, array $schemaErrors): array
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

    /**
     * @param array{created:int,updated:int,unchanged:int,changes:list<array<string,mixed>>} $result
     */
    private function recordBatchAudit(
        MasterDataBulkDomain $domain,
        array $result,
        int $actorUserId,
        string $batchId,
        string $format,
    ): void {
        $subjects = [[
            'type' => 'master_data_domain',
            'id' => $domain->key(),
            'role' => 'domain',
        ]];

        foreach (array_slice((array) $result['changes'], 0, 50) as $change) {
            if (($change['entity_type'] ?? null) === null || ($change['entity_id'] ?? null) === null) {
                continue;
            }

            $subjects[] = [
                'type' => (string) $change['entity_type'],
                'id' => (string) $change['entity_id'],
                'role' => 'affected',
            ];
        }

        AuditEvent::info('admin.master_data.import.committed', [
            'batch_id' => $batchId,
            'domain' => $domain->key(),
            '_audit' => [
                'action' => 'master_data.import.committed',
                'entity_type' => 'master_data_import_batch',
                'entity_id' => $batchId,
                'subjects' => $subjects,
                'after' => [
                    'domain' => $domain->key(),
                    'format' => $format,
                    'created' => (int) $result['created'],
                    'updated' => (int) $result['updated'],
                    'unchanged' => (int) $result['unchanged'],
                ],
                'summary' => [
                    'domain' => $domain->key(),
                    'format' => $format,
                    'created' => (int) $result['created'],
                    'updated' => (int) $result['updated'],
                    'unchanged' => (int) $result['unchanged'],
                    'changed_row_count' => count((array) $result['changes']),
                ],
                'actor' => [
                    'type' => 'staff_user',
                    'user_id' => $actorUserId,
                    'key' => 'staff_user:' . $actorUserId,
                ],
            ],
        ]);
    }
}
