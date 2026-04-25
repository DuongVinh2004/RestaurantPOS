<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class StaffReservationDepositServiceDuplicateConstraintTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_transaction_code_constraint_fallback_uses_canonical_provider_message(): void
    {
        $service = new StaffReservationDepositService(
            Mockery::mock(ReservationLockService::class),
            Mockery::mock(ReservationFinancialSyncService::class),
        );
        $method = new ReflectionMethod($service, 'throwIfDuplicatePaymentConstraint');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $this->makeQueryException(
                "Duplicate entry 'DEP-001' for key 'uq_payments__transaction_code'",
                '23000',
            ));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Transaction code already exists for this payment provider. Check reconciliation or use a different code.',
                $e->errors()['transaction_code'][0] ?? null,
            );
        }
    }

    private function makeQueryException(string $driverMessage, string $sqlState): QueryException
    {
        $previous = new PDOException($driverMessage);
        $previous->errorInfo = [$sqlState, 0, $driverMessage];

        return new QueryException('mysql', 'insert into payments values (...)', [], $previous);
    }
}
