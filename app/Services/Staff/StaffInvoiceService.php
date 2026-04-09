<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\BillingInvoice;
use App\Models\Reservation;
use App\Services\Finance\FinanceTaxProfileService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffInvoiceService
{
    public function __construct(
        private readonly FinanceTaxProfileService $taxProfileService,
        private readonly StaffFinancialReconciliationService $financialReconciliationService,
    ) {
    }

    /**
     * @return array{invoice:BillingInvoice,created:bool}
     */
    public function issue(int $reservationId, ?int $issuedByUserId = null): array
    {
        return DB::transaction(function () use ($reservationId, $issuedByUserId): array {
            /** @var Reservation $reservation */
            $reservation = Reservation::query()
                ->lockForUpdate()
                ->findOrFail($reservationId);

            /** @var BillingInvoice|null $existing */
            $existing = BillingInvoice::query()
                ->where('reservation_id', $reservationId)
                ->first();

            if ($existing instanceof BillingInvoice) {
                return [
                    'invoice' => $this->findInvoiceByReservationId($reservationId),
                    'created' => false,
                ];
            }

            if ($reservation->billed_at === null || $reservation->final_bill_amount === null) {
                throw ValidationException::withMessages([
                    'reservation' => ['Invoice can only be issued for a reservation with a locked bill total.'],
                ]);
            }

            $profile = $this->taxProfileService->effectiveProfile();
            if (! (bool) ($profile['prices_include_tax'] ?? true)) {
                throw ValidationException::withMessages([
                    'tax_profile' => ['Current financial truth only supports tax-inclusive invoice issuance.'],
                ]);
            }

            $totalAmount = $this->money($reservation->final_bill_amount);
            $discountAmount = $this->money($reservation->discount_amount ?? 0.0);
            $subtotalAmount = $this->money($totalAmount + $discountAmount);
            $currency = trim((string) ($reservation->bill_currency ?? 'VND')) ?: 'VND';
            $rate = round((float) ($profile['tax_rate_percentage'] ?? 0.0), 3);
            [$taxableAmount, $taxAmount] = $this->splitInclusiveTax($totalAmount, $rate);

            $invoice = new BillingInvoice();
            $invoice->fill([
                'reservation_id' => (int) $reservation->reservation_id,
                'invoice_number' => $this->generateInvoiceNumber((string) $profile['invoice_prefix'], (string) $reservation->reservation_code),
                'invoice_status' => 'Issued',
                'subtotal_amount' => number_format($subtotalAmount, 2, '.', ''),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => $currency,
                'tax_code' => $profile['tax_code'],
                'tax_name' => $profile['tax_name'],
                'tax_rate_percentage' => number_format($rate, 3, '.', ''),
                'prices_include_tax' => true,
                'taxable_amount' => number_format($taxableAmount, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'seller_name' => $profile['seller_name'],
                'seller_tax_id' => $profile['seller_tax_id'],
                'seller_address' => $profile['seller_address'],
                'issued_at' => Carbon::now('UTC'),
                'issued_by' => $issuedByUserId,
                'metadata_json' => [
                    'source' => 'reservation_financial_truth',
                    'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
                    'deposit_status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status),
                    'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
                ],
            ]);
            $invoice->save();

            return [
                'invoice' => $this->findInvoiceByReservationId($reservationId),
                'created' => true,
            ];
        }, 3);
    }

    /**
     * @return array<string,mixed>
     */
    public function show(int $reservationId): array
    {
        $invoice = $this->findInvoiceByReservationId($reservationId);
        $reconciliation = $this->financialReconciliationService->show($reservationId);

        return [
            'invoice' => $this->transformInvoice($invoice),
            'reservation' => $reconciliation['reservation'],
            'reconciliation' => $reconciliation['summary'],
            'method_breakdown' => $reconciliation['method_breakdown'],
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters = []): array
    {
        $reconciliationRows = $this->financialReconciliationService->exportRows($filters);
        $reservationIds = [];
        foreach ($reconciliationRows as $row) {
            if (isset($row['reservation_id']) && is_numeric((string) $row['reservation_id'])) {
                $reservationIds[] = (int) $row['reservation_id'];
            }
        }

        $invoices = BillingInvoice::query()
            ->whereIn('reservation_id', array_values(array_unique($reservationIds)))
            ->orderBy('billing_invoice_id')
            ->get()
            ->keyBy('reservation_id');

        $onlyInvoiced = (bool) ($filters['only_invoiced'] ?? false);
        $rows = [];

        foreach ($reconciliationRows as $row) {
            $reservationId = (int) ($row['reservation_id'] ?? 0);
            /** @var BillingInvoice|null $invoice */
            $invoice = $invoices->get($reservationId);

            if ($onlyInvoiced && ! $invoice instanceof BillingInvoice) {
                continue;
            }

            $rows[] = array_merge($row, [
                'invoice_number' => $invoice?->invoice_number,
                'invoice_status' => $invoice?->invoice_status,
                'invoice_subtotal_amount' => $this->formatMoney($invoice?->subtotal_amount),
                'invoice_discount_amount' => $this->formatMoney($invoice?->discount_amount),
                'invoice_total_amount' => $this->formatMoney($invoice?->total_amount),
                'invoice_currency' => $invoice?->currency,
                'invoice_tax_code' => $invoice?->tax_code,
                'invoice_tax_name' => $invoice?->tax_name,
                'invoice_tax_rate_percentage' => $invoice instanceof BillingInvoice ? number_format((float) $invoice->tax_rate_percentage, 3, '.', '') : null,
                'invoice_taxable_amount' => $this->formatMoney($invoice?->taxable_amount),
                'invoice_tax_amount' => $this->formatMoney($invoice?->tax_amount),
                'invoice_prices_include_tax' => $invoice instanceof BillingInvoice ? ((bool) $invoice->prices_include_tax ? '1' : '0') : null,
                'invoice_issued_at' => $invoice?->issued_at?->utc()->toIso8601String(),
                'invoice_seller_name' => $invoice?->seller_name,
                'invoice_seller_tax_id' => $invoice?->seller_tax_id,
                'invoice_seller_address' => $invoice?->seller_address,
            ]);
        }

        return $rows;
    }

    public function findInvoiceByReservationId(int $reservationId): BillingInvoice
    {
        /** @var BillingInvoice|null $invoice */
        $invoice = BillingInvoice::query()
            ->with([
                'reservation.user:user_id,full_name,email,phone',
                'issuedByUser:user_id,full_name,email',
            ])
            ->where('reservation_id', $reservationId)
            ->first();

        if (! $invoice instanceof BillingInvoice) {
            throw (new ModelNotFoundException())->setModel(BillingInvoice::class, [$reservationId]);
        }

        return $invoice;
    }

    /**
     * @return array<string,mixed>
     */
    private function transformInvoice(BillingInvoice $invoice): array
    {
        return [
            'billing_invoice_id' => (int) $invoice->billing_invoice_id,
            'reservation_id' => (int) $invoice->reservation_id,
            'invoice_number' => (string) $invoice->invoice_number,
            'invoice_status' => (string) $invoice->invoice_status,
            'currency' => (string) ($invoice->currency ?? 'VND'),
            'bill_amounts' => [
                'subtotal_amount' => $this->money($invoice->subtotal_amount),
                'discount_amount' => $this->money($invoice->discount_amount),
                'total_amount' => $this->money($invoice->total_amount),
            ],
            'tax' => [
                'tax_code' => $invoice->tax_code,
                'tax_name' => $invoice->tax_name,
                'tax_rate_percentage' => round((float) ($invoice->tax_rate_percentage ?? 0.0), 3),
                'prices_include_tax' => (bool) $invoice->prices_include_tax,
                'taxable_amount' => $this->money($invoice->taxable_amount),
                'tax_amount' => $this->money($invoice->tax_amount),
            ],
            'seller' => [
                'seller_name' => $invoice->seller_name,
                'seller_tax_id' => $invoice->seller_tax_id,
                'seller_address' => $invoice->seller_address,
            ],
            'issued_at' => $invoice->issued_at?->utc()->toIso8601String(),
            'issued_by' => [
                'user_id' => $invoice->issued_by !== null ? (int) $invoice->issued_by : null,
                'full_name' => $invoice->issuedByUser?->full_name,
                'email' => $invoice->issuedByUser?->email,
            ],
            'row_version' => (int) ($invoice->row_version ?? 1),
            'metadata' => $invoice->metadata_json ?? [],
        ];
    }

    /**
     * @return array{0:float,1:float}
     */
    private function splitInclusiveTax(float $totalAmount, float $taxRatePercentage): array
    {
        $totalAmount = $this->money($totalAmount);
        $taxRatePercentage = round(max(0.0, $taxRatePercentage), 3);

        if ($taxRatePercentage <= 0.0001) {
            return [$totalAmount, 0.0];
        }

        $divisor = 1 + ($taxRatePercentage / 100);
        $taxableAmount = round($totalAmount / $divisor, 2);
        $taxAmount = round($totalAmount - $taxableAmount, 2);

        return [$taxableAmount, $taxAmount];
    }

    private function generateInvoiceNumber(string $prefix, string $reservationCode): string
    {
        $normalizedPrefix = strtoupper(trim($prefix));
        $normalizedCode = strtoupper(trim($reservationCode));
        $normalizedCode = preg_replace('/[^A-Z0-9\-]/', '-', $normalizedCode) ?? $normalizedCode;
        $normalizedCode = trim(preg_replace('/-+/', '-', $normalizedCode) ?? $normalizedCode, '-');

        return $normalizedPrefix . '-' . $normalizedCode;
    }

    private function money(mixed $value): float
    {
        return round(max(0.0, (float) ($value ?? 0.0)), 2);
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
