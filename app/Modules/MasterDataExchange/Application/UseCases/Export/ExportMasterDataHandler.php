<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Application\UseCases\Export;

use App\Modules\MasterDataExchange\Application\UseCases\Registry\ResolveMasterDataDomainHandler;

final class ExportMasterDataHandler
{
    public function __construct(
        private readonly ResolveMasterDataDomainHandler $resolveMasterDataDomainHandler,
    ) {}

    /**
     * @return array{format:string,rows:list<array<string,mixed>>,columns:list<string>,filename:string,meta:array<string,mixed>}
     */
    public function handle(string $domainKey, string $format): array
    {
        $domain = $this->resolveMasterDataDomainHandler->handle($domainKey);
        $rows = $domain->exportRows($format);

        return [
            'format' => $format,
            'rows' => $rows,
            'columns' => $domain->importColumns(),
            'filename' => $domainKey.'_export_'.now('UTC')->format('Ymd_His').'.'.$format,
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
}
