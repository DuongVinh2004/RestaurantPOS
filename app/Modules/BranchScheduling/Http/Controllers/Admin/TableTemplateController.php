<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableManagementService;
use App\Modules\BranchScheduling\Http\Resources\Admin\TableTemplateResource;
use Illuminate\Http\JsonResponse;

class TableTemplateController extends Controller
{
    public function __construct(
        private readonly RestaurantTableManagementService $tableService,
    ) {}

    public function index(): JsonResponse
    {
        $templates = $this->tableService->listTemplates();

        return response()->json([
            'data' => TableTemplateResource::collection(collect($templates))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_templates',
                'count' => count($templates),
            ],
        ]);
    }
}
