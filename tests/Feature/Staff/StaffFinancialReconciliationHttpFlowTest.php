<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffFinancialReconciliationHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
    }

    public function test_staff_can_list_reconciliation_rows_with_refund_lineage_backed_totals(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Nguyen A']);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '40000.00',
            'deposit_status' => 'PartiallyRefunded',
            'final_bill_amount' => '200000.00',
            'bill_currency' => 'VND',
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
            'transaction_code' => 'DEP-RECON-001',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '150000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'FIN-RECON-001',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '10000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'refund_of_payment_id' => $depositPaymentId,
            'created_by' => $staffId,
            'transaction_code' => 'RF-RECON-001',
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-recon-list'))
            ->getJson('/api/v1/staff/finance/reconciliation?reservation_id='.$reservationId);
        $reservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $response->assertOk()
            ->assertJsonPath('meta.action', 'financial_reconciliation_index')
            ->assertJsonPath('data.0.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.0.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.0.payment_summary.payment_count', 3)
            ->assertJsonPath('data.0.payment_summary.refund_count', 1)
            ->assertJsonPath('data.0.payment_summary.deposit_captured_amount', 50000.0)
            ->assertJsonPath('data.0.payment_summary.deposit_refunded_amount', 10000.0)
            ->assertJsonPath('data.0.payment_summary.deposit_net_amount', 40000.0)
            ->assertJsonPath('data.0.payment_summary.final_net_amount', 150000.0)
            ->assertJsonPath('data.0.payment_summary.net_paid_amount', 190000.0)
            ->assertJsonPath('data.0.reconciliation.deposit_sync_gap_amount', 0.0)
            ->assertJsonPath('data.0.reconciliation.bill_outstanding_amount', 10000.0)
            ->assertJsonPath('data.0.flags.has_discrepancy', false);
    }

    public function test_staff_can_show_reconciliation_detail_and_export_csv_and_filter_discrepancies(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Tran B']);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'final_bill_amount' => '150000.00',
            'bill_currency' => 'VND',
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
            'transaction_code' => 'DEP-RECON-002',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '10000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'refund_of_payment_id' => $depositPaymentId,
            'created_by' => $staffId,
            'transaction_code' => 'RF-RECON-002',
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        $show = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-recon-show'))
            ->getJson('/api/v1/staff/finance/reconciliation/'.$reservationId);
        $reservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $show->assertOk()
            ->assertJsonPath('meta.action', 'financial_reconciliation_show')
            ->assertJsonPath('data.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.summary.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.summary.flags.has_discrepancy', true)
            ->assertJsonPath('data.summary.reconciliation.deposit_sync_gap_amount', -10000.0)
            ->assertJsonPath('data.payments.1.refund_target_payment_type', 'Deposit')
            ->assertJsonPath('data.payments.1.refund_source_payment.payment_id', $depositPaymentId);

        $filter = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-recon-filter'))
            ->getJson('/api/v1/staff/finance/reconciliation?has_discrepancy=1');

        $filter->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.0.flags.has_discrepancy', true);

        $export = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-recon-export'))
            ->get('/api/v1/staff/finance/reconciliation/export?reservation_id='.$reservationId.'&format=csv');

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('reservation_id,reservation_code,reservation_status', $csv);
        $this->assertStringContainsString('has_bill_outstanding', $csv);
        $this->assertStringContainsString('discrepancy_reasons', $csv);
        $this->assertStringContainsString((string) $reservationId, $csv);
        $this->assertStringContainsString('-10000.00', $csv);
        $this->assertStringContainsString('deposit_sync_gap,bill_outstanding', $csv);
    }

    public function test_reconciliation_routes_can_be_scoped_to_branch_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Branch Finance']);
        $branchA = $this->createBranch(['branch_code' => 'FINA', 'branch_name' => 'Finance A']);
        $branchB = $this->createBranch(['branch_code' => 'FINB', 'branch_name' => 'Finance B']);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchA]);

        $reservationA = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'final_bill_amount' => '90000.00',
            'bill_currency' => 'VND',
        ]);
        $reservationB = $this->createReservation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'final_bill_amount' => '110000.00',
            'bill_currency' => 'VND',
        ]);

        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationA,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '90000.00',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'FIN-BRANCH-A',
        ]);
        $this->createPayment([
            'branch_id' => $branchB,
            'reservation_id' => $reservationB,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '110000.00',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'FIN-BRANCH-B',
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-finance-recon-branch');

        $defaultScoped = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation');

        $defaultScoped->assertOk();
        $reservationIds = collect($defaultScoped->json('data', []))
            ->pluck('reservation.reservation_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $this->assertContains($reservationA, $reservationIds);
        $this->assertNotContains($reservationB, $reservationIds);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?branch_id='.$branchA)
            ->assertOk()
            ->assertJsonPath('meta.filters.branch_id', $branchA)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reservation.reservation_id', $reservationA);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?branch_id='.$branchB)
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation/'.$reservationA.'?branch_id='.$branchA)
            ->assertOk()
            ->assertJsonPath('meta.branch_id', $branchA)
            ->assertJsonPath('data.reservation.reservation_id', $reservationA);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation/'.$reservationB)
            ->assertStatus(404);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation/'.$reservationB.'?branch_id='.$branchA)
            ->assertStatus(404);
    }

    public function test_reconciliation_show_returns_stable_no_data_contract_for_reservation_without_payments(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Le C']);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'final_bill_amount' => null,
            'bill_currency' => 'VND',
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-finance-recon-no-data'))
            ->getJson('/api/v1/staff/finance/reconciliation/'.$reservationId);
        $reservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $response->assertOk()
            ->assertJsonPath('meta.action', 'financial_reconciliation_show')
            ->assertJsonPath('data.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.summary.reservation.row_version', $reservationRowVersion)
            ->assertJsonPath('data.summary.payment_summary.payment_count', 0)
            ->assertJsonPath('data.summary.payment_summary.refund_count', 0)
            ->assertJsonPath('data.summary.payment_summary.net_paid_amount', 0.0)
            ->assertJsonPath('data.summary.reconciliation.deposit_required_amount', 50000.0)
            ->assertJsonPath('data.summary.reconciliation.deposit_recorded_paid_amount', 0.0)
            ->assertJsonPath('data.summary.reconciliation.deposit_computed_net_amount', 0.0)
            ->assertJsonPath('data.summary.reconciliation.bill_outstanding_amount', null)
            ->assertJsonPath('data.summary.flags.has_payments', false)
            ->assertJsonPath('data.summary.flags.has_discrepancy', false)
            ->assertJsonCount(0, 'data.payments')
            ->assertJsonCount(0, 'data.method_breakdown');
    }

    public function test_reconciliation_routes_require_staff_authentication(): void
    {
        $this->getJson('/api/v1/staff/finance/reconciliation')
            ->assertStatus(401);
    }
}
