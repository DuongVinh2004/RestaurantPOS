<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Application\UseCases\Import;

use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Support\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportMasterDataHandler
{
    /**
     * @param  array{rows:list<array<string,mixed>>,summary:array<string,int>}  $analysis
     * @return array{batch_id:string,committed_at:string,created:int,updated:int,unchanged:int}
     */
    public function handle(MasterDataDomain $domain, array $analysis, int $actorUserId, string $format): array
    {
        $batchId = 'bulk-import-'.$domain->key().'-'.str_replace('-', '', (string) Str::uuid());

        $applied = DB::transaction(function () use ($domain, $analysis, $actorUserId, $batchId, $format): array {
            $result = $domain->apply($analysis['rows'], $actorUserId);
            $this->recordBatchAudit($domain, $result, $actorUserId, $batchId, $format);

            return $result;
        });

        return [
            'batch_id' => $batchId,
            'committed_at' => now('UTC')->toIso8601String(),
            'created' => (int) $applied['created'],
            'updated' => (int) $applied['updated'],
            'unchanged' => (int) $applied['unchanged'],
        ];
    }

    /**
     * @param  array{created:int,updated:int,unchanged:int,changes:list<array<string,mixed>>}  $result
     */
    private function recordBatchAudit(
        MasterDataDomain $domain,
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
                    'key' => 'staff_user:'.$actorUserId,
                ],
            ],
        ]);
    }
}
