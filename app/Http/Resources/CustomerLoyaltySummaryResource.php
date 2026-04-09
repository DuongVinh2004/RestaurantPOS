<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerLoyaltySummaryResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $transactions = LoyaltyPointTransactionResource::collection(collect((array) data_get($this->resource, 'transactions', [])))->resolve($request);

        return [
            'user' => data_get($this->resource, 'user'),
            'transactions' => $transactions,
        ];
    }
}
