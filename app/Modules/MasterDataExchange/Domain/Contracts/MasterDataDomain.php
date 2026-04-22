<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Domain\Contracts;

interface MasterDataDomain
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<string>
     */
    public function importColumns(): array;

    /**
     * @return list<string>
     */
    public function requiredColumns(): array;

    /**
     * @return list<array<string,mixed>>
     */
    public function exportRows(string $format): array;

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{rows:list<array<string,mixed>>,summary:array<string,int>}
     */
    public function analyze(array $rows): array;

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{created:int,updated:int,unchanged:int,changes:list<array<string,mixed>>}
     */
    public function apply(array $rows, int $actorUserId): array;
}
