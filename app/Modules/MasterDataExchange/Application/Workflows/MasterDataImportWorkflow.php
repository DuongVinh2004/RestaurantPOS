<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Application\Workflows;

use App\Modules\MasterDataExchange\Application\UseCases\Import\ImportMasterDataHandler;
use App\Modules\MasterDataExchange\Application\UseCases\Import\ValidateMasterDataImportHandler;

class MasterDataImportWorkflow
{
    public function __construct(
        private readonly ValidateMasterDataImportHandler $validateMasterDataImportHandler,
        private readonly ImportMasterDataHandler $importMasterDataHandler,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{status:int,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function handle(string $domainKey, array $payload, int $actorUserId): array
    {
        $validatedImport = $this->validateMasterDataImportHandler->handle($domainKey, $payload);
        $report = $validatedImport['report'];

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

        $commit = $this->importMasterDataHandler->handle(
            $validatedImport['domain'],
            $validatedImport['analysis'],
            $actorUserId,
            $validatedImport['format'],
        );

        return [
            'status' => 200,
            'data' => array_merge($report, [
                'commit' => $commit,
            ]),
            'meta' => [
                'action' => 'admin_master_data_import_committed',
            ],
        ];
    }
}
