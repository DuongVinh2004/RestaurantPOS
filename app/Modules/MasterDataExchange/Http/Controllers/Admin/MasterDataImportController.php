<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Http\Controllers\Admin;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\MasterDataExchange\Application\Workflows\MasterDataImportWorkflow;
use App\Modules\MasterDataExchange\Http\Requests\Admin\ImportMasterDataRequest;
use App\Modules\MasterDataExchange\Http\Resources\Admin\MasterDataImportResultResource;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

class MasterDataImportController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly MasterDataImportWorkflow $masterDataImportWorkflow,
    ) {}

    #[ResponseFromApiResource(MasterDataImportResultResource::class)]
    public function import(ImportMasterDataRequest $request, string $domain): JsonResponse
    {
        $payload = $request->validated();
        $payload['file'] = $request->file('file');

        $result = $this->masterDataImportWorkflow->handle(
            $domain,
            $payload,
            $this->resolveStaffActorUserId($request),
        );

        return response()->json([
            'data' => (new MasterDataImportResultResource($result['data']))->toArray($request),
            'meta' => $result['meta'],
        ], (int) $result['status'], [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
