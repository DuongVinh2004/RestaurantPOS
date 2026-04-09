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

        $depositPaymentId = $this->createPayment([
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
            'transaction_code' => 'RF-INV-001',
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        $headers = $this->withIdempotencyKey('idem-staff-finance-invoice-issue-a', $this->staffAuthHeaders($staffId, 'staff-finance-invoice-a'));

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
            ->assertJsonPath('data.reconciliation.payment_summary.net_paid_amount', 175000.0);

        $show = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-invoice-show'))
            ->getJson('/api/v1/staff/finance/invoices/' . $reservationId);

        $show->assertOk()
            ->assertJsonPath('meta.action', 'finance_invoice_show')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-RSV-000001')
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
        $this->assertStringContainsString('175000.00', $csv);
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
}
