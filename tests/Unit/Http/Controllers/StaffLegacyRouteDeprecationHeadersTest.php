<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\Staff\StaffTableBoardController;
use App\Http\Requests\Staff\TableBoardRequest;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Services\Staff\StaffTableBoardService;
use Mockery;
use Tests\TestCase;

class StaffLegacyRouteDeprecationHeadersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_table_board_legacy_alias_adds_deprecation_headers(): void
    {
        $service = Mockery::mock(StaffTableBoardService::class);
        $service->shouldReceive('buildBoardSnapshot')->once()->andReturn([
            'data' => [],
            'zones' => [],
            'summary' => [],
            'unassigned_reservations' => [],
            'orchestration' => [],
        ]);
        $realtime = Mockery::mock(StaffOperationalRealtimeService::class);
        $realtime->shouldReceive('describeTopic')->once()->andReturn([]);

        $controller = new StaffTableBoardController($service, $realtime);
        $request = TableBoardRequest::create('/api/v1/staff/table-board', 'GET', [
            'from' => '2026-04-01 00:00:00',
            'to' => '2026-04-01 23:59:59',
        ]);
        $request->setRouteResolver(static function () {
            return new class
            {
                public function uri(): string
                {
                    return 'api/v1/staff/table-board';
                }
            };
        });

        $response = $controller->legacyIndex($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->headers->get('Deprecation'));
        self::assertSame('Wed, 01 Jul 2026 00:00:00 GMT', $response->headers->get('Sunset'));
        self::assertStringContainsString('/api/v1/staff/tables/board', (string) $response->headers->get('Link'));
        self::assertSame('/api/v1/staff/table-board', $response->getData(true)['meta']['request_route']);
        self::assertSame('/api/v1/staff/tables/board', $response->getData(true)['meta']['canonical_route']);
    }
}
