<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MasterDataExchange\Application\UseCases\Export\ExportMasterDataHandler;
use App\Modules\MasterDataExchange\Http\Requests\Admin\ExportMasterDataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDataExportController extends Controller
{
    public function __construct(
        private readonly ExportMasterDataHandler $exportMasterDataHandler,
    ) {}

    public function export(ExportMasterDataRequest $request, string $domain): Response|JsonResponse|StreamedResponse
    {
        $result = $this->exportMasterDataHandler->handle(
            $domain,
            (string) $request->validated('format', 'csv'),
        );

        if ($result['format'] === 'json') {
            return response()->json([
                'data' => $result['rows'],
                'meta' => $result['meta'],
            ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
        }

        $filename = (string) $result['filename'];
        $rows = (array) $result['rows'];
        $columns = (array) $result['columns'];

        return response()->streamDownload(function () use ($rows, $columns): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                $ordered = [];
                foreach ($columns as $column) {
                    $ordered[] = $row[$column] ?? null;
                }

                fputcsv($handle, $ordered);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
