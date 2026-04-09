<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

class DatabaseWriteConflictMapperTest extends TestCase
{
    public function test_it_maps_menu_item_price_overlap_trigger_errors(): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException(
            $this->makeQueryException('menu_item_prices overlap conflict for same item', '45000')
        );

        $this->assertNotNull($mapped);
        $this->assertSame(
            'The effective price window overlaps another price for this menu item.',
            $mapped->errors()['effective_from'][0] ?? null
        );
    }

    public function test_it_maps_active_voucher_unique_index_errors(): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException(
            $this->makeQueryException("Duplicate entry '42' for key 'uq_reservations__active_applied_user_voucher_id'", '23000')
        );

        $this->assertNotNull($mapped);
        $this->assertSame(
            'This voucher is already applied to another active reservation. Reload data and try again.',
            $mapped->errors()['voucher'][0] ?? null
        );
    }

    public function test_it_maps_duplicate_provider_transaction_reference_errors(): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException(
            $this->makeQueryException("Duplicate entry 'BankX-REF-001' for key 'uq_payments__payment_provider__transaction_code'", '23000')
        );

        $this->assertNotNull($mapped);
        $this->assertSame(
            'Mã giao dịch này đã tồn tại cho payment provider hiện tại. Vui lòng kiểm tra lại đối soát hoặc dùng mã khác.',
            $mapped->errors()['transaction_code'][0] ?? null
        );
    }

    public function test_it_maps_duplicate_staff_api_key_hash_errors(): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException(
            $this->makeQueryException("Duplicate entry 'abc' for key 'uq_staff_api_keys__key_hash'", '23000')
        );

        $this->assertNotNull($mapped);
        $this->assertSame(
            'This staff API key already exists. Use a different key or revoke the old key before creating a new one.',
            $mapped->errors()['api_key'][0] ?? null
        );
    }

    private function makeQueryException(string $driverMessage, string $sqlState): QueryException
    {
        $previous = new PDOException($driverMessage);
        $previous->errorInfo = [$sqlState, 0, $driverMessage];

        return new QueryException('mysql', 'select 1', [], $previous);
    }
}
