<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Platform\Delivery\Release\Application\Verifiers\PortableSqlSanitizer;
use PHPUnit\Framework\TestCase;

final class PortableSqlSanitizerTest extends TestCase
{
    public function test_it_removes_definer_clauses_from_mysql_dump_fragments(): void
    {
        $input = <<<'SQL'
/*!50017 DEFINER=`root`@`localhost`*/
CREATE DEFINER=`root`@`localhost` VIEW `v_demo` AS SELECT 1;
ALTER DEFINER=`root`@`localhost` EVENT `e_demo` ON SCHEDULE EVERY 1 DAY DO SELECT 1;
SQL;

        $result = PortableSqlSanitizer::sanitize($input);

        self::assertStringNotContainsString('DEFINER=', $result['sql']);
        self::assertStringContainsString('CREATE VIEW `v_demo` AS SELECT 1;', $result['sql']);
        self::assertStringContainsString('ALTER EVENT `e_demo` ON SCHEDULE EVERY 1 DAY DO SELECT 1;', $result['sql']);
        self::assertTrue($result['changed']);
    }

    public function test_it_reports_no_change_for_already_portable_sql(): void
    {
        $input = 'CREATE TABLE demo (id INT PRIMARY KEY);';

        $result = PortableSqlSanitizer::sanitize($input);

        self::assertSame($input, $result['sql']);
        self::assertFalse($result['changed']);
    }
}
