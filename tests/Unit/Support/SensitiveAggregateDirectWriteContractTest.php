<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SensitiveAggregateDirectWriteContractTest extends TestCase
{
    private const SENSITIVE_TABLES = [
        'reservations',
        'reservation_orders',
        'reservation_order_items',
        'payments',
        'waiting_list',
        'table_holds',
        'restaurant_tables',
    ];

    /**
     * @var array<string,string>
     */
    private const SENSITIVE_MODELS = [
        'Reservation' => 'reservations',
        'ReservationOrder' => 'reservation_orders',
        'ReservationOrderItem' => 'reservation_order_items',
        'Payment' => 'payments',
        'WaitingList' => 'waiting_list',
        'TableHold' => 'table_holds',
        'RestaurantTable' => 'restaurant_tables',
    ];

    /**
     * @var array<string,array<string,int>>
     */
    private const ALLOWED_DIRECT_WRITE_INVENTORY = [
        'app/Modules/BranchScheduling/Application/Services/TableHoldService.php' => [
            'db:table_holds:update' => 1,
        ],
        'app/Platform/Uat/UatScenarioPackService.php' => [
            'db:payments:delete' => 1,
            'db:payments:update' => 1,
            'db:reservation_order_items:delete' => 2,
            'db:reservation_order_items:insert' => 1,
            'db:reservation_orders:delete' => 1,
            'db:reservation_orders:update' => 1,
            'db:reservations:delete' => 1,
            'db:reservations:update' => 1,
            'db:restaurant_tables:delete' => 1,
            'db:table_holds:delete' => 1,
            'db:waiting_list:delete' => 1,
        ],
    ];

    public function test_sensitive_aggregate_direct_writes_are_explicitly_allowlisted(): void
    {
        self::assertSame(self::ALLOWED_DIRECT_WRITE_INVENTORY, $this->directWriteInventory());
    }

    /**
     * @return array<string,array<string,int>>
     */
    private function directWriteInventory(): array
    {
        $inventory = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) File::get($file->getRealPath());
            $normalized = preg_replace('/\s+/', '', $source) ?? $source;
            $relativePath = str_replace('\\', '/', ltrim(str_replace(base_path(), '', $file->getRealPath()), '\\/'));

            foreach (self::SENSITIVE_TABLES as $table) {
                $pattern = sprintf(
                    "/DB::table\\('%s'\\)(?:(?!->first\\(|->get\\(|;).)*->(insert|update|delete)\\(/",
                    preg_quote($table, '/')
                );

                if (! preg_match_all($pattern, $normalized, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $operation) {
                    $key = sprintf('db:%s:%s', $table, $operation);
                    $inventory[$relativePath][$key] = ($inventory[$relativePath][$key] ?? 0) + 1;
                }
            }

            foreach (self::SENSITIVE_MODELS as $model => $table) {
                $pattern = sprintf(
                    '/%s::query\\(\\)(?:(?!->first\\(|->get\\(|->each\\(|;).)*->(update|delete)\\(/',
                    preg_quote($model, '/')
                );

                if (! preg_match_all($pattern, $normalized, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $operation) {
                    $key = sprintf('model:%s:%s', $table, $operation);
                    $inventory[$relativePath][$key] = ($inventory[$relativePath][$key] ?? 0) + 1;
                }
            }
        }

        ksort($inventory);

        foreach ($inventory as &$counts) {
            ksort($counts);
        }
        unset($counts);

        return $inventory;
    }
}
