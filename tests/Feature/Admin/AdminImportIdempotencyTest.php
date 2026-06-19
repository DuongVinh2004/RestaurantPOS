<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\PrivacyCompliance\Domain\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class AdminImportIdempotencyTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00', 'UTC'));
        Cache::store('redis')->flush();
    }

    #[DataProvider('adminImportDomains')]
    public function test_admin_import_requires_idempotency_key(string $domain): void
    {
        [, $headers] = $this->adminHeaders('admin-import-missing-'.$domain);
        $case = $this->importCase($domain);

        $response = $this->withHeaders($headers)
            ->postJson($case['uri'], $case['payload']);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required')
            ->assertJsonPath('error_code', 'idempotency_key_required')
            ->assertJsonPath('category_code', 'validation_error')
            ->assertJsonPath('state_reason', 'missing_idempotency_key');
    }

    #[DataProvider('adminImportDomains')]
    public function test_admin_import_same_key_same_payload_replays(string $domain): void
    {
        [, $headers] = $this->adminHeaders('admin-import-replay-'.$domain);
        $headers = $this->withIdempotencyKey($headers, 'admin-import-replay-'.$domain);
        $case = $this->importCase($domain);

        $first = $this->withHeaders($headers)->postJson($case['uri'], $case['payload']);
        $second = $this->withHeaders($headers)->postJson($case['uri'], $case['payload']);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.commit.batch_id'), $second->json('data.commit.batch_id'));
        self::assertSame($first->json('data.commit.created'), $second->json('data.commit.created'));
    }

    #[DataProvider('adminImportDomains')]
    public function test_admin_import_same_key_different_payload_conflicts(string $domain): void
    {
        [, $headers] = $this->adminHeaders('admin-import-conflict-'.$domain);
        $headers = $this->withIdempotencyKey($headers, 'admin-import-conflict-'.$domain);
        $case = $this->importCase($domain);

        $this->withHeaders($headers)
            ->postJson($case['uri'], $case['payload'])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->withHeaders($headers)
            ->postJson($case['uri'], $case['changed_payload'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_conflict')
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('category_code', 'idempotency_conflict')
            ->assertJsonPath('conflict_type', 'idempotency_payload_mismatch')
            ->assertJsonPath('replay_state', 'payload_mismatch')
            ->assertJsonPath('state_reason', 'key_reused_for_different_payload');
    }

    #[DataProvider('adminImportDomains')]
    public function test_admin_import_retry_does_not_create_duplicate_batch(string $domain): void
    {
        [, $headers] = $this->adminHeaders('admin-import-duplicate-batch-'.$domain);
        $headers = $this->withIdempotencyKey($headers, 'admin-import-duplicate-batch-'.$domain);
        $case = $this->importCase($domain);

        $before = AuditLog::query()
            ->where('action', 'master_data.import.committed')
            ->count();

        $first = $this->withHeaders($headers)->postJson($case['uri'], $case['payload']);
        $second = $this->withHeaders($headers)->postJson($case['uri'], $case['payload']);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.commit.batch_id'), $second->json('data.commit.batch_id'));
        self::assertSame(
            $before + 1,
            AuditLog::query()->where('action', 'master_data.import.committed')->count()
        );
    }

    #[DataProvider('adminImportDomains')]
    public function test_admin_import_dry_run_does_not_require_idempotency_key(string $domain): void
    {
        [, $headers] = $this->adminHeaders('admin-import-dry-run-'.$domain);
        $case = $this->importCase($domain);
        $payload = $case['payload'];
        $payload['mode'] = 'dry_run';

        $response = $this->withHeaders($headers)
            ->postJson($case['uri'], $payload);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'admin_master_data_import_dry_run');

        self::assertNull($response->headers->get('Idempotency-Replayed'));
    }

    public function test_admin_import_routes_have_expected_idempotency_scopes(): void
    {
        foreach ($this->expectedImportRouteScopes() as $uri => $scope) {
            $route = $this->findRoute('POST', $uri);

            self::assertNotNull($route, sprintf('Expected import route [%s] is not registered.', $uri));

            $matches = collect($route->gatherMiddleware())
                ->contains(static fn (string $middleware): bool => str_contains($middleware, $scope)
                    && str_contains($middleware, 'mode,commit'));

            self::assertTrue(
                $matches,
                sprintf('Expected route [%s] to use idempotency scope [%s] for mode=commit.', $uri, $scope)
            );
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function adminImportDomains(): array
    {
        return [
            'branches' => ['branches'],
            'restaurant tables' => ['restaurant-tables'],
            'menu categories' => ['menu-categories'],
            'menu items' => ['menu-items'],
            'menu prices' => ['menu-prices'],
            'vouchers' => ['vouchers'],
            'loyalty tiers' => ['loyalty-tiers'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function expectedImportRouteScopes(): array
    {
        return [
            'v1/admin/settings/branches/import' => 'admin.branches.import',
            'v1/admin/restaurant/tables/import' => 'admin.tables.import',
            'v1/admin/menu/categories/import' => 'admin.menu-categories.import',
            'v1/admin/menu/items/import' => 'admin.menu-items.import',
            'v1/admin/menu/prices/import' => 'admin.prices.import',
            'v1/admin/benefits/vouchers/import' => 'admin.master-data.import',
            'v1/admin/benefits/loyalty-tiers/import' => 'admin.master-data.import',
        ];
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function importCase(string $domain): array
    {
        $suffix = Str::upper(Str::random(8));

        return match ($domain) {
            'branches' => $this->branchImportCase($suffix),
            'restaurant-tables' => $this->tableImportCase($suffix),
            'menu-categories' => $this->menuCategoryImportCase($suffix),
            'menu-items' => $this->menuItemImportCase($suffix),
            'menu-prices' => $this->menuPriceImportCase($suffix),
            'vouchers' => $this->voucherImportCase($suffix),
            'loyalty-tiers' => $this->loyaltyTierImportCase($suffix),
            default => throw new \InvalidArgumentException('Unknown admin import domain ['.$domain.'].'),
        };
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function branchImportCase(string $suffix): array
    {
        return $this->makeCase('/api/v1/admin/settings/branches/import', [
            [
                'branch_code' => 'IDEM-BR-'.$suffix,
                'branch_name' => 'Idempotency Branch '.$suffix,
            ],
        ], [
            [
                'branch_code' => 'IDEM-BR-'.$suffix,
                'branch_name' => 'Idempotency Branch Changed '.$suffix,
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function tableImportCase(string $suffix): array
    {
        $templateCode = 'IDEM-TPL-'.$suffix;
        DB::table('table_templates')->updateOrInsert([
            'template_code' => $templateCode,
        ], [
            'seats' => 4,
            'description' => 'Idempotency table template',
        ]);

        return $this->makeCase('/api/v1/admin/restaurant/tables/import', [
            [
                'branch_code' => 'MAIN',
                'table_code' => 'IDEM-TABLE-'.$suffix,
                'template_code' => $templateCode,
                'zone' => 'Main',
                'status' => 'Available',
            ],
        ], [
            [
                'branch_code' => 'MAIN',
                'table_code' => 'IDEM-TABLE-'.$suffix,
                'template_code' => $templateCode,
                'zone' => 'Patio',
                'status' => 'Available',
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function menuCategoryImportCase(string $suffix): array
    {
        return $this->makeCase('/api/v1/admin/menu/categories/import', [
            [
                'name' => 'Idempotency Category '.$suffix,
                'description' => 'Initial category import',
            ],
        ], [
            [
                'name' => 'Idempotency Category '.$suffix,
                'description' => 'Changed category import',
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function menuItemImportCase(string $suffix): array
    {
        $categoryName = 'Idempotency Item Category '.$suffix;
        $this->ensureMenuCategory($categoryName);

        return $this->makeCase('/api/v1/admin/menu/items/import', [
            [
                'code' => 'IDEM-ITEM-'.$suffix,
                'name' => 'Idempotency Item '.$suffix,
                'category_name' => $categoryName,
                'is_available' => true,
            ],
        ], [
            [
                'code' => 'IDEM-ITEM-'.$suffix,
                'name' => 'Idempotency Item Changed '.$suffix,
                'category_name' => $categoryName,
                'is_available' => true,
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function menuPriceImportCase(string $suffix): array
    {
        $itemCode = 'IDEM-PRICE-'.$suffix;
        $this->createMenuItem([
            'code' => $itemCode,
            'name' => 'Idempotency Price Item '.$suffix,
        ]);

        return $this->makeCase('/api/v1/admin/menu/prices/import', [
            [
                'item_code' => $itemCode,
                'price' => '123000',
                'currency' => 'VND',
                'effective_from' => '2026-04-06T00:00:00Z',
            ],
        ], [
            [
                'item_code' => $itemCode,
                'price' => '124000',
                'currency' => 'VND',
                'effective_from' => '2026-04-06T00:00:00Z',
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function voucherImportCase(string $suffix): array
    {
        return $this->makeCase('/api/v1/admin/benefits/vouchers/import', [
            [
                'code' => 'IDEM-VOUCH-'.$suffix,
                'discount_type' => 'Percent',
                'discount_value' => '10',
                'is_active' => true,
            ],
        ], [
            [
                'code' => 'IDEM-VOUCH-'.$suffix,
                'discount_type' => 'Percent',
                'discount_value' => '15',
                'is_active' => true,
            ],
        ]);
    }

    /**
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function loyaltyTierImportCase(string $suffix): array
    {
        return $this->makeCase('/api/v1/admin/benefits/loyalty-tiers/import', [
            [
                'tier_code' => 'IDEM-TIER-'.$suffix,
                'tier_name' => 'Idempotency Tier '.$suffix,
                'min_points' => 1000,
                'benefits_json' => [
                    'priority_booking' => true,
                ],
                'is_active' => true,
            ],
        ], [
            [
                'tier_code' => 'IDEM-TIER-'.$suffix,
                'tier_name' => 'Idempotency Tier Changed '.$suffix,
                'min_points' => 1000,
                'benefits_json' => [
                    'priority_booking' => true,
                ],
                'is_active' => true,
            ],
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  list<array<string,mixed>>  $changedRows
     * @return array{uri:string,payload:array<string,mixed>,changed_payload:array<string,mixed>}
     */
    private function makeCase(string $uri, array $rows, array $changedRows): array
    {
        return [
            'uri' => $uri,
            'payload' => [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => $rows,
            ],
            'changed_payload' => [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => $changedRows,
            ],
        ];
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffHeaders($adminId, $apiKey)];
    }

    private function findRoute(string $method, string $uri): ?IlluminateRoute
    {
        $normalized = trim($uri, '/');
        $candidates = array_values(array_unique([
            $normalized,
            str_starts_with($normalized, 'api/') ? substr($normalized, 4) : 'api/'.$normalized,
        ]));

        return collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                && in_array(trim($route->uri(), '/'), $candidates, true));
    }
}
