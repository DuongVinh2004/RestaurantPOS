<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportingDailySalesSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $turn = function (mixed $value): float {
            return round((float) ($value ?? 0), 2);
        };

        return [
            'snapshot_id' => (int) $this->snapshot_id,
            'business_date' => optional($this->business_date)?->format('Y-m-d'),
            'currency' => (string) $this->currency,
            'branch_id' => (int) $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => (string) $this->branch->branch_code,
                'branch_name' => (string) $this->branch->branch_name,
                'is_default' => (bool) $this->branch->is_default,
            ]),
            'billed' => [
                'reservation_count' => (int) $this->billed_reservation_count,
                'guest_count' => (int) $this->billed_guest_count,
                'gross_bill_amount' => $turn($this->gross_bill_amount),
                'discount_amount' => $turn($this->discount_amount),
                'billed_total_amount' => $turn($this->billed_total_amount),
            ],
            'invoices' => [
                'issued_count' => (int) $this->invoice_issued_count,
                'issued_total_amount' => $turn($this->invoiced_total_amount),
                'tax_amount' => $turn($this->invoiced_tax_amount),
            ],
            'payments' => [
                'payment_row_count' => (int) $this->payment_row_count,
                'refund_row_count' => (int) $this->refund_row_count,
                'captured_amount' => $turn($this->captured_amount),
                'refunded_amount' => $turn($this->refunded_amount),
                'net_paid_amount' => $turn($this->net_paid_amount),
                'deposit_net_amount' => $turn($this->deposit_net_amount),
                'final_net_amount' => $turn($this->final_net_amount),
            ],
            'cashier' => [
                'closed_shift_count' => (int) $this->cashier_shift_closed_count,
                'cash_discrepancy_amount' => $turn($this->cash_discrepancy_amount),
            ],
            'freshness' => [
                'refreshed_at' => $this->refreshed_at?->utc()->toIso8601String(),
            ],
        ];
    }
}
