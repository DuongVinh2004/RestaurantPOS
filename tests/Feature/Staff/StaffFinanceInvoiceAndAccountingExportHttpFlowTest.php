<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffFinanceInvoiceAndAccountingExportHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_can_issue_invoice_from_financial_truth_and_export_accounting_rows(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Le Thu']);

        DB::table('settings')->updateOrInsert(
            ['setting_key' => 'finance.tax_invoice_profile'],
            [
                'value_json' => json_encode([
                    'tax_code' => 'VAT10',
                    'tax_name' => 'VAT 10%',
                    'tax_rate_percentage' => 10,
                    'prices_include_tax' => true,
                    'invoice_prefix' => 'INV',
                    'seller_name' => 'Restaurant POS Test',
                    'seller_tax_id' => '0301234567',
                    'seller_address' => '123 Nguyen Hue',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $staffId,
                'updated_at' => $this->nowUtc(),
            ]
        );

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'checked_in_at' => $this->nowUtc()->copy()->subHours(2),
            'checked_out_at' => $this->nowUtc(),
            'reservation_code' => 'RSV-000001',
            'discount_amount' => '20000.00',
            'final_bill_amount' => '180000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
        ]);
        $reservationBranchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $reservationBranchId,
            'status' => 'Open',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'created_by' => $staffId,
            'transaction_code' => 'DEP-INV-001',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '130000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'FIN-INV-001',
        ]);

        $headers = $this->withIdempotencyKey('idem-staff-finance-invoice-issue-a', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-a'));
        $reservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $issue = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationId . '/issue');

        $issue->assertCreated()
            ->assertJsonPath('meta.action', 'finance_invoice_issued')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-RSV-000001')
            ->assertJsonPath('data.invoice.bill_amounts.subtotal_amount', 200000.0)
            ->assertJsonPath('data.invoice.bill_amounts.discount_amount', 20000.0)
            ->assertJsonPath('data.invoice.bill_amounts.total_amount', 180000.0)
            ->assertJsonPath('data.invoice.tax.tax_rate_percentage', 10.0)
            ->assertJsonPath('data.invoice.tax.tax_amount', 16363.64)
            ->assertJsonPath('data.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.reconciliation.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.reconciliation.payment_summary.net_paid_amount', 180000.0);

        $show = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-invoice-show'))
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationId);

        $show->assertOk()
            ->assertJsonPath('meta.action', 'finance_invoice_show')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-RSV-000001')
            ->assertJsonPath('data.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.reconciliation.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.invoice.seller.seller_tax_id', '0301234567');

        $export = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-accounting-export'))
            ->get('/api/v1/staff/finance/accounting-export?reservation_id=' . $reservationId . '&only_invoiced=1&format=csv');

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('invoice_number', $csv);
        $this->assertStringContainsString('has_bill_outstanding', $csv);
        $this->assertStringContainsString('discrepancy_reasons', $csv);
        $this->assertStringContainsString('INV-RSV-000001', $csv);
        $this->assertStringContainsString('180000.00', $csv);
        $this->assertStringContainsString('16363.64', $csv);
    }

    public function test_staff_cannot_issue_invoice_for_unbilled_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'final_bill_amount' => null,
            'billed_at' => null,
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('idem-staff-finance-invoice-issue-b', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-b')))
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationId . '/issue');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_staff_cannot_issue_invoice_when_reservation_is_not_fully_settled(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);

        DB::table('settings')->updateOrInsert(
            ['setting_key' => 'finance.tax_invoice_profile'],
            [
                'value_json' => json_encode([
                    'tax_code' => 'VAT10',
                    'tax_name' => 'VAT 10%',
                    'tax_rate_percentage' => 10,
                    'prices_include_tax' => true,
                    'invoice_prefix' => 'INV',
                    'seller_name' => 'Restaurant POS Test',
                    'seller_tax_id' => '0301234567',
                    'seller_address' => '123 Nguyen Hue',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $staffId,
                'updated_at' => $this->nowUtc(),
            ]
        );

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'reservation_code' => 'RSV-UNSETTLED-INV',
            'discount_amount' => '20000.00',
            'final_bill_amount' => '180000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
        ]);
        $reservationBranchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $reservationBranchId,
            'status' => 'Open',
        ]);

        $depositPaymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'created_by' => $staffId,
            'transaction_code' => 'DEP-INV-UNSETTLED-1',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '130000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'FIN-INV-UNSETTLED-1',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '5000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'refund_of_payment_id' => $depositPaymentId,
            'created_by' => $staffId,
            'transaction_code' => 'RF-INV-UNSETTLED-1',
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('idem-staff-finance-invoice-unsettled', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-unsettled')))
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationId . '/issue');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_staff_cannot_issue_invoice_without_open_cashier_shift_in_reservation_branch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchA = $this->createBranch(['branch_code' => 'INVCASHA', 'branch_name' => 'Invoice Cashier A']);
        $branchB = $this->createBranch(['branch_code' => 'INVCASHB', 'branch_name' => 'Invoice Cashier B']);

        DB::table('settings')->updateOrInsert(
            ['setting_key' => 'finance.tax_invoice_profile'],
            [
                'value_json' => json_encode([
                    'tax_code' => 'VAT10',
                    'tax_name' => 'VAT 10%',
                    'tax_rate_percentage' => 10,
                    'prices_include_tax' => true,
                    'invoice_prefix' => 'INV',
                    'seller_name' => 'Restaurant POS Test',
                    'seller_tax_id' => '0301234567',
                    'seller_address' => '123 Nguyen Hue',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $staffId,
                'updated_at' => $this->nowUtc(),
            ]
        );

        $reservationId = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'reservation_code' => 'RSV-NO-SHIFT-INV',
            'final_bill_amount' => '100000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);

        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'INV-NO-SHIFT-1',
        ]);

        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchB,
            'status' => 'Open',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('idem-staff-finance-invoice-no-shift', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-no-shift')))
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationId . '/issue?branch_id=' . $branchA);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cashier_shift']);
    }

    public function test_invoice_routes_respect_branch_scope_when_requested(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchA = $this->createBranch(['branch_code' => 'INVA', 'branch_name' => 'Invoice A']);
        $branchB = $this->createBranch(['branch_code' => 'INVB', 'branch_name' => 'Invoice B']);

        DB::table('settings')->updateOrInsert(
            ['setting_key' => 'finance.tax_invoice_profile'],
            [
                'value_json' => json_encode([
                    'tax_code' => 'VAT10',
                    'tax_name' => 'VAT 10%',
                    'tax_rate_percentage' => 10,
                    'prices_include_tax' => true,
                    'invoice_prefix' => 'INV',
                    'seller_name' => 'Restaurant POS Test',
                    'seller_tax_id' => '0301234567',
                    'seller_address' => '123 Nguyen Hue',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $staffId,
                'updated_at' => $this->nowUtc(),
            ]
        );

        $reservationId = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'reservation_code' => 'RSV-BRANCH-INV',
            'final_bill_amount' => '100000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'INV-BRANCH-1',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchA,
            'status' => 'Open',
        ]);

        $headers = $this->withHeaders($this->withIdempotencyKey('idem-staff-finance-invoice-branch', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-branch')));

        $headers
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationId . '/issue?branch_id=' . $branchA)
            ->assertCreated()
            ->assertJsonPath('meta.branch_id', $branchA)
            ->assertJsonPath('data.reservation.reservation_id', $reservationId);

        $headers
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationId . '?branch_id=' . $branchA)
            ->assertOk()
            ->assertJsonPath('meta.branch_id', $branchA);

        $headers
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationId . '?branch_id=' . $branchB)
            ->assertStatus(404);
    }

    public function test_invoice_reads_and_accounting_export_default_to_actor_operational_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchA = $this->createBranch(['branch_code' => 'INVOPS1', 'branch_name' => 'Invoice Ops A']);
        $branchB = $this->createBranch(['branch_code' => 'INVOPS2', 'branch_name' => 'Invoice Ops B']);

        DB::table('settings')->updateOrInsert(
            ['setting_key' => 'finance.tax_invoice_profile'],
            [
                'value_json' => json_encode([
                    'tax_code' => 'VAT10',
                    'tax_name' => 'VAT 10%',
                    'tax_rate_percentage' => 10,
                    'prices_include_tax' => true,
                    'invoice_prefix' => 'INV',
                    'seller_name' => 'Restaurant POS Test',
                    'seller_tax_id' => '0301234567',
                    'seller_address' => '123 Nguyen Hue',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $staffId,
                'updated_at' => $this->nowUtc(),
            ]
        );

        $reservationA = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'reservation_code' => 'RSV-OPS-A',
            'final_bill_amount' => '100000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $reservationB = $this->createReservation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Completed',
            'reservation_code' => 'RSV-OPS-B',
            'final_bill_amount' => '120000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);

        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationA,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'INV-OPS-A-1',
        ]);
        $this->createPayment([
            'branch_id' => $branchB,
            'reservation_id' => $reservationB,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '120000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'INV-OPS-B-1',
        ]);

        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchA,
            'status' => 'Open',
        ]);

        $issueHeadersA = $this->withIdempotencyKey('idem-staff-finance-invoice-ops-a', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-ops'));

        $this->withHeaders($issueHeadersA)
            ->postJson('/api/v1/staff/finance/invoices/' . $reservationA . '/issue')
            ->assertCreated();

        DB::table('billing_invoices')->insert([
            'reservation_id' => $reservationB,
            'invoice_number' => 'INV-RSV-OPS-B',
            'invoice_status' => 'Issued',
            'subtotal_amount' => '120000.00',
            'discount_amount' => '0.00',
            'total_amount' => '120000.00',
            'currency' => 'VND',
            'tax_code' => 'VAT10',
            'tax_name' => 'VAT 10%',
            'tax_rate_percentage' => '10.000',
            'prices_include_tax' => 1,
            'taxable_amount' => '109090.91',
            'tax_amount' => '10909.09',
            'seller_name' => 'Restaurant POS Test',
            'seller_tax_id' => '0301234567',
            'seller_address' => '123 Nguyen Hue',
            'issued_at' => $this->nowUtc(),
            'issued_by' => $staffId,
            'voided_at' => null,
            'voided_by' => null,
            'metadata_json' => null,
            'row_version' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-finance-invoice-ops-read');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationA)
            ->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationA);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationB)
            ->assertStatus(404);

        $export = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/accounting-export?format=json');

        $export->assertOk();
        $reservationIds = collect($export->json('data', []))
            ->pluck('reservation_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $this->assertContains($reservationA, $reservationIds);
        $this->assertNotContains($reservationB, $reservationIds);
    }
}
