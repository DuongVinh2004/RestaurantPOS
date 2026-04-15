<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\AdminMasterDataBulk\Application\Services\AdminMasterDataBulkService;
use App\Modules\AdminMasterDataBulk\Http\Requests\Admin\ExportAdminMasterDataRequest;
use App\Modules\AdminMasterDataBulk\Http\Requests\Admin\ImportAdminMasterDataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMasterDataBulkController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly AdminMasterDataBulkService $bulkService,
    ) {
    }

    public function export(ExportAdminMasterDataRequest $request, string $domain): Response|JsonResponse|StreamedResponse
    {
        $result = $this->bulkService->export(
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

    public function import(ImportAdminMasterDataRequest $request, string $domain): JsonResponse
    {
        $payload = $request->validated();
        $payload['file'] = $request->file('file');

        $result = $this->bulkService->import(
            $domain,
            $payload,
            $this->resolveStaffActorUserId($request),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], (int) $result['status'], [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
