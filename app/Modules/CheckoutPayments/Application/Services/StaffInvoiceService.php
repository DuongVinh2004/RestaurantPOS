<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\BillingInvoice;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Services\Finance\FinanceTaxProfileService;
use App\Modules\FloorOps\Application\Services\StaffBranchContextService;
use App\Support\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffInvoiceService
{
    public function __construct(
        private readonly FinanceTaxProfileService $taxProfileService,
        private readonly StaffFinancialReconciliationService $financialReconciliationService,
        private readonly BranchContextService $branchContextService,
        private readonly StaffCashierShiftService $cashierShiftService,
        private readonly StaffBranchContextService $staffBranchContextService,
    ) {}

    /**
     * @return array{invoice:BillingInvoice,created:bool}
     */
    public function issue(int $reservationId, ?int $issuedByUserId = null, ?int $branchId = null): array
    {
        return DB::transaction(function () use ($reservationId, $issuedByUserId, $branchId): array {
            $reservationQuery = Reservation::query()
                ->lockForUpdate();

            if ($branchId !== null) {
                $reservationQuery->where('branch_id', $branchId);
            }

            /** @var Reservation $reservation */
            $reservation = $reservationQuery->findOrFail($reservationId);
            $reservationBranchId = $this->resolveReservationBranchId($reservation, $branchId);

            /** @var BillingInvoice|null $existing */
            $existing = BillingInvoice::query()
                ->where('reservation_id', $reservationId)
                ->when($branchId !== null, static function ($query) use ($branchId): void {
                    $query->whereHas('reservation', static fn ($reservationQuery) => $reservationQuery->where('branch_id', $branchId));
                })
                ->first();

            if ($existing instanceof BillingInvoice) {
                return [
                    'invoice' => $this->findInvoiceByReservationId($reservationId, $reservationBranchId),
                    'created' => false,
                ];
            }

            if ($reservation->billed_at === null || $reservation->final_bill_amount === null) {
                throw ValidationException::withMessages([
                    'reservation' => ['Invoice can only be issued for a reservation with a locked bill total.'],
                ]);
            }

            $reconciliation = $this->financialReconciliationService->show($reservationId, $reservationBranchId);
            $this->assertIssuableReservationFinancialTruth($reconciliation);
            $this->assertOpenCashierShiftForBranch($issuedByUserId, $reservationBranchId);

            $profile = $this->taxProfileService->effectiveProfile();
            if (! (bool) ($profile['prices_include_tax'] ?? true)) {
                throw ValidationException::withMessages([
                    'tax_profile' => ['Current financial truth only supports tax-inclusive invoice issuance.'],
                ]);
            }

            $totalAmountMinor = Money::minorUnits($reservation->final_bill_amount, true);
            $discountAmountMinor = Money::minorUnits($reservation->discount_amount ?? 0, true);
            $subtotalAmountMinor = $totalAmountMinor + $discountAmountMinor;
            $totalAmount = Money::minorToFloat($totalAmountMinor);
            $discountAmount = Money::minorToFloat($discountAmountMinor);
            $currency = trim((string) ($reservation->bill_currency ?? 'VND')) ?: 'VND';
            $rate = round((float) ($profile['tax_rate_percentage'] ?? 0.0), 3);
            [$taxableAmount, $taxAmount] = $this->splitInclusiveTax($totalAmount, $rate);

            $invoice = new BillingInvoice;
            $invoice->fill([
                'reservation_id' => (int) $reservation->reservation_id,
                'invoice_number' => $this->generateInvoiceNumber((string) $profile['invoice_prefix'], (string) $reservation->reservation_code),
                'invoice_status' => 'Issued',
                'subtotal_amount' => Money::formatMinor($subtotalAmountMinor),
                'discount_amount' => Money::formatMinor($discountAmountMinor),
                'total_amount' => Money::formatMinor($totalAmountMinor),
                'currency' => $currency,
                'tax_code' => $profile['tax_code'],
                'tax_name' => $profile['tax_name'],
                'tax_rate_percentage' => number_format($rate, 3, '.', ''),
                'prices_include_tax' => true,
                'taxable_amount' => Money::format($taxableAmount, true),
                'tax_amount' => Money::format($taxAmount, true),
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
                'invoice' => $this->findInvoiceByReservationId($reservationId, $reservationBranchId),
                'created' => true,
            ];
        }, 3);
    }

    /**
     * @return array<string,mixed>
     */
    public function show(int $reservationId, ?int $branchId = null, ?int $staffActorUserId = null): array
    {
        $branchScope = $this->resolveAccessibleBranchScope($staffActorUserId, $branchId);
        $invoice = $this->findInvoiceByReservationId($reservationId, $branchScope);
        $reconciliation = $this->financialReconciliationService->show($reservationId, $branchId, $staffActorUserId);

        return [
            'invoice' => $this->transformInvoice($invoice),
            'reservation' => $reconciliation['reservation'],
            'reconciliation' => $reconciliation['summary'],
            'method_breakdown' => $reconciliation['method_breakdown'],
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters = [], ?int $staffActorUserId = null): array
    {
        $reconciliationRows = $this->financialReconciliationService->exportRows($filters, $staffActorUserId);
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

    /**
     * @param  list<int>|int|null  $branchScope
     */
    public function findInvoiceByReservationId(int $reservationId, array|int|null $branchScope = null): BillingInvoice
    {
        $normalizedBranchScope = $this->normalizeBranchScope($branchScope);

        /** @var BillingInvoice|null $invoice */
        $invoice = BillingInvoice::query()
            ->with([
                'reservation.user:user_id,full_name,email,phone',
                'issuedByUser:user_id,full_name,email',
            ])
            ->where('reservation_id', $reservationId)
            ->when($normalizedBranchScope !== [], static function ($query) use ($normalizedBranchScope): void {
                $query->whereHas('reservation', static fn ($reservationQuery) => $reservationQuery->whereIn('branch_id', $normalizedBranchScope));
            }, static function ($query): void {
                $query->whereRaw('1 = 0');
            })
            ->first();

        if (! $invoice instanceof BillingInvoice) {
            throw (new ModelNotFoundException)->setModel(BillingInvoice::class, [$reservationId]);
        }

        return $invoice;
    }

    /**
     * @return list<int>
     */
    private function resolveAccessibleBranchScope(?int $staffActorUserId = null, ?int $requestedBranchId = null): array
    {
        return $this->staffBranchContextService->branchScopeOrAccessible($staffActorUserId, $requestedBranchId);
    }

    /**
     * @param  list<int>|int|null  $branchScope
     * @return list<int>
     */
    private function normalizeBranchScope(array|int|null $branchScope): array
    {
        if (is_int($branchScope)) {
            return [$branchScope];
        }

        if (! is_array($branchScope)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_filter($branchScope, static fn ($value): bool => $value !== null && $value !== ''),
        )));
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
        $totalMinor = Money::minorUnits($totalAmount, true);
        $rateUnits = max(0, (int) round($taxRatePercentage * 1000));

        if ($rateUnits <= 0) {
            return [Money::minorToFloat($totalMinor), 0.0];
        }

        $denominator = 100000 + $rateUnits;
        $taxableMinor = intdiv(($totalMinor * 100000) + intdiv($denominator, 2), $denominator);
        $taxAmountMinor = max(0, $totalMinor - $taxableMinor);

        return [Money::minorToFloat($taxableMinor), Money::minorToFloat($taxAmountMinor)];
    }

    private function generateInvoiceNumber(string $prefix, string $reservationCode): string
    {
        $normalizedPrefix = strtoupper(trim($prefix));
        $normalizedCode = strtoupper(trim($reservationCode));
        $normalizedCode = preg_replace('/[^A-Z0-9\-]/', '-', $normalizedCode) ?? $normalizedCode;
        $normalizedCode = trim(preg_replace('/-+/', '-', $normalizedCode) ?? $normalizedCode, '-');

        return $normalizedPrefix.'-'.$normalizedCode;
    }

    private function money(mixed $value): float
    {
        return Money::toFloat($value ?? 0, true);
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Money::format($value, true);
    }

    /**
     * @param  array<string,mixed>  $reconciliation
     */
    private function assertIssuableReservationFinancialTruth(array $reconciliation): void
    {
        $summary = (array) ($reconciliation['summary'] ?? []);
        $flags = (array) ($summary['flags'] ?? []);
        $finalBillAmount = $this->money(data_get($summary, 'reconciliation.final_bill_amount'));

        if (
            Money::minorUnits($finalBillAmount, true) <= 0
            || ! (bool) ($flags['is_fully_settled'] ?? false)
            || (bool) ($flags['has_discrepancy'] ?? false)
            || (bool) ($flags['has_bill_outstanding'] ?? false)
            || (bool) ($flags['has_bill_overpaid'] ?? false)
            || (bool) ($flags['has_over_refund'] ?? false)
            || (bool) ($flags['has_mixed_payment_currencies'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'reservation' => ['Invoice can only be issued after the reservation is fully settled with no outstanding or reconciliation discrepancy.'],
            ]);
        }
    }

    private function resolveReservationBranchId(Reservation $reservation, ?int $branchId = null): int
    {
        if ($reservation->branch_id !== null && $reservation->branch_id !== '') {
            return $this->branchContextService->resolveBranchId($reservation->branch_id, false);
        }

        return $this->branchContextService->resolveBranchId($branchId, false);
    }

    private function assertOpenCashierShiftForBranch(?int $staffUserId, int $branchId): void
    {
        if ($staffUserId === null || $staffUserId <= 0) {
            return;
        }

        if ($this->cashierShiftService->currentOpenShift($staffUserId, $branchId) !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'cashier_shift' => ['Open a cashier shift for this branch before issuing invoices.'],
        ]);
    }
}
