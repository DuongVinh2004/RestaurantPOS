<?php

declare(strict_types=1);

namespace App\Platform\Uat;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Models\User;
use App\Platform\FeatureFlags\Services\FeatureFlagManagementService;
use App\Platform\Release\Services\SiteBootstrapService;
use App\Services\StaffApiKeyGovernanceService;
use Carbon\Carbon;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class UatScenarioPackService
{
    private const BRANCH_CODE = 'UATDEMO';

    private const CONVERSATION_ID = '00000000-0000-0000-0000-0000000001a1';

    private const DEFAULT_MANIFEST_PATH = 'storage/app/uat/scenario-pack.json';

    /**
     * @var array<string,array<string,mixed>>
     */
    private const USERS = [
        'admin' => [
            'username' => 'uat.admin',
            'full_name' => 'UAT Admin',
            'email' => 'uat.admin@example.test',
            'phone' => '0900001001',
            'role_name' => 'Admin',
            'password' => 'UatDemo!123',
        ],
        'staff' => [
            'username' => 'uat.staff',
            'full_name' => 'UAT Staff',
            'email' => 'uat.staff@example.test',
            'phone' => '0900001002',
            'role_name' => 'Staff',
            'password' => 'UatDemo!123',
        ],
        'customer_primary' => [
            'username' => 'uat.customer.primary',
            'full_name' => 'UAT Customer Primary',
            'email' => 'uat.customer.primary@example.test',
            'phone' => '0900001003',
            'role_name' => 'Customer',
            'password' => 'UatDemo!123',
        ],
        'customer_secondary' => [
            'username' => 'uat.customer.secondary',
            'full_name' => 'UAT Customer Secondary',
            'email' => 'uat.customer.secondary@example.test',
            'phone' => '0900001004',
            'role_name' => 'Customer',
            'password' => 'UatDemo!123',
        ],
    ];

    /**
     * @var array<string,string>
     */
    private const RESERVATION_CODES = [
        'deposit_pending' => 'UAT-DEP-001',
        'dine_in_checkin' => 'UAT-DINE-001',
        'benefits_pending' => 'UAT-BEN-001',
        'refund_partial_ready' => 'UAT-RF-001',
        'refund_cancel_ready' => 'UAT-RFC-001',
    ];

    /**
     * @var array<string,string>
     */
    private const MENU_CODES = [
        'steak' => 'UAT-STEAK',
        'pho' => 'UAT-PHO',
        'tea' => 'UAT-TEA',
        'dessert' => 'UAT-DESSERT',
    ];

    /**
     * @var array<string,string>
     */
    private const KITCHEN_STATION_CODES = [
        'hot_pass' => 'UAT-HOT-PASS',
        'drink_bar' => 'UAT-DRINK-BAR',
    ];

    private const VOUCHER_CODE = 'UAT-VOUCHER-50';

    /**
     * @var list<string>
     */
    private const ENABLED_FEATURES = [
        'customer.bill_self_payment',
        'waiting_list.advanced_automation',
        'inventory.uplift',
        'staff.conversation_inbox',
        'staff.kitchen_dispatch',
    ];

    public function __construct(
        private readonly SiteBootstrapService $siteBootstrapService,
        private readonly StaffApiKeyGovernanceService $staffApiKeyGovernanceService,
        private readonly FeatureFlagManagementService $featureFlagManagementService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function bootstrap(?string $baseUrl = null, ?string $manifestPath = null): array
    {
        $this->reset(deleteManifest: false, manifestPath: $manifestPath);

        /** @var array<string,mixed> $manifest */
        $manifest = DB::transaction(function () use ($baseUrl): array {
            app(ReferenceDataSeeder::class)->run();

            $bootstrap = $this->siteBootstrapService->bootstrap([
                'branch_code' => self::BRANCH_CODE,
                'branch_name' => 'UAT Demo Branch',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'zones' => 'Main,Patio,VIP',
                'tables_per_zone' => 4,
                'admin_username' => (string) self::USERS['admin']['username'],
                'admin_name' => (string) self::USERS['admin']['full_name'],
                'staff_username' => (string) self::USERS['staff']['username'],
                'staff_name' => (string) self::USERS['staff']['full_name'],
                'rotate_staff_key' => true,
                'staff_key_label' => 'UAT Demo Staff Key',
                'staff_key_ttl_days' => 90,
            ]);

            $branch = Branch::query()->findOrFail((int) data_get($bootstrap, 'branch.branch_id'));
            $branch = $this->configureBranchPolicy($branch);

            $users = $this->ensureCanonicalUsers();
            $staffApiKeys = $this->reissueStaffApiKeys($users);
            $menu = $this->ensureCanonicalMenu();
            $this->ensureCanonicalKitchenRouting($menu);
            $benefits = $this->ensureCanonicalBenefits($users);
            $reservationContext = $this->seedReservationsAndPayments($branch, $users, $menu, $benefits);
            $waitingList = $this->seedWaitingListFoundation($branch, $users);
            $conversation = $this->seedConversationFoundation($branch, $users, $reservationContext, $waitingList);
            $featureFlags = $this->ensureFeatureFlags($branch, $users['admin']);
            $tables = $this->buildTableManifest($branch);

            return $this->buildManifest(
                branch: $branch,
                users: $users,
                staffApiKeys: $staffApiKeys,
                tables: $tables,
                menu: $menu,
                benefits: $benefits,
                reservations: $reservationContext['manifest'],
                waitingList: $waitingList,
                conversation: $conversation,
                featureFlags: $featureFlags,
                baseUrl: $baseUrl,
            );
        }, 3);

        $resolvedManifestPath = $this->resolveManifestPath($manifestPath);
        $this->writeManifest($resolvedManifestPath, $manifest);

        return [
            'manifest_path' => $resolvedManifestPath,
            'manifest' => $manifest,
            'summary' => $this->buildBootstrapSummary($manifest, $resolvedManifestPath),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function reset(bool $deleteManifest = true, ?string $manifestPath = null): array
    {
        $counts = DB::transaction(function (): array {
            $branchIds = DB::table('branches')
                ->where('branch_code', self::BRANCH_CODE)
                ->pluck('branch_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            $userIds = DB::table('users')
                ->whereIn('username', array_map(
                    static fn (array $row): string => (string) $row['username'],
                    self::USERS
                ))
                ->pluck('user_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            $conversationIds = [];
            if ($branchIds !== [] || $userIds !== []) {
                $conversationIds = DB::table('conversations')
                    ->where(function ($query) use ($branchIds, $userIds): void {
                        if ($branchIds !== []) {
                            $query->whereIn('branch_id', $branchIds);
                        }

                        if ($userIds !== []) {
                            $method = $branchIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('user_id', $userIds);
                        }
                    })
                    ->pluck('conversation_id')
                    ->map(static fn (mixed $value): string => (string) $value)
                    ->all();
            }

            $messageIds = $conversationIds === []
                ? []
                : DB::table('conversation_messages')
                    ->whereIn('conversation_id', $conversationIds)
                    ->pluck('message_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();

            $waitingIds = [];
            if ($branchIds !== [] || $userIds !== []) {
                $waitingIds = DB::table('waiting_list')
                    ->where(function ($query) use ($branchIds, $userIds): void {
                        if ($branchIds !== []) {
                            $query->whereIn('branch_id', $branchIds);
                        }

                        if ($userIds !== []) {
                            $method = $branchIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('user_id', $userIds);
                        }
                    })
                    ->pluck('waiting_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();
            }

            $reservationIds = [];
            if ($branchIds !== [] || $userIds !== []) {
                $reservationIds = DB::table('reservations')
                    ->where(function ($query) use ($branchIds, $userIds): void {
                        if ($branchIds !== []) {
                            $query->whereIn('branch_id', $branchIds);
                        }

                        if ($userIds !== []) {
                            $method = $branchIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('user_id', $userIds);
                        }
                    })
                    ->pluck('reservation_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();
            }

            $orderIds = $reservationIds === []
                ? []
                : DB::table('reservation_orders')
                    ->whereIn('reservation_id', $reservationIds)
                    ->pluck('order_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();

            $holdIds = [];
            if ($branchIds !== [] || $userIds !== []) {
                $holdIds = DB::table('table_holds')
                    ->where(function ($query) use ($branchIds, $userIds): void {
                        if ($branchIds !== []) {
                            $query->whereIn('branch_id', $branchIds);
                        }

                        if ($userIds !== []) {
                            $method = $branchIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('user_id', $userIds);
                        }
                    })
                    ->pluck('hold_id')
                    ->map(static fn (mixed $value): string => (string) $value)
                    ->all();
            }

            $voucherIds = DB::table('vouchers')
                ->where('code', self::VOUCHER_CODE)
                ->pluck('voucher_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            $menuItemIds = DB::table('menu_items')
                ->whereIn('code', array_values(self::MENU_CODES))
                ->pluck('item_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            $orderItemIds = [];
            if ($orderIds !== [] || $menuItemIds !== []) {
                $orderItemIds = DB::table('reservation_order_items')
                    ->where(function ($query) use ($orderIds, $menuItemIds): void {
                        if ($orderIds !== []) {
                            $query->whereIn('order_id', $orderIds);
                        }

                        if ($menuItemIds !== []) {
                            $method = $orderIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('item_id', $menuItemIds);
                        }
                    })
                    ->pluck('order_item_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();
            }

            $menuCategoryIds = DB::table('menu_categories')
                ->whereIn('name', ['UAT Signatures', 'UAT Drinks'])
                ->pluck('category_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();
            $kitchenStationIds = DB::table('kitchen_stations')
                ->whereIn('code', array_values(self::KITCHEN_STATION_CODES))
                ->pluck('station_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            return [
                'message_entities' => $messageIds === [] ? 0 : DB::table('message_entities')->whereIn('message_id', $messageIds)->delete(),
                'conversation_files' => $messageIds === [] ? 0 : DB::table('conversation_files')->whereIn('message_id', $messageIds)->delete(),
                'conversation_events' => $conversationIds === [] ? 0 : DB::table('conversation_events')->whereIn('conversation_id', $conversationIds)->delete(),
                'conversation_analyses' => $conversationIds === [] ? 0 : DB::table('conversation_analyses')->whereIn('conversation_id', $conversationIds)->delete(),
                'agent_assignments' => $conversationIds === [] ? 0 : DB::table('agent_assignments')->whereIn('conversation_id', $conversationIds)->delete(),
                'conversation_messages' => $conversationIds === [] ? 0 : DB::table('conversation_messages')->whereIn('conversation_id', $conversationIds)->delete(),
                'conversations' => $conversationIds === [] ? 0 : DB::table('conversations')->whereIn('conversation_id', $conversationIds)->delete(),
                'reservation_bill_payment_sessions' => $reservationIds === [] ? 0 : DB::table('reservation_bill_payment_sessions')->whereIn('reservation_id', $reservationIds)->delete(),
                'reservation_deposit_payment_sessions' => $reservationIds === [] ? 0 : DB::table('reservation_deposit_payment_sessions')->whereIn('reservation_id', $reservationIds)->delete(),
                'billing_invoices' => $reservationIds === [] ? 0 : DB::table('billing_invoices')->whereIn('reservation_id', $reservationIds)->delete(),
                'kitchen_order_item_tickets' => ($orderIds === [] && $orderItemIds === [] && $menuItemIds === []) ? 0 : DB::table('kitchen_order_item_tickets')
                    ->where(function ($query) use ($orderIds, $orderItemIds, $menuItemIds): void {
                        if ($orderIds !== []) {
                            $query->whereIn('order_id', $orderIds);
                        }

                        if ($orderItemIds !== []) {
                            $method = $orderIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('order_item_id', $orderItemIds);
                        }

                        if ($menuItemIds !== []) {
                            $method = ($orderIds !== [] || $orderItemIds !== []) ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('item_id', $menuItemIds);
                        }
                    })
                    ->delete(),
                'reservation_order_items' => $orderItemIds === [] ? 0 : DB::table('reservation_order_items')->whereIn('order_item_id', $orderItemIds)->delete(),
                'reservation_orders' => $reservationIds === [] ? 0 : DB::table('reservation_orders')->whereIn('reservation_id', $reservationIds)->delete(),
                'payments' => $reservationIds === [] ? 0 : DB::table('payments')->whereIn('reservation_id', $reservationIds)->delete(),
                'reservation_tables' => $reservationIds === [] ? 0 : DB::table('reservation_tables')->whereIn('reservation_id', $reservationIds)->delete(),
                'reservations' => $reservationIds === [] ? 0 : DB::table('reservations')->whereIn('reservation_id', $reservationIds)->delete(),
                'table_hold_details' => $holdIds === [] ? 0 : DB::table('table_hold_details')->whereIn('hold_id', $holdIds)->delete(),
                'table_holds' => $holdIds === [] ? 0 : DB::table('table_holds')->whereIn('hold_id', $holdIds)->delete(),
                'waiting_list' => $waitingIds === [] ? 0 : DB::table('waiting_list')->whereIn('waiting_id', $waitingIds)->delete(),
                'customer_access_sessions' => $userIds === [] ? 0 : DB::table('customer_access_sessions')->whereIn('user_id', $userIds)->delete(),
                'staff_api_keys' => $userIds === [] ? 0 : DB::table('staff_api_keys')->whereIn('user_id', $userIds)->delete(),
                'user_vouchers' => ($userIds === [] && $voucherIds === []) ? 0 : DB::table('user_vouchers')
                    ->where(function ($query) use ($userIds, $voucherIds): void {
                        if ($userIds !== []) {
                            $query->whereIn('user_id', $userIds);
                        }

                        if ($voucherIds !== []) {
                            $method = $userIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('voucher_id', $voucherIds);
                        }
                    })
                    ->delete(),
                'loyalty_point_transactions' => $userIds === [] ? 0 : DB::table('loyalty_point_transactions')->whereIn('user_id', $userIds)->delete(),
                'user_points' => $userIds === [] ? 0 : DB::table('user_points')->whereIn('user_id', $userIds)->delete(),
                'user_tier_history' => $userIds === [] ? 0 : DB::table('user_tier_history')->whereIn('user_id', $userIds)->delete(),
                'feature_flags' => $branchIds === [] ? 0 : DB::table('feature_flags')->whereIn('branch_id', $branchIds)->delete(),
                'reporting_daily_inventory_movement_snapshots' => $branchIds === [] ? 0 : DB::table('reporting_daily_inventory_movement_snapshots')->whereIn('branch_id', $branchIds)->delete(),
                'reporting_daily_operation_snapshots' => $branchIds === [] ? 0 : DB::table('reporting_daily_operation_snapshots')->whereIn('branch_id', $branchIds)->delete(),
                'reporting_daily_sales_snapshots' => $branchIds === [] ? 0 : DB::table('reporting_daily_sales_snapshots')->whereIn('branch_id', $branchIds)->delete(),
                'kitchen_station_category_routes' => ($menuCategoryIds === [] && $kitchenStationIds === []) ? 0 : DB::table('kitchen_station_category_routes')
                    ->where(function ($query) use ($menuCategoryIds, $kitchenStationIds): void {
                        if ($menuCategoryIds !== []) {
                            $query->whereIn('category_id', $menuCategoryIds);
                        }

                        if ($kitchenStationIds !== []) {
                            $method = $menuCategoryIds !== [] ? 'orWhereIn' : 'whereIn';
                            $query->{$method}('station_id', $kitchenStationIds);
                        }
                    })
                    ->delete(),
                'kitchen_stations' => $kitchenStationIds === [] ? 0 : DB::table('kitchen_stations')->whereIn('station_id', $kitchenStationIds)->delete(),
                'restaurant_tables' => $branchIds === [] ? 0 : DB::table('restaurant_tables')->whereIn('branch_id', $branchIds)->delete(),
                'branches' => $branchIds === [] ? 0 : DB::table('branches')->whereIn('branch_id', $branchIds)->delete(),
                'users' => $userIds === [] ? 0 : DB::table('users')->whereIn('user_id', $userIds)->delete(),
                'menu_item_prices' => $menuItemIds === [] ? 0 : DB::table('menu_item_prices')->whereIn('item_id', $menuItemIds)->delete(),
                'menu_item_recipes' => $menuItemIds === [] ? 0 : DB::table('menu_item_recipes')->whereIn('item_id', $menuItemIds)->delete(),
                'menu_items' => $menuItemIds === [] ? 0 : DB::table('menu_items')->whereIn('item_id', $menuItemIds)->delete(),
                'menu_categories' => $menuCategoryIds === [] ? 0 : DB::table('menu_categories')->whereIn('category_id', $menuCategoryIds)->delete(),
                'vouchers' => $voucherIds === [] ? 0 : DB::table('vouchers')->whereIn('voucher_id', $voucherIds)->delete(),
            ];
        }, 3);

        $resolvedManifestPath = $this->resolveManifestPath($manifestPath);
        $manifestDeleted = false;
        if ($deleteManifest && File::exists($resolvedManifestPath)) {
            File::delete($resolvedManifestPath);
            $manifestDeleted = true;
        }

        return [
            'deleted' => $counts,
            'manifest_deleted' => $manifestDeleted,
            'manifest_path' => $resolvedManifestPath,
        ];
    }

    private function configureBranchPolicy(Branch $branch): Branch
    {
        $branch->fill([
            'description' => 'Canonical UAT/demo branch for RestaurantPOS backend hand-off.',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '00:00',
                        'end_time' => '23:59',
                    ]],
                ])
                ->all(),
            'closure_windows' => [],
            'booking_policy' => [
                'reservation' => [
                    'min_lead_time_minutes' => 15,
                    'max_advance_time_minutes' => 60 * 24 * 30,
                    'same_day_cutoff_time' => '23:30',
                    'service_buffer_minutes' => 15,
                ],
                'waiting_list' => [
                    'enabled' => true,
                    'notify_hold_minutes' => 10,
                    'default_service_minutes' => 90,
                ],
            ],
            'is_active' => true,
        ]);
        $branch->save();

        return $branch->fresh() ?? $branch;
    }

    /**
     * @return array<string,User>
     */
    private function ensureCanonicalUsers(): array
    {
        $users = [];

        foreach (self::USERS as $key => $definition) {
            $roleId = $this->resolveRoleId((string) $definition['role_name']);
            $now = now('UTC');
            $username = (string) $definition['username'];
            $payload = [
                'password_hash' => Hash::make((string) $definition['password']),
                'full_name' => (string) $definition['full_name'],
                'email' => (string) $definition['email'],
                'phone' => (string) $definition['phone'],
                'role_id' => $roleId,
                'current_tier_id' => null,
                'language_pref' => 'vn',
                'is_deleted' => 0,
                'updated_at' => $now,
                'row_version' => 1,
            ];

            $existingUserId = DB::table('users')
                ->where('username', $username)
                ->value('user_id');

            if ($existingUserId !== null) {
                DB::table('users')
                    ->where('user_id', (int) $existingUserId)
                    ->update($payload);
            } else {
                DB::table('users')->insert(array_merge($payload, [
                    'username' => $username,
                    'created_at' => $now,
                ]));
            }

            $users[$key] = User::query()
                ->with('role')
                ->where('username', $username)
                ->firstOrFail();
        }

        return $users;
    }

    /**
     * @param array<string,User> $users
     * @return array<string,array<string,mixed>>
     */
    private function reissueStaffApiKeys(array $users): array
    {
        $keys = [];

        foreach (['admin' => 'UAT Demo Admin Key', 'staff' => 'UAT Demo Staff Key'] as $key => $label) {
            $user = $users[$key];

            DB::table('staff_api_keys')
                ->where('user_id', (int) $user->user_id)
                ->update([
                    'revoked_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);

            $issued = $this->staffApiKeyGovernanceService->issueKey(
                (int) $user->user_id,
                $label,
                now('UTC')->addDays(90),
            );

            $keys[$key] = [
                'staff_api_key_id' => (int) $issued['record']->getKey(),
                'access_token' => (string) $issued['plaintext_key'],
                'label' => $label,
            ];
        }

        return $keys;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function ensureCanonicalMenu(): array
    {
        $categoryIds = [
            'signatures' => $this->upsertMenuCategory('UAT Signatures', 'Canonical UAT mains and signatures.', 10),
            'drinks' => $this->upsertMenuCategory('UAT Drinks', 'Canonical UAT beverage catalog.', 20),
        ];

        return [
            'categories' => [
                'signatures' => [
                    'category_id' => $categoryIds['signatures'],
                    'name' => 'UAT Signatures',
                ],
                'drinks' => [
                    'category_id' => $categoryIds['drinks'],
                    'name' => 'UAT Drinks',
                ],
            ],
            'items' => [
                'steak' => $this->upsertMenuItem(self::MENU_CODES['steak'], $categoryIds['signatures'], 'UAT Pepper Steak', 'Signature steak for checkout and benefits demos.', '180000.00'),
                'pho' => $this->upsertMenuItem(self::MENU_CODES['pho'], $categoryIds['signatures'], 'UAT Beef Pho', 'Fallback hot main for table-order flows.', '95000.00'),
                'dessert' => $this->upsertMenuItem(self::MENU_CODES['dessert'], $categoryIds['signatures'], 'UAT Caramel Flan', 'Dessert for add-item checks.', '45000.00'),
                'tea' => $this->upsertMenuItem(self::MENU_CODES['tea'], $categoryIds['drinks'], 'UAT Peach Tea', 'Drink for menu and order-item demos.', '35000.00'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $menu
     */
    private function ensureCanonicalKitchenRouting(array $menu): void
    {
        $signatureCategoryId = (int) data_get($menu, 'categories.signatures.category_id');
        $drinkCategoryId = (int) data_get($menu, 'categories.drinks.category_id');

        if ($signatureCategoryId > 0) {
            $stationId = $this->upsertKitchenStation(
                self::KITCHEN_STATION_CODES['hot_pass'],
                'UAT Hot Pass',
                'Canonical hot-pass station for dine-in order and kitchen smoke.',
                'Both',
                'printer://uat-hot-pass'
            );
            $this->upsertKitchenStationRoute($stationId, $signatureCategoryId, 10);
        }

        if ($drinkCategoryId > 0) {
            $stationId = $this->upsertKitchenStation(
                self::KITCHEN_STATION_CODES['drink_bar'],
                'UAT Drink Bar',
                'Canonical beverage station for dine-in kitchen smoke.',
                'Both',
                'printer://uat-drink-bar'
            );
            $this->upsertKitchenStationRoute($stationId, $drinkCategoryId, 20);
        }
    }

    /**
     * @param array<string,User> $users
     * @return array<string,mixed>
     */
    private function ensureCanonicalBenefits(array $users): array
    {
        $tierId = $this->upsertLoyaltyTier();

        DB::table('users')
            ->where('user_id', (int) $users['customer_primary']->user_id)
            ->update([
                'current_tier_id' => $tierId,
                'updated_at' => now('UTC'),
            ]);

        DB::table('user_points')->updateOrInsert(
            ['user_id' => (int) $users['customer_primary']->user_id],
            [
                'total_points' => 500,
                'last_updated' => now('UTC'),
                'updated_by' => (int) $users['admin']->user_id,
                'row_version' => 1,
            ]
        );

        $voucherId = $this->upsertVoucher();
        DB::table('user_vouchers')->updateOrInsert(
            [
                'user_id' => (int) $users['customer_primary']->user_id,
                'voucher_id' => $voucherId,
            ],
            [
                'assigned_date' => now('UTC'),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'is_used' => 0,
                'used_date' => null,
                'used_reservation_id' => null,
                'used_amount' => null,
                'lock_token' => null,
                'locked_until' => null,
                'created_by' => (int) $users['admin']->user_id,
                'updated_by' => (int) $users['admin']->user_id,
                'row_version' => 1,
            ]
        );

        $userVoucherId = (int) DB::table('user_vouchers')
            ->where('user_id', (int) $users['customer_primary']->user_id)
            ->where('voucher_id', $voucherId)
            ->value('user_voucher_id');

        return [
            'loyalty' => [
                'tier_id' => $tierId,
                'tier_code' => 'UAT-BRONZE',
                'available_points' => 500,
            ],
            'voucher' => [
                'voucher_id' => $voucherId,
                'voucher_code' => self::VOUCHER_CODE,
                'user_voucher_id' => $userVoucherId,
                'discount_value' => '50000.00',
            ],
        ];
    }

    /**
     * @param array<string,User> $users
     * @param array<string,array<string,mixed>> $menu
     * @param array<string,mixed> $benefits
     * @return array<string,mixed>
     */
    private function seedReservationsAndPayments(Branch $branch, array $users, array $menu, array $benefits): array
    {
        $nowLocal = now((string) $branch->timezone);
        $availabilityStart = $this->roundUpMinutes($nowLocal->copy()->addHours(2), 15);
        $dineInStart = $this->roundUpMinutes($nowLocal->copy()->addMinutes(10), 5);
        $depositStart = $this->roundUpMinutes($nowLocal->copy()->addHours(4), 15);
        $benefitsStart = $this->roundUpMinutes($nowLocal->copy()->addHours(6), 15);

        $tables = $this->buildTableManifest($branch);
        $mainFourSeatTableId = (int) data_get($tables, 'main_4p.table_id');
        $patioFourSeatTableId = (int) data_get($tables, 'patio_4p.table_id');
        $vipFourSeatTableId = (int) data_get($tables, 'vip_4p.table_id');

        $depositReservationId = $this->upsertReservation([
            'reservation_code' => self::RESERVATION_CODES['deposit_pending'],
            'branch_id' => (int) $branch->branch_id,
            'user_id' => (int) $users['customer_primary']->user_id,
            'reserved_at' => now('UTC'),
            'start_time' => $depositStart->copy()->utc(),
            'end_time' => $depositStart->copy()->addHours(2)->utc(),
            'guest_count' => 2,
            'status' => 'Confirmed',
            'source' => 'Online',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'discount_amount' => '0.00',
            'bill_currency' => 'VND',
            'created_by' => (int) $users['admin']->user_id,
            'updated_by' => (int) $users['admin']->user_id,
            'notes' => 'UAT deposit preview and payment session scenario.',
        ], [$patioFourSeatTableId]);

        $dineInReservationId = $this->upsertReservation([
            'reservation_code' => self::RESERVATION_CODES['dine_in_checkin'],
            'branch_id' => (int) $branch->branch_id,
            'user_id' => (int) $users['customer_primary']->user_id,
            'reserved_at' => now('UTC'),
            'start_time' => $dineInStart->copy()->utc(),
            'end_time' => $dineInStart->copy()->addHours(2)->utc(),
            'guest_count' => 2,
            'status' => 'Confirmed',
            'source' => 'WalkIn',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'discount_amount' => '0.00',
            'bill_currency' => 'VND',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
            'notes' => 'UAT check-in to checkout scenario.',
        ], [$mainFourSeatTableId]);

        $benefitsReservationId = $this->upsertReservation([
            'reservation_code' => self::RESERVATION_CODES['benefits_pending'],
            'branch_id' => (int) $branch->branch_id,
            'user_id' => (int) $users['customer_primary']->user_id,
            'reserved_at' => now('UTC'),
            'start_time' => $benefitsStart->copy()->utc(),
            'end_time' => $benefitsStart->copy()->addHours(2)->utc(),
            'guest_count' => 2,
            'status' => 'Confirmed',
            'source' => 'Online',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'discount_amount' => '0.00',
            'bill_currency' => 'VND',
            'created_by' => (int) $users['admin']->user_id,
            'updated_by' => (int) $users['admin']->user_id,
            'notes' => 'UAT voucher and loyalty scenario.',
        ], [$vipFourSeatTableId]);

        $benefitsOrderId = $this->upsertOrder([
            'reservation_id' => $benefitsReservationId,
            'order_type' => 'OnSpot',
            'status' => 'Completed',
            'notes' => 'UAT benefits financial base order.',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
        ]);
        $this->replaceOrderItems($benefitsOrderId, [
            [
                'item_id' => (int) data_get($menu, 'items.steak.item_id'),
                'quantity' => 1,
                'unit_price' => '180000.00',
                'line_total' => '180000.00',
                'currency' => 'VND',
                'status' => 'Served',
            ],
            [
                'item_id' => (int) data_get($menu, 'items.tea.item_id'),
                'quantity' => 1,
                'unit_price' => '35000.00',
                'line_total' => '35000.00',
                'currency' => 'VND',
                'status' => 'Served',
            ],
        ]);

        $refundPartialReservationId = $this->upsertReservation([
            'reservation_code' => self::RESERVATION_CODES['refund_partial_ready'],
            'branch_id' => (int) $branch->branch_id,
            'user_id' => (int) $users['customer_primary']->user_id,
            'reserved_at' => now('UTC')->subHours(4),
            'start_time' => $nowLocal->copy()->subHours(4)->utc(),
            'end_time' => $nowLocal->copy()->subHours(2)->utc(),
            'guest_count' => 2,
            'status' => 'Completed',
            'source' => 'Online',
            'checked_in_at' => $nowLocal->copy()->subHours(4)->utc(),
            'checked_out_at' => $nowLocal->copy()->subHours(2)->utc(),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'discount_amount' => '0.00',
            'final_bill_amount' => '0.00',
            'bill_currency' => 'VND',
            'billed_at' => $nowLocal->copy()->subHours(3)->utc(),
            'created_by' => (int) $users['admin']->user_id,
            'updated_by' => (int) $users['admin']->user_id,
            'notes' => 'UAT partial refund scenario.',
        ], [$patioFourSeatTableId]);
        $this->upsertPayment([
            'reservation_id' => $refundPartialReservationId,
            'branch_id' => (int) $branch->branch_id,
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'transaction_code' => 'UAT-DEP-RF-001',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
            'notes' => 'UAT seeded deposit payment for partial refund.',
        ]);

        $refundCancelReservationId = $this->upsertReservation([
            'reservation_code' => self::RESERVATION_CODES['refund_cancel_ready'],
            'branch_id' => (int) $branch->branch_id,
            'user_id' => (int) $users['customer_secondary']->user_id,
            'reserved_at' => now('UTC')->subHours(3),
            'start_time' => $nowLocal->copy()->subHours(3)->utc(),
            'end_time' => $nowLocal->copy()->addHour()->utc(),
            'guest_count' => 2,
            'status' => 'Reserved',
            'source' => 'WalkIn',
            'checked_in_at' => $nowLocal->copy()->subHours(2)->utc(),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'discount_amount' => '0.00',
            'final_bill_amount' => '200000.00',
            'bill_currency' => 'VND',
            'billed_at' => $nowLocal->copy()->subMinutes(30)->utc(),
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
            'notes' => 'UAT refund-cancel scenario.',
        ], [$vipFourSeatTableId]);
        $refundCancelOrderId = $this->upsertOrder([
            'reservation_id' => $refundCancelReservationId,
            'order_type' => 'OnSpot',
            'status' => 'Completed',
            'notes' => 'UAT refund-cancel completed order.',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
        ]);
        $this->replaceOrderItems($refundCancelOrderId, [
            [
                'item_id' => (int) data_get($menu, 'items.steak.item_id'),
                'quantity' => 1,
                'unit_price' => '180000.00',
                'line_total' => '180000.00',
                'currency' => 'VND',
                'status' => 'Served',
            ],
            [
                'item_id' => (int) data_get($menu, 'items.dessert.item_id'),
                'quantity' => 1,
                'unit_price' => '20000.00',
                'line_total' => '20000.00',
                'currency' => 'VND',
                'status' => 'Served',
            ],
        ]);
        $this->upsertPayment([
            'reservation_id' => $refundCancelReservationId,
            'branch_id' => (int) $branch->branch_id,
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'transaction_code' => 'UAT-DEP-RFC-001',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
            'notes' => 'UAT seeded deposit payment for refund-cancel.',
        ]);
        $this->upsertPayment([
            'reservation_id' => $refundCancelReservationId,
            'branch_id' => (int) $branch->branch_id,
            'amount' => '150000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'payment_type' => 'Final',
            'status' => 'Success',
            'transaction_code' => 'UAT-FINAL-RFC-001',
            'created_by' => (int) $users['staff']->user_id,
            'updated_by' => (int) $users['staff']->user_id,
            'notes' => 'UAT seeded final payment for refund-cancel.',
        ]);

        return [
            'ids' => [
                'deposit_pending' => $depositReservationId,
                'dine_in_checkin' => $dineInReservationId,
                'benefits_pending' => $benefitsReservationId,
                'refund_partial_ready' => $refundPartialReservationId,
                'refund_cancel_ready' => $refundCancelReservationId,
            ],
            'manifest' => [
                'deposit_pending' => $this->reservationManifestRow($depositReservationId),
                'dine_in_checkin' => $this->reservationManifestRow($dineInReservationId),
                'benefits_pending' => array_merge(
                    $this->reservationManifestRow($benefitsReservationId),
                    [
                        'user_voucher_id' => (int) data_get($benefits, 'voucher.user_voucher_id'),
                        'available_points' => (int) data_get($benefits, 'loyalty.available_points'),
                    ]
                ),
                'refund_partial_ready' => $this->reservationManifestRow($refundPartialReservationId),
                'refund_cancel_ready' => $this->reservationManifestRow($refundCancelReservationId),
            ],
            'scenario_windows' => [
                'availability_start_utc' => $availabilityStart->copy()->utc()->toIso8601String(),
                'availability_end_utc' => $availabilityStart->copy()->addHours(2)->utc()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param array<string,User> $users
     * @return array<string,mixed>
     */
    private function seedWaitingListFoundation(Branch $branch, array $users): array
    {
        DB::table('waiting_list')->updateOrInsert(
            [
                'branch_id' => (int) $branch->branch_id,
                'user_id' => (int) $users['customer_secondary']->user_id,
                'guest_name' => 'UAT Conversation Queue',
            ],
            [
                'customer_session_id' => null,
                'phone' => (string) self::USERS['customer_secondary']['phone'],
                'guest_count' => 2,
                'requested_at' => now('UTC')->subMinutes(30),
                'status' => 'Waiting',
                'priority' => 0,
                'notified_at' => null,
                'notify_expires_at' => null,
                'customer_response_status' => null,
                'customer_responded_at' => null,
                'customer_confirmed_arrival_at' => null,
                'notified_by' => null,
                'created_at' => now('UTC')->subMinutes(30),
                'updated_at' => now('UTC'),
                'seated_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Canonical waiting-list seed for conversation linkage.',
                'updated_by' => null,
                'row_version' => 1,
            ]
        );

        $waitingId = (int) DB::table('waiting_list')
            ->where('branch_id', (int) $branch->branch_id)
            ->where('user_id', (int) $users['customer_secondary']->user_id)
            ->where('guest_name', 'UAT Conversation Queue')
            ->value('waiting_id');

        return [
            'seeded_waiting_entry' => [
                'waiting_id' => $waitingId,
                'status' => 'Waiting',
                'guest_name' => 'UAT Conversation Queue',
            ],
        ];
    }

    /**
     * @param array<string,User> $users
     * @param array<string,mixed> $reservationContext
     * @param array<string,mixed> $waitingList
     * @return array<string,mixed>
     */
    private function seedConversationFoundation(
        Branch $branch,
        array $users,
        array $reservationContext,
        array $waitingList,
    ): array {
        DB::table('conversations')->updateOrInsert(
            ['conversation_id' => self::CONVERSATION_ID],
            [
                'branch_id' => (int) $branch->branch_id,
                'user_id' => (int) $users['customer_secondary']->user_id,
                'customer_session_id' => null,
                'session_id' => 'uat-conversation-session',
                'channel' => 'WebChat',
                'status' => 'Open',
                'intent_detected' => 'reservation_follow_up',
                'linked_reservation_id' => (int) data_get($reservationContext, 'ids.deposit_pending'),
                'linked_waiting_list_id' => (int) data_get($waitingList, 'seeded_waiting_entry.waiting_id'),
                'created_at' => now('UTC')->subMinutes(20),
                'closed_at' => null,
            ]
        );

        DB::table('message_entities')->whereIn('message_id', function ($query): void {
            $query->select('message_id')
                ->from('conversation_messages')
                ->where('conversation_id', self::CONVERSATION_ID);
        })->delete();
        DB::table('conversation_files')->whereIn('message_id', function ($query): void {
            $query->select('message_id')
                ->from('conversation_messages')
                ->where('conversation_id', self::CONVERSATION_ID);
        })->delete();
        DB::table('conversation_messages')->where('conversation_id', self::CONVERSATION_ID)->delete();
        DB::table('conversation_events')->where('conversation_id', self::CONVERSATION_ID)->delete();
        DB::table('conversation_analyses')->where('conversation_id', self::CONVERSATION_ID)->delete();
        DB::table('agent_assignments')->where('conversation_id', self::CONVERSATION_ID)->delete();

        $userMessageId = (int) DB::table('conversation_messages')->insertGetId([
            'conversation_id' => self::CONVERSATION_ID,
            'sender' => 'user',
            'sender_id' => (int) $users['customer_secondary']->user_id,
            'message_text' => 'Can you confirm the reservation deposit and queue status?',
            'message_type' => 'text',
            'is_internal_note' => 0,
            'attachment_url' => null,
            'created_at' => now('UTC')->subMinutes(19),
            'is_processed' => 1,
            'processing_status' => 'reviewed',
            'confidence' => '0.9500',
            'related_reservation_id' => (int) data_get($reservationContext, 'ids.deposit_pending'),
            'related_order_id' => null,
        ]);

        DB::table('conversation_messages')->insert([
            'conversation_id' => self::CONVERSATION_ID,
            'sender' => 'agent',
            'sender_id' => (int) $users['staff']->user_id,
            'message_text' => 'Internal note: guest is part of the UAT demo pack.',
            'message_type' => 'text',
            'is_internal_note' => 1,
            'attachment_url' => null,
            'created_at' => now('UTC')->subMinutes(18),
            'is_processed' => 1,
            'processing_status' => 'reviewed',
            'confidence' => '1.0000',
            'related_reservation_id' => (int) data_get($reservationContext, 'ids.deposit_pending'),
            'related_order_id' => null,
        ]);

        DB::table('message_entities')->insert([
            'message_id' => $userMessageId,
            'entity_type' => 'reservation_code',
            'entity_text' => self::RESERVATION_CODES['deposit_pending'],
            'entity_normalized' => self::RESERVATION_CODES['deposit_pending'],
            'extra_json' => json_encode(['source' => 'uat_pack'], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC')->subMinutes(19),
        ]);

        DB::table('conversation_events')->insert([
            'conversation_id' => self::CONVERSATION_ID,
            'event_type' => 'conversation.linked',
            'event_by_user_id' => (int) $users['staff']->user_id,
            'event_data' => json_encode([
                'linked_reservation_id' => (int) data_get($reservationContext, 'ids.deposit_pending'),
                'linked_waiting_list_id' => (int) data_get($waitingList, 'seeded_waiting_entry.waiting_id'),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC')->subMinutes(17),
        ]);

        DB::table('conversation_analyses')->insert([
            'conversation_id' => self::CONVERSATION_ID,
            'analyzer_name' => 'uat_demo_pack',
            'is_spam' => 0,
            'quality_score' => '0.9300',
            'extracted_info' => json_encode([
                'intent' => 'reservation_follow_up',
                'pack' => 'uat_demo',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC')->subMinutes(17),
        ]);

        DB::table('agent_assignments')->insert([
            'conversation_id' => self::CONVERSATION_ID,
            'agent_user_id' => (int) $users['staff']->user_id,
            'assigned_at' => now('UTC')->subMinutes(16),
            'released_at' => null,
            'is_active' => 1,
            'notes' => 'UAT demo inbox owner',
        ]);

        return [
            'conversation_id' => self::CONVERSATION_ID,
            'linked_reservation_id' => (int) data_get($reservationContext, 'ids.deposit_pending'),
            'linked_waiting_list_id' => (int) data_get($waitingList, 'seeded_waiting_entry.waiting_id'),
            'status' => 'Open',
        ];
    }

    /**
     * @param array<string,User> $users
     * @return list<array<string,mixed>>
     */
    private function ensureFeatureFlags(Branch $branch, User $actor): array
    {
        $environment = (string) config('app.env', 'local');
        $rows = [];

        foreach (self::ENABLED_FEATURES as $featureKey) {
            $result = $this->featureFlagManagementService->upsertOverride(
                featureKey: $featureKey,
                enabled: true,
                environment: $environment,
                branchId: (int) $branch->branch_id,
                reason: 'Enabled for canonical UAT/demo scenario pack.',
                actorUserId: (int) $actor->user_id,
                actorType: 'system',
                actorKey: 'booking:uat-pack:bootstrap',
            );

            $rows[] = (array) ($result['feature'] ?? []);
        }

        return $rows;
    }

    /**
     * @param array<string,User> $users
     * @param array<string,array<string,mixed>> $staffApiKeys
     * @param array<string,array<string,mixed>> $tables
     * @param array<string,array<string,mixed>> $menu
     * @param array<string,mixed> $benefits
     * @param array<string,mixed> $reservations
     * @param array<string,mixed> $waitingList
     * @param array<string,mixed> $conversation
     * @param list<array<string,mixed>> $featureFlags
     * @return array<string,mixed>
     */
    private function buildManifest(
        Branch $branch,
        array $users,
        array $staffApiKeys,
        array $tables,
        array $menu,
        array $benefits,
        array $reservations,
        array $waitingList,
        array $conversation,
        array $featureFlags,
        ?string $baseUrl,
    ): array {
        $resolvedBaseUrl = rtrim(trim($baseUrl !== null && trim($baseUrl) !== '' ? $baseUrl : (string) config('app.url', 'http://127.0.0.1:8000')), '/');

        return [
            'pack' => [
                'name' => 'restaurantpos-uat-demo',
                'generated_at_utc' => now('UTC')->toIso8601String(),
                'environment' => (string) config('app.env', 'local'),
                'base_url' => $resolvedBaseUrl,
                'reset_recommended_before_full_run' => true,
            ],
            'branch' => [
                'branch_id' => (int) $branch->branch_id,
                'branch_code' => (string) $branch->branch_code,
                'branch_name' => (string) $branch->branch_name,
                'timezone' => (string) $branch->timezone,
                'currency' => (string) $branch->currency,
                'business_hours' => $branch->business_hours,
                'booking_policy' => $branch->booking_policy,
            ],
            'auth' => [
                'admin' => $this->userManifestRow($users['admin'], self::USERS['admin']['password'], $staffApiKeys['admin']['access_token']),
                'staff' => $this->userManifestRow($users['staff'], self::USERS['staff']['password'], $staffApiKeys['staff']['access_token']),
                'customer_primary' => $this->userManifestRow($users['customer_primary'], self::USERS['customer_primary']['password']),
                'customer_secondary' => $this->userManifestRow($users['customer_secondary'], self::USERS['customer_secondary']['password']),
            ],
            'tables' => $tables,
            'menu' => $menu,
            'benefits' => $benefits,
            'reservations' => $reservations,
            'waiting_list' => $waitingList,
            'conversation' => $conversation,
            'feature_flags' => $featureFlags,
            'scenarios' => [
                'availability_hold_reservation' => [
                    'branch_id' => (int) $branch->branch_id,
                    'guest_count' => 4,
                    'session_id' => 'uat-session-booking',
                    'from_utc' => (string) data_get($reservations, 'deposit_pending.start_time_utc'),
                    'to_utc' => (string) data_get($reservations, 'deposit_pending.end_time_utc'),
                    'preferred_table_ids' => [(int) data_get($tables, 'main_4p.table_id')],
                ],
                'deposit_self_pay' => [
                    'reservation_id' => (int) data_get($reservations, 'deposit_pending.reservation_id'),
                    'payment_amount' => '100000.00',
                    'provider_code' => 'simulated',
                ],
                'dine_in_checkout' => [
                    'reservation_id' => (int) data_get($reservations, 'dine_in_checkin.reservation_id'),
                    'table_id' => (int) data_get($tables, 'main_4p.table_id'),
                    'menu_item_ids' => [
                        (int) data_get($menu, 'items.steak.item_id'),
                        (int) data_get($menu, 'items.tea.item_id'),
                    ],
                ],
                'refund_partial' => [
                    'reservation_id' => (int) data_get($reservations, 'refund_partial_ready.reservation_id'),
                    'refund_scope' => 'deposit',
                    'refund_amount' => '20000.00',
                ],
                'refund_cancel' => [
                    'reservation_id' => (int) data_get($reservations, 'refund_cancel_ready.reservation_id'),
                    'refund_scope' => 'all',
                ],
                'waiting_list_lifecycle' => [
                    'branch_id' => (int) $branch->branch_id,
                    'customer_user_id' => (int) $users['customer_secondary']->user_id,
                    'table_id' => (int) data_get($tables, 'patio_4p.table_id'),
                ],
                'benefits' => [
                    'reservation_id' => (int) data_get($reservations, 'benefits_pending.reservation_id'),
                    'user_voucher_id' => (int) data_get($benefits, 'voucher.user_voucher_id'),
                    'loyalty_points' => 50,
                ],
                'admin_master_data' => [
                    'branch_id' => (int) $branch->branch_id,
                    'template_id' => (int) data_get($tables, 'templates.4p.template_id'),
                    'menu_category_name' => 'UAT Scenario Specials',
                    'menu_item_code' => 'UAT-SCENARIO-ITEM',
                ],
                'conversation_inbox' => [
                    'conversation_id' => self::CONVERSATION_ID,
                    'branch_id' => (int) $branch->branch_id,
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildBootstrapSummary(array $manifest, string $manifestPath): array
    {
        return [
            'generated_at_utc' => (string) data_get($manifest, 'pack.generated_at_utc'),
            'manifest_path' => $manifestPath,
            'branch' => (array) data_get($manifest, 'branch', []),
            'users' => collect((array) data_get($manifest, 'auth', []))
                ->map(static fn (array $row): array => [
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'username' => (string) ($row['username'] ?? ''),
                    'role_name' => (string) ($row['role_name'] ?? ''),
                ])
                ->all(),
            'reservations' => collect((array) data_get($manifest, 'reservations', []))
                ->map(static fn (array $row): array => [
                    'reservation_id' => (int) ($row['reservation_id'] ?? 0),
                    'reservation_code' => (string) ($row['reservation_code'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                ])
                ->values()
                ->all(),
            'supported_scenarios' => array_keys((array) data_get($manifest, 'scenarios', [])),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function userManifestRow(User $user, string $password, ?string $accessToken = null): array
    {
        return [
            'user_id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'password' => $password,
            'full_name' => (string) $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role_name' => (string) ($user->role?->role_name ?? ''),
            'api_key' => $accessToken,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function reservationManifestRow(int $reservationId): array
    {
        $row = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first();

        if (! is_object($row)) {
            throw new \RuntimeException(sprintf('Reservation [%d] was not found while building UAT manifest.', $reservationId));
        }

        return [
            'reservation_id' => $reservationId,
            'reservation_code' => (string) $row->reservation_code,
            'status' => (string) $row->status,
            'row_version' => (int) $row->row_version,
            'start_time_utc' => Carbon::parse((string) $row->start_time)->utc()->toIso8601String(),
            'end_time_utc' => Carbon::parse((string) $row->end_time)->utc()->toIso8601String(),
            'bill_currency' => (string) $row->bill_currency,
            'deposit_status' => (string) $row->deposit_status,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function buildTableManifest(Branch $branch): array
    {
        $tableRows = RestaurantTable::query()
            ->with('template')
            ->where('branch_id', (int) $branch->branch_id)
            ->orderBy('table_code')
            ->get();

        return [
            'main_2p' => $this->resolveTableManifestEntry($tableRows, self::BRANCH_CODE . '-MAIN-01', 'Main', 2),
            'main_4p' => $this->resolveTableManifestEntry($tableRows, self::BRANCH_CODE . '-MAIN-02', 'Main', 4),
            'patio_4p' => $this->resolveTableManifestEntry($tableRows, self::BRANCH_CODE . '-PATIO-02', 'Patio', 4),
            'vip_4p' => $this->resolveTableManifestEntry($tableRows, self::BRANCH_CODE . '-VIP-02', 'VIP', 4),
            'templates' => [
                '2p' => $this->resolveTemplateManifestEntry($tableRows, self::BRANCH_CODE . '-MAIN-01', 'Main', 2),
                '4p' => $this->resolveTemplateManifestEntry($tableRows, self::BRANCH_CODE . '-MAIN-02', 'Main', 4),
                '6p' => $this->resolveTemplateManifestEntry($tableRows, self::BRANCH_CODE . '-MAIN-04', 'Main', 6),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveTableManifestEntry(
        Collection $tableRows,
        string $tableCode,
        ?string $zone = null,
        ?int $seats = null,
    ): array
    {
        /** @var RestaurantTable|null $table */
        $table = $tableRows->first(static fn (RestaurantTable $row): bool => (string) $row->table_code === $tableCode);

        if (! $table instanceof RestaurantTable && $zone !== null && $seats !== null) {
            /** @var RestaurantTable|null $table */
            $table = $tableRows->first(static function (RestaurantTable $row) use ($zone, $seats): bool {
                return strcasecmp((string) ($row->zone ?? ''), $zone) === 0
                    && (int) ($row->template?->seats ?? 0) === $seats;
            });
        }

        if (! $table instanceof RestaurantTable) {
            throw new \RuntimeException(sprintf('Expected UAT table [%s] is missing.', $tableCode));
        }

        return [
            'table_id' => (int) $table->table_id,
            'table_code' => (string) $table->table_code,
            'zone' => (string) ($table->zone ?? ''),
            'status' => (string) ($table->status?->value ?? $table->status),
            'template_id' => (int) ($table->template_id ?? 0),
            'template_code' => (string) ($table->template?->template_code ?? ''),
            'seats' => (int) ($table->template?->seats ?? 0),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveTemplateManifestEntry(
        Collection $tableRows,
        string $tableCode,
        ?string $zone = null,
        ?int $seats = null,
    ): array
    {
        /** @var RestaurantTable|null $table */
        $table = $tableRows->first(static fn (RestaurantTable $row): bool => (string) $row->table_code === $tableCode);

        if (! $table instanceof RestaurantTable && $zone !== null && $seats !== null) {
            /** @var RestaurantTable|null $table */
            $table = $tableRows->first(static function (RestaurantTable $row) use ($zone, $seats): bool {
                return strcasecmp((string) ($row->zone ?? ''), $zone) === 0
                    && (int) ($row->template?->seats ?? 0) === $seats;
            });
        }

        if (! $table instanceof RestaurantTable || $table->template === null) {
            throw new \RuntimeException(sprintf('Expected UAT template for table [%s] is missing.', $tableCode));
        }

        return [
            'template_id' => (int) $table->template->template_id,
            'template_code' => (string) $table->template->template_code,
            'seats' => (int) $table->template->seats,
            'description' => (string) ($table->template->description ?? ''),
        ];
    }

    private function upsertMenuCategory(string $name, string $description, int $sortOrder): int
    {
        DB::table('menu_categories')->updateOrInsert(
            ['name' => $name],
            [
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_deleted' => 0,
            ]
        );

        return (int) DB::table('menu_categories')
            ->where('name', $name)
            ->value('category_id');
    }

    /**
     * @return array<string,mixed>
     */
    private function upsertMenuItem(
        string $code,
        int $categoryId,
        string $name,
        string $description,
        string $price,
    ): array {
        $now = now('UTC');

        DB::table('menu_items')->updateOrInsert(
            ['code' => $code],
            [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'img_url' => null,
                'is_available' => 1,
                'is_preorder_enabled' => 0,
                'preorder_quota_per_day' => null,
                'preorder_cutoff_minutes' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $itemId = (int) DB::table('menu_items')
            ->where('code', $code)
            ->value('item_id');

        $activePrice = DB::table('menu_item_prices')
            ->where('item_id', $itemId)
            ->where('currency', 'VND')
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->first();

        if (! is_object($activePrice) || number_format((float) $activePrice->price, 2, '.', '') !== number_format((float) $price, 2, '.', '')) {
            DB::table('menu_item_prices')
                ->where('item_id', $itemId)
                ->where('currency', 'VND')
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => $now,
                ]);

            DB::table('menu_item_prices')->insert([
                'item_id' => $itemId,
                'price' => $price,
                'currency' => 'VND',
                'effective_from' => $now,
                'effective_to' => null,
            ]);

            $activePrice = DB::table('menu_item_prices')
                ->where('item_id', $itemId)
                ->where('currency', 'VND')
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->orderByDesc('price_id')
                ->first();
        }

        return [
            'item_id' => $itemId,
            'code' => $code,
            'name' => $name,
            'category_id' => $categoryId,
            'active_price_id' => (int) ($activePrice->price_id ?? 0),
            'current_price' => $price,
            'currency' => 'VND',
        ];
    }

    private function upsertKitchenStation(
        string $code,
        string $name,
        string $description,
        string $outputMode,
        ?string $printerTarget = null,
    ): int {
        $now = now('UTC');

        DB::table('kitchen_stations')->updateOrInsert(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'output_mode' => $outputMode,
                'printer_target' => $printerTarget,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('kitchen_stations')
            ->where('code', $code)
            ->value('station_id');
    }

    private function upsertKitchenStationRoute(int $stationId, int $categoryId, int $sortOrder): void
    {
        $now = now('UTC');

        DB::table('kitchen_station_category_routes')->updateOrInsert(
            ['category_id' => $categoryId],
            [
                'station_id' => $stationId,
                'sort_order' => $sortOrder,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function upsertLoyaltyTier(): int
    {
        $now = now('UTC');

        DB::table('loyalty_tiers')->updateOrInsert(
            ['tier_code' => 'UAT-BRONZE'],
            [
                'tier_name' => 'UAT Bronze',
                'min_points' => 0,
                'benefits_json' => json_encode([
                    'voucher_demo' => true,
                    'loyalty_demo' => true,
                ], JSON_THROW_ON_ERROR),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'row_version' => 1,
            ]
        );

        return (int) DB::table('loyalty_tiers')
            ->where('tier_code', 'UAT-BRONZE')
            ->value('tier_id');
    }

    private function upsertVoucher(): int
    {
        $now = now('UTC');

        DB::table('vouchers')->updateOrInsert(
            ['code' => self::VOUCHER_CODE],
            [
                'description' => 'Canonical fixed-value voucher for UAT benefits flow.',
                'discount_type' => 'Fixed',
                'discount_value' => '50000.00',
                'free_item_id' => null,
                'free_item_qty' => null,
                'max_usage' => null,
                'max_usage_per_user' => 1,
                'min_spend' => '150000.00',
                'start_date' => $now->copy()->subDays(1),
                'expiry_date' => $now->copy()->addDays(30),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => null,
                'updated_by' => null,
                'row_version' => 1,
            ]
        );

        return (int) DB::table('vouchers')
            ->where('code', self::VOUCHER_CODE)
            ->value('voucher_id');
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<int> $tableIds
     */
    private function upsertReservation(array $payload, array $tableIds): int
    {
        $now = now('UTC');
        $reservationCode = (string) ($payload['reservation_code'] ?? '');

        if ($reservationCode === '') {
            throw new \InvalidArgumentException('UAT reservation seed requires reservation_code.');
        }

        $existingId = DB::table('reservations')
            ->where('reservation_code', $reservationCode)
            ->value('reservation_id');

        $row = array_merge([
            'reserved_at' => $now,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'cancelled_by' => null,
            'no_show_at' => null,
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'deposit_requirement_acknowledged_at' => null,
            'deposit_intent_status' => 'None',
            'deposit_intent_submitted_at' => null,
            'deposit_intent_revoked_at' => null,
            'applied_user_voucher_id' => null,
            'discount_amount' => '0.00',
            'final_bill_amount' => null,
            'bill_currency' => 'VND',
            'billed_at' => null,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ], $payload);

        if ($existingId !== null) {
            DB::table('reservations')
                ->where('reservation_id', (int) $existingId)
                ->update(array_merge($row, [
                    'updated_at' => $now,
                ]));

            $reservationId = (int) $existingId;
        } else {
            $reservationId = (int) DB::table('reservations')->insertGetId($row, 'reservation_id');
        }

        DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->delete();

        foreach (array_values(array_unique(array_map('intval', $tableIds))) as $tableId) {
            DB::table('reservation_tables')->insert([
                'reservation_id' => $reservationId,
                'table_id' => $tableId,
            ]);
        }

        return $reservationId;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function upsertOrder(array $payload): int
    {
        $now = now('UTC');
        $reservationId = (int) ($payload['reservation_id'] ?? 0);

        if ($reservationId <= 0) {
            throw new \InvalidArgumentException('UAT order seed requires reservation_id.');
        }

        $existingId = DB::table('reservation_orders')
            ->where('reservation_id', $reservationId)
            ->orderBy('order_id')
            ->value('order_id');

        $row = array_merge([
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
            'notes' => null,
        ], $payload);

        if ($existingId !== null) {
            DB::table('reservation_orders')
                ->where('order_id', (int) $existingId)
                ->update(array_merge($row, [
                    'updated_at' => $now,
                ]));

            return (int) $existingId;
        }

        return (int) DB::table('reservation_orders')->insertGetId($row, 'order_id');
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private function replaceOrderItems(int $orderId, array $items): void
    {
        DB::table('kitchen_order_item_tickets')
            ->where('order_id', $orderId)
            ->delete();

        DB::table('reservation_order_items')
            ->where('order_id', $orderId)
            ->delete();

        $now = now('UTC');
        foreach ($items as $item) {
            DB::table('reservation_order_items')->insert([
                'order_id' => $orderId,
                'item_id' => (int) ($item['item_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                'currency' => (string) ($item['currency'] ?? 'VND'),
                'line_total' => (string) ($item['line_total'] ?? '0.00'),
                'item_name_snapshot' => $item['item_name_snapshot'] ?? null,
                'status' => (string) ($item['status'] ?? 'Ordered'),
                'notes' => $item['notes'] ?? null,
                'updated_by' => $item['updated_by'] ?? null,
                'row_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function upsertPayment(array $payload): int
    {
        $now = now('UTC');
        $transactionCode = trim((string) ($payload['transaction_code'] ?? ''));
        $paymentProvider = trim((string) ($payload['payment_provider'] ?? 'Other'));

        $existingId = null;
        if ($transactionCode !== '') {
            $existingId = DB::table('payments')
                ->where('payment_provider', $paymentProvider)
                ->where('transaction_code', $transactionCode)
                ->value('payment_id');
        }

        $row = array_merge([
            'refund_of_payment_id' => null,
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Other',
            'payment_type' => 'Final',
            'status' => 'Pending',
            'transaction_code' => $transactionCode !== '' ? $transactionCode : null,
            'idempotency_key' => null,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'notes' => null,
            'provider_response_json' => null,
            'row_version' => 1,
        ], $payload);

        if ($existingId !== null) {
            DB::table('payments')
                ->where('payment_id', (int) $existingId)
                ->update(array_merge($row, [
                    'updated_at' => $now,
                ]));

            return (int) $existingId;
        }

        return (int) DB::table('payments')->insertGetId($row, 'payment_id');
    }

    private function resolveRoleId(string $roleName): int
    {
        $roleId = DB::table('roles')
            ->whereRaw('LOWER(role_name) = ?', [mb_strtolower(trim($roleName))])
            ->value('role_id');

        if ($roleId !== null) {
            return (int) $roleId;
        }

        throw new \RuntimeException(sprintf('Required role [%s] is missing. Seed reference data first.', $roleName));
    }

    private function roundUpMinutes(Carbon $value, int $minutes): Carbon
    {
        $minutes = max(1, $minutes);
        $rounded = $value->copy()->second(0)->microsecond(0);
        $remainder = ((int) $rounded->minute) % $minutes;

        if ($remainder === 0) {
            return $rounded;
        }

        return $rounded->addMinutes($minutes - $remainder);
    }

    private function resolveManifestPath(?string $manifestPath): string
    {
        $candidate = trim((string) ($manifestPath ?? ''));
        if ($candidate === '') {
            $candidate = self::DEFAULT_MANIFEST_PATH;
        }

        return $this->isAbsolutePath($candidate)
            ? $candidate
            : base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate));
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\\/)/', $path) === 1;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function writeManifest(string $manifestPath, array $manifest): void
    {
        $directory = dirname($manifestPath);
        if (! File::isDirectory($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put(
            $manifestPath,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }
}
