<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationBenefitsPreviewResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $vouchers = collect((array) data_get($this->resource, 'available_vouchers', []))
            ->map(fn ($row) => (new VoucherResource($row))->resolve($request))
            ->values()
            ->all();

        return [
            'reservation' => data_get($this->resource, 'reservation'),
            'available_vouchers' => $vouchers,
        ];
    }
}
