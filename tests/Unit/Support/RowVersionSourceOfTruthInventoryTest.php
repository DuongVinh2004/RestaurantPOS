<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RowVersionSourceOfTruthInventoryTest extends TestCase
{
    private const EXPECTED_ROW_VERSION_TABLES = [
        'cashier_shifts',
        'ingredients',
        'kitchen_order_item_tickets',
        'loyalty_tiers',
        'menu_item_recipes',
        'payments',
        'purchase_orders',
        'reservation_order_items',
        'reservation_orders',
        'reservations',
        'restaurant_tables',
        'suppliers',
        'table_holds',
        'user_points',
        'user_vouchers',
        'users',
        'vouchers',
        'waiting_list',
    ];

    public function test_mysql_schema_declares_expected_row_version_tables_and_triggers(): void
    {
        $schemaPath = base_path('database/schema/mysql-schema.sql');

        self::assertFileExists($schemaPath, 'mysql-schema.sql not found.');

        $schema = (string) File::get($schemaPath);
        self::assertNotSame('', trim($schema), 'mysql-schema.sql is empty.');

        $beforeInsertTables = $this->extractTriggeredTables($schema, 'INSERT');
        $beforeUpdateTables = $this->extractTriggeredTables($schema, 'UPDATE');

        $expected = self::EXPECTED_ROW_VERSION_TABLES;
        sort($expected);
        sort($beforeInsertTables);
        sort($beforeUpdateTables);

        self::assertSame(
            $expected,
            $beforeInsertTables,
            'Row-version BEFORE INSERT trigger inventory drifted from mysql-schema.sql.'
        );

        self::assertSame(
            $expected,
            $beforeUpdateTables,
            'Row-version BEFORE UPDATE trigger inventory drifted from mysql-schema.sql.'
        );

        self::assertSame(
            $beforeInsertTables,
            $beforeUpdateTables,
            'Row-version BEFORE INSERT/UPDATE trigger inventories are inconsistent.'
        );

        foreach (self::EXPECTED_ROW_VERSION_TABLES as $table) {
            self::assertMatchesRegularExpression(
                sprintf(
                    '/TRIGGER `[^`]*%s__bi_row_version` BEFORE INSERT ON `%s`/s',
                    preg_quote($table, '/'),
                    preg_quote($table, '/')
                ),
                $schema,
                sprintf('Missing BEFORE INSERT row-version trigger for table [%s].', $table)
            );

            self::assertMatchesRegularExpression(
                sprintf(
                    '/TRIGGER `[^`]*%s__bu_row_version` BEFORE UPDATE ON `%s`/s',
                    preg_quote($table, '/'),
                    preg_quote($table, '/')
                ),
                $schema,
                sprintf('Missing BEFORE UPDATE row-version trigger for table [%s].', $table)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function extractTriggeredTables(string $schema, string $operation): array
    {
        $operation = strtoupper($operation);

        preg_match_all(
            sprintf(
                '/TRIGGER `[^`]*__b%s_row_version` BEFORE %s ON `([^`]+)`/s',
                $operation === 'INSERT' ? 'i' : 'u',
                preg_quote($operation, '/')
            ),
            $schema,
            $matches
        );

        $tables = array_values(array_unique($matches[1] ?? []));
        sort($tables);

        return $tables;
    }
}
