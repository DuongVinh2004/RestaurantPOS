<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers\Admin;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Promotions\Application\UseCases\Vouchers\VoucherManagementService;
use App\Modules\Promotions\Http\Requests\Admin\StoreVoucherRequest;
use App\Modules\Promotions\Http\Requests\Admin\UpdateVoucherRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly VoucherManagementService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list((string) $request->query('q', ''));

        return response()->json([
            'meta' => [
                'action' => 'admin_vouchers',
                'total' => count($items),
            ],
            'data' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'meta' => [
                'action' => 'admin_voucher',
            ],
            'data' => $this->service->show($id),
        ]);
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $actorUserId = $this->resolveStaffActorUserId($request);
        $voucher = $this->service->store($request->validated(), $actorUserId);

        return response()->json([
            'meta' => [
                'action' => 'admin_voucher_created',
            ],
            'data' => $voucher,
        ], 201);
    }

    public function update(int $id, UpdateVoucherRequest $request): JsonResponse
    {
        $actorUserId = $this->resolveStaffActorUserId($request);
        $voucher = $this->service->update($id, $request->validated(), $actorUserId);

        return response()->json([
            'meta' => [
                'action' => 'admin_voucher_updated',
            ],
            'data' => $voucher,
        ]);
    }
}
