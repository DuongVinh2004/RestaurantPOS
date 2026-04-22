<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class StaffCheckoutServiceDuplicateConstraintTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_maps_current_provider_transaction_unique_constraint_name_to_validation_exception(): void
    {
        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'throwIfDuplicatePaymentConstraint');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $this->makeQueryException(
                "Duplicate entry 'BankX-REF-001' for key 'uq_payments__payment_provider__transaction_code'",
                '23000'
            ));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Transaction code already exists for this payment provider. Check reconciliation or use a different code.',
                $e->errors()['transaction_code'][0] ?? null
            );
        }
    }

    public function test_it_maps_duplicate_payment_idempotency_constraint_to_validation_exception(): void
    {
        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'throwIfDuplicatePaymentConstraint');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $this->makeQueryException(
                "Duplicate entry 'idem-123' for key 'uq_payments__idempotency_key'",
                '23000'
            ));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'idempotency key already used.',
                $e->errors()['idempotency_key'][0] ?? null
            );
        }
    }

    public function test_it_maps_sqlite_payment_provider_transaction_unique_constraint_to_validation_exception(): void
    {
        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'throwIfDuplicatePaymentConstraint');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $this->makeQueryException(
                'UNIQUE constraint failed: payments.payment_provider, payments.transaction_code',
                '23000',
                'sqlite'
            ));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Transaction code already exists for this payment provider. Check reconciliation or use a different code.',
                $e->errors()['transaction_code'][0] ?? null
            );
        }
    }

    public function test_it_detects_sqlite_payment_idempotency_constraint_for_replay_handling(): void
    {
        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'isDuplicatePaymentIdempotencyConstraint');
        $method->setAccessible(true);

        self::assertTrue($method->invoke(
            $service,
            $this->makeQueryException(
                'UNIQUE constraint failed: payments.idempotency_key',
                '23000',
                'sqlite'
            )
        ));
    }

    private function makeService(): OrderSettlementWorkflow
    {
        return new OrderSettlementWorkflow(
            Mockery::mock(ReservationLockService::class),
            Mockery::mock(NotificationOutboxService::class),
            Mockery::mock(LoyaltyPointsService::class),
            Mockery::mock(RestaurantTableStateService::class),
            Mockery::mock(ReservationFinancialSyncService::class),
        );
    }

    private function makeQueryException(string $driverMessage, string $sqlState, string $connectionName = 'mysql'): QueryException
    {
        $previous = new PDOException($driverMessage);
        $previous->errorInfo = [$sqlState, 0, $driverMessage];

        return new QueryException($connectionName, 'insert into payments values (...)', [], $previous);
    }
}
