<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReservationBenefitsPreviewResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $vouchers = collect((array) data_get($this->resource, 'available_vouchers', []))
            ->map(fn ($row) => (new CustomerVoucherResource($row))->resolve($request))
            ->values()
            ->all();

        return [
            'reservation' => data_get($this->resource, 'reservation'),
            'available_vouchers' => $vouchers,
        ];
    }
}
