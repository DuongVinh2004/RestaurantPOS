<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Enums\RestaurantTableStatus;
use App\Modules\Billing\Application\Workflows\FinanceTaxProfileWorkflow;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableTemplate;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteBootstrapService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
        private readonly FinanceTaxProfileWorkflow $financeTaxProfileWorkflow,
        private readonly StaffApiKeyStore $staffApiKeyGovernanceService,
    ) {}

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function bootstrap(array $options = []): array
    {
        return DB::transaction(function () use ($options): array {
            app(ReferenceDataSeeder::class)->run();

            $branch = $this->ensureBranch($options);
            $templates = $this->ensureTableTemplates();
            $tables = $this->ensureTables($branch, $templates, $options);
            $menu = $this->ensureMenu((string) $branch->currency);
            $users = $this->ensureBootstrapUsers($options);
            $finance = $this->ensureFinanceProfile((int) $users['admin']->user_id);
            $staffApiKey = $this->ensureStaffApiKey($users['staff'], $options);

            return [
                'branch' => [
                    'branch_id' => (int) $branch->branch_id,
                    'branch_code' => (string) $branch->branch_code,
                    'branch_name' => (string) $branch->branch_name,
                    'currency' => (string) $branch->currency,
                    'timezone' => (string) $branch->timezone,
                ],
                'templates' => array_map(static fn (TableTemplate $template): array => [
                    'template_id' => (int) $template->template_id,
                    'template_code' => (string) $template->template_code,
                    'seats' => (int) $template->seats,
                ], $templates),
                'tables' => [
                    'count' => count($tables),
                    'table_codes' => array_map(static fn (RestaurantTable $table): string => (string) $table->table_code, $tables),
                ],
                'menu' => $menu,
                'finance' => $finance,
                'users' => [
                    'admin' => [
                        'user_id' => (int) $users['admin']->user_id,
                        'username' => (string) $users['admin']->username,
                        'role_id' => (int) $users['admin']->role_id,
                    ],
                    'staff' => [
                        'user_id' => (int) $users['staff']->user_id,
                        'username' => (string) $users['staff']->username,
                        'role_id' => (int) $users['staff']->role_id,
                    ],
                ],
                'staff_api_key' => $staffApiKey,
            ];
        });
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function ensureBranch(array $options): Branch
    {
        $branchCode = strtoupper(trim((string) ($options['branch_code'] ?? config('booking.multi_branch.default_branch_code', 'MAIN'))));
        $branchName = trim((string) ($options['branch_name'] ?? config('booking.multi_branch.default_branch_name', 'Chi nhánh chính')));
        $timezone = trim((string) ($options['timezone'] ?? config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC'))));
        $currency = strtoupper(trim((string) ($options['currency'] ?? config('booking.multi_branch.default_branch_currency', 'VND'))));

        if (! Branch::query()->exists()) {
            /** @var Branch $branch */
            $branch = Branch::query()->create([
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
                'description' => 'Chi nhánh khởi tạo hệ thống.',
                'timezone' => $timezone,
                'currency' => $currency,
                'is_active' => true,
                'is_default' => true,
            ]);

            return $branch->fresh() ?? $branch;
        }

        $this->branchContextService->ensureDefaultBranchExists();

        /** @var Branch $branch */
        $branch = Branch::query()->firstOrCreate(
            ['branch_code' => $branchCode],
            [
                'branch_name' => $branchName,
                'description' => 'Chi nhánh khởi tạo hệ thống.',
                'timezone' => $timezone,
                'currency' => $currency,
                'is_active' => true,
                'is_default' => Branch::query()->count() === 0,
            ]
        );

        if ((int) Branch::query()->count() === 1 && ! (bool) $branch->is_default) {
            $branch->is_default = true;
        }

        if (! (bool) $branch->is_active) {
            $branch->is_active = true;
        }

        if (trim((string) $branch->branch_name) === '') {
            $branch->branch_name = $branchName;
        }

        if (trim((string) $branch->timezone) === '') {
            $branch->timezone = $timezone;
        }

        if (trim((string) $branch->currency) === '') {
            $branch->currency = $currency;
        }

        $branch->save();

        return $branch->fresh() ?? $branch;
    }

    /**
     * @return array<int,TableTemplate>
     */
    private function ensureTableTemplates(): array
    {
        $definitions = [
            ['template_code' => 'BOOT-2P', 'seats' => 2, 'description' => 'Bàn 2 chỗ khởi tạo'],
            ['template_code' => 'BOOT-4P', 'seats' => 4, 'description' => 'Bàn 4 chỗ khởi tạo'],
            ['template_code' => 'BOOT-6P', 'seats' => 6, 'description' => 'Bàn 6 chỗ khởi tạo'],
        ];

        $templates = [];
        foreach ($definitions as $definition) {
            /** @var TableTemplate $template */
            $template = TableTemplate::query()->firstOrCreate(
                ['template_code' => $definition['template_code']],
                $definition,
            );
            $templates[(int) $template->seats] = $template->fresh() ?? $template;
        }

        return array_values($templates);
    }

    /**
     * @param  array<int,TableTemplate>  $templates
     * @param  array<string,mixed>  $options
     * @return array<int,RestaurantTable>
     */
    private function ensureTables(Branch $branch, array $templates, array $options): array
    {
        $zones = $this->normalizeZones($options['zones'] ?? 'Khu A,Khu B');
        $tablesPerZone = max(1, (int) ($options['tables_per_zone'] ?? 4));
        $seatPattern = [2, 4, 4, 6];
        $created = [];

        foreach ($zones as $zoneIndex => $zone) {
            $zoneCode = $this->zoneCode($zone, $zoneIndex + 1);

            for ($position = 1; $position <= $tablesPerZone; $position++) {
                $seats = $seatPattern[($position - 1) % count($seatPattern)];
                $template = $this->resolveTemplateBySeats($templates, $seats);
                $tableCode = sprintf('%s-%s-%02d', (string) $branch->branch_code, $zoneCode, $position);

                /** @var RestaurantTable $table */
                $table = RestaurantTable::query()->firstOrCreate(
                    ['table_code' => $tableCode],
                    [
                        'branch_id' => (int) $branch->branch_id,
                        'template_id' => (int) $template->template_id,
                        'zone' => $zone,
                        'pos_x' => $position,
                        'pos_y' => $zoneIndex + 1,
                        'status' => RestaurantTableStatus::Available->value,
                        'description' => sprintf('Bàn %d chỗ tại %s.', $seats, $zone),
                        'is_deleted' => false,
                        'price' => null,
                    ]
                );

                if ((int) $table->branch_id !== (int) $branch->branch_id) {
                    throw ValidationException::withMessages([
                        'table_code' => [sprintf('Existing table [%s] belongs to another branch and cannot be reused by bootstrap.', $tableCode)],
                    ]);
                }

                $created[] = $table->fresh() ?? $table;
            }
        }

        return $created;
    }

    /**
     * @return array<string,mixed>
     */
    private function ensureMenu(string $currency): array
    {
        $definitions = [
            [
                'category' => ['name' => 'Đồ uống', 'description' => 'Đồ uống khởi tạo', 'sort_order' => 10, 'aliases' => ['Do uong']],
                'items' => [
                    ['code' => 'BOOT-WATER', 'name' => 'Trà đá', 'description' => 'Trà đá mát lạnh dùng kèm bữa ăn.', 'price' => 10000, 'preorder_quota_per_day' => 200],
                    ['code' => 'BOOT-PEACH-TEA', 'name' => 'Trà đào cam sả', 'description' => 'Trà đào thơm nhẹ, dùng cùng cam vàng và sả tươi.', 'price' => 45000, 'preorder_quota_per_day' => 120],
                    ['code' => 'BOOT-LEMONGRASS-LIME', 'name' => 'Nước chanh sả', 'description' => 'Nước chanh tươi pha sả, vị chua ngọt cân bằng.', 'price' => 39000, 'preorder_quota_per_day' => 120],
                    ['code' => 'BOOT-SALTED-PLUM-SODA', 'name' => 'Soda xí muội', 'description' => 'Soda mát lạnh cùng xí muội và lát chanh tươi.', 'price' => 42000, 'preorder_quota_per_day' => 100],
                    ['code' => 'BOOT-FRESH-ORANGE', 'name' => 'Cam ép tươi', 'description' => 'Cam ép nguyên chất, phục vụ lạnh.', 'price' => 55000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-MINERAL-WATER', 'name' => 'Nước suối', 'description' => 'Nước suối đóng chai dùng kèm bữa ăn.', 'price' => 15000, 'preorder_quota_per_day' => 240],
                ],
            ],
            [
                'category' => ['name' => 'Món chính', 'description' => 'Các món chính khởi tạo', 'sort_order' => 20, 'aliases' => ['Mon chinh']],
                'items' => [
                    ['code' => 'BOOT-FRIED-RICE', 'name' => 'Cơm chiên hải sản', 'description' => 'Cơm chiên cùng tôm, mực, trứng và rau củ.', 'price' => 89000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-NOODLE-BOWL', 'name' => 'Bún xào bò', 'description' => 'Bún xào bò mềm với rau cải và nước sốt đậm vị.', 'price' => 79000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-SHAKING-BEEF', 'name' => 'Bò lúc lắc', 'description' => 'Bò áp chảo sốt tiêu đen, dùng cùng khoai tây và salad.', 'price' => 129000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-GRILLED-CHICKEN', 'name' => 'Gà nướng mật ong', 'description' => 'Đùi gà nướng mật ong, da giòn nhẹ và sốt cay ngọt.', 'price' => 98000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-CARAMEL-PORK', 'name' => 'Thịt kho tiêu', 'description' => 'Thịt ba chỉ kho tiêu đậm vị, dùng cùng cơm trắng.', 'price' => 86000, 'preorder_quota_per_day' => 60],
                    ['code' => 'BOOT-SEAFOOD-NOODLES', 'name' => 'Mì xào hải sản', 'description' => 'Mì xào cùng tôm, mực, rau cải và sốt dầu hào.', 'price' => 92000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-BASIL-BEEF', 'name' => 'Bò xào húng quế', 'description' => 'Bò xào nhanh với húng quế, ớt chuông và hành tây.', 'price' => 99000, 'preorder_quota_per_day' => 60],
                ],
            ],
            [
                'category' => ['name' => 'Khai vị', 'description' => 'Món khai vị dễ chia sẻ', 'sort_order' => 30, 'aliases' => ['Khai vi']],
                'items' => [
                    ['code' => 'BOOT-SPRING-ROLLS', 'name' => 'Gỏi cuốn tôm thịt', 'description' => 'Gỏi cuốn tôm thịt, rau thơm và nước chấm đậu phộng.', 'price' => 69000, 'preorder_quota_per_day' => 90],
                    ['code' => 'BOOT-FRIED-CALAMARI', 'name' => 'Mực chiên giòn', 'description' => 'Mực vòng chiên giòn, dùng cùng sốt tartar.', 'price' => 89000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-CHICKEN-SALAD', 'name' => 'Gỏi gà hoa chuối', 'description' => 'Gà xé trộn hoa chuối, rau răm và nước mắm chua ngọt.', 'price' => 76000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-SALTED-EGG-CORN', 'name' => 'Bắp xào trứng muối', 'description' => 'Bắp Mỹ xào bơ, trứng muối và hành lá.', 'price' => 59000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-SHRIMP-TOAST', 'name' => 'Bánh mì tôm chiên', 'description' => 'Bánh mì phủ tôm quết, chiên giòn và dùng cùng sốt cay.', 'price' => 72000, 'preorder_quota_per_day' => 70],
                ],
            ],
            [
                'category' => ['name' => 'Cơm và mì', 'description' => 'Các món cơm, mì và phần ăn nhanh', 'sort_order' => 40, 'aliases' => ['Com va mi']],
                'items' => [
                    ['code' => 'BOOT-BEEF-RICE-BOWL', 'name' => 'Cơm bò sốt nấm', 'description' => 'Cơm trắng dùng cùng bò xào nấm và sốt nâu.', 'price' => 95000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-CHICKEN-RICE', 'name' => 'Cơm gà xối mỡ', 'description' => 'Gà xối mỡ da giòn, cơm thơm và đồ chua.', 'price' => 88000, 'preorder_quota_per_day' => 80],
                    ['code' => 'BOOT-VEG-FRIED-NOODLES', 'name' => 'Mì xào rau củ', 'description' => 'Mì xào cùng cải thìa, nấm, cà rốt và sốt mè.', 'price' => 69000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-SEAFOOD-UDON', 'name' => 'Udon hải sản áp chảo', 'description' => 'Udon áp chảo cùng tôm, mực và sốt tương Nhật.', 'price' => 105000, 'preorder_quota_per_day' => 60],
                    ['code' => 'BOOT-PORK-RIB-RICE', 'name' => 'Cơm sườn nướng', 'description' => 'Sườn nướng mật ong, cơm tấm, trứng ốp và nước mắm.', 'price' => 94000, 'preorder_quota_per_day' => 80],
                ],
            ],
            [
                'category' => ['name' => 'Lẩu và món chia sẻ', 'description' => 'Món phần lớn dành cho nhóm', 'sort_order' => 50, 'aliases' => ['Lau va mon chia se']],
                'items' => [
                    ['code' => 'BOOT-THAI-HOTPOT', 'name' => 'Lẩu Thái hải sản', 'description' => 'Nước lẩu Thái chua cay, tôm, mực, nghêu và rau nấm.', 'price' => 329000, 'preorder_quota_per_day' => 35, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'BOOT-SEAFOOD-HOTPOT', 'name' => 'Lẩu nấm hải sản', 'description' => 'Nước dùng thanh, hải sản tươi và các loại nấm.', 'price' => 349000, 'preorder_quota_per_day' => 30, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'BOOT-BEEF-HOTPOT', 'name' => 'Lẩu bò sa tế', 'description' => 'Lẩu bò sa tế cay nhẹ, dùng cùng rau xanh và mì trứng.', 'price' => 319000, 'preorder_quota_per_day' => 35, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'BOOT-GRILLED-SEAFOOD-PLATTER', 'name' => 'Hải sản nướng tổng hợp', 'description' => 'Tôm, mực, sò điệp và cá nướng cho nhóm 3-4 khách.', 'price' => 389000, 'preorder_quota_per_day' => 25, 'preorder_cutoff_minutes' => 45],
                    ['code' => 'BOOT-FAMILY-COMBO', 'name' => 'Mâm gia đình', 'description' => 'Combo 4 món mặn, canh và rau cho nhóm gia đình.', 'price' => 459000, 'preorder_quota_per_day' => 25, 'preorder_cutoff_minutes' => 45],
                ],
            ],
            [
                'category' => ['name' => 'Tráng miệng', 'description' => 'Món ngọt sau bữa ăn', 'sort_order' => 60, 'aliases' => ['Trang mieng']],
                'items' => [
                    ['code' => 'BOOT-FLAN', 'name' => 'Bánh flan caramel', 'description' => 'Flan trứng sữa mềm mịn cùng caramel đậm vị.', 'price' => 39000, 'preorder_quota_per_day' => 90],
                    ['code' => 'BOOT-PANNA-COTTA', 'name' => 'Panna cotta dâu', 'description' => 'Panna cotta kem sữa dùng cùng sốt dâu tươi.', 'price' => 52000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-FRUIT-PLATE', 'name' => 'Đĩa trái cây theo mùa', 'description' => 'Trái cây tươi cắt sẵn, phù hợp chia sẻ sau bữa ăn.', 'price' => 69000, 'preorder_quota_per_day' => 70],
                    ['code' => 'BOOT-CHOCOLATE-MOUSSE', 'name' => 'Mousse chocolate', 'description' => 'Mousse chocolate đen, kem tươi và vụn hạnh nhân.', 'price' => 59000, 'preorder_quota_per_day' => 60],
                ],
            ],
            [
                'category' => ['name' => 'Combo đặt trước', 'description' => 'Combo chuẩn bị sẵn cho bàn đặt trước', 'sort_order' => 70, 'aliases' => ['Combo dat truoc']],
                'items' => [
                    ['code' => 'BOOT-COUPLE-COMBO', 'name' => 'Combo hẹn hò 2 người', 'description' => 'Set khai vị, hai món chính, hai đồ uống và tráng miệng.', 'price' => 399000, 'preorder_quota_per_day' => 30, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'BOOT-GROUP-COMBO-4', 'name' => 'Combo nhóm 4 người', 'description' => 'Set chia sẻ gồm khai vị, món chính, lẩu nhỏ và đồ uống.', 'price' => 799000, 'preorder_quota_per_day' => 25, 'preorder_cutoff_minutes' => 45],
                    ['code' => 'BOOT-BIRTHDAY-SET', 'name' => 'Set sinh nhật', 'description' => 'Combo món ăn nhóm kèm bánh flan và trang trí bàn cơ bản.', 'price' => 959000, 'preorder_quota_per_day' => 18, 'preorder_cutoff_minutes' => 60],
                    ['code' => 'BOOT-KIDS-SET', 'name' => 'Set trẻ em', 'description' => 'Phần ăn nhẹ cho trẻ gồm gà, khoai, nước ép và bánh ngọt.', 'price' => 149000, 'preorder_quota_per_day' => 40, 'preorder_cutoff_minutes' => 30],
                ],
            ],
        ];

        $categoryCount = 0;
        $itemCount = 0;
        $priceCount = 0;
        $effectiveFrom = now('UTC')->startOfDay();

        foreach ($definitions as $definition) {
            /** @var MenuCategory $category */
            $categoryDefinition = $definition['category'];
            $category = MenuCategory::query()
                ->where(function ($query) use ($categoryDefinition): void {
                    $query->where('name', $categoryDefinition['name']);

                    if (($categoryDefinition['aliases'] ?? []) !== []) {
                        $query->orWhereIn('name', $categoryDefinition['aliases']);
                    }
                })
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$categoryDefinition['name']])
                ->first();

            if (! $category instanceof MenuCategory) {
                $category = new MenuCategory;
            }

            $category->fill([
                'name' => $categoryDefinition['name'],
                'description' => $categoryDefinition['description'],
                'sort_order' => $categoryDefinition['sort_order'],
                'is_deleted' => false,
            ]);
            $category->save();
            $categoryCount++;

            foreach ($definition['items'] as $itemDefinition) {
                /** @var MenuItem $item */
                $item = MenuItem::query()->updateOrCreate(
                    ['code' => $itemDefinition['code']],
                    [
                        'category_id' => (int) $category->category_id,
                        'name' => $itemDefinition['name'],
                        'description' => $itemDefinition['description'],
                        'img_url' => null,
                        'is_available' => true,
                        'is_preorder_enabled' => (bool) ($itemDefinition['is_preorder_enabled'] ?? true),
                        'preorder_quota_per_day' => $itemDefinition['preorder_quota_per_day'] ?? null,
                        'preorder_cutoff_minutes' => (int) ($itemDefinition['preorder_cutoff_minutes'] ?? 0),
                    ]
                );
                $itemCount++;

                $existingPrice = MenuItemPrice::query()
                    ->where('item_id', (int) $item->item_id)
                    ->where('currency', strtoupper($currency))
                    ->whereNull('effective_to')
                    ->orderByDesc('effective_from')
                    ->orderByDesc('price_id')
                    ->first();

                if (! $existingPrice instanceof MenuItemPrice) {
                    MenuItemPrice::query()->create([
                        'item_id' => (int) $item->item_id,
                        'price' => $itemDefinition['price'],
                        'currency' => strtoupper($currency),
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                    ]);
                }

                $priceCount++;
            }
        }

        return [
            'category_count' => $categoryCount,
            'item_count' => $itemCount,
            'price_count' => $priceCount,
            'currency' => strtoupper($currency),
        ];
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array{admin:User,staff:User}
     */
    private function ensureBootstrapUsers(array $options): array
    {
        $admin = $this->ensureBootstrapUser(
            username: trim((string) ($options['admin_username'] ?? 'bootstrap-admin')),
            fullName: trim((string) ($options['admin_name'] ?? 'Quản trị khởi tạo')),
            roleId: 1,
        );
        $staff = $this->ensureBootstrapUser(
            username: trim((string) ($options['staff_username'] ?? 'bootstrap-staff')),
            fullName: trim((string) ($options['staff_name'] ?? 'Nhân viên khởi tạo')),
            roleId: 2,
        );

        return [
            'admin' => $admin,
            'staff' => $staff,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function ensureFinanceProfile(int $adminUserId): array
    {
        $description = $this->financeTaxProfileWorkflow->describe();
        if (($description['runtime_profile'] ?? null) === null) {
            $description = $this->financeTaxProfileWorkflow->upsert(
                (array) config('booking.finance_tax_invoice_profile', []),
                $adminUserId,
            );

            return [
                'action' => 'created',
                'profile' => $description['effective_profile'] ?? null,
            ];
        }

        return [
            'action' => 'existing',
            'profile' => $description['effective_profile'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    private function ensureStaffApiKey(User $staffUser, array $options): array
    {
        if ((bool) ($options['skip_staff_key'] ?? false)) {
            return [
                'action' => 'skipped',
                'plaintext_key' => null,
                'record' => null,
            ];
        }

        /** @var StaffApiKey|null $active */
        $active = StaffApiKey::query()
            ->with('user.role')
            ->active()
            ->where('user_id', (int) $staffUser->user_id)
            ->orderByDesc('created_at')
            ->orderByDesc('staff_api_key_id')
            ->first();

        $expiresAt = now('UTC')->addDays(max(1, (int) ($options['staff_key_ttl_days'] ?? 90)));
        $label = trim((string) ($options['staff_key_label'] ?? 'Khóa API nhân viên khởi tạo'));

        if ($active instanceof StaffApiKey && ! (bool) ($options['rotate_staff_key'] ?? false)) {
            return [
                'action' => 'existing',
                'plaintext_key' => null,
                'record' => $active,
            ];
        }

        if ($active instanceof StaffApiKey) {
            $rotated = $this->staffApiKeyGovernanceService->rotateKey(
                (int) $active->staff_api_key_id,
                $label,
                $expiresAt,
            );

            return [
                'action' => 'rotated',
                'plaintext_key' => $rotated['plaintext_key'],
                'record' => $rotated['record'],
            ];
        }

        $issued = $this->staffApiKeyGovernanceService->issueKey((int) $staffUser->user_id, $label, $expiresAt);

        return [
            'action' => 'issued',
            'plaintext_key' => $issued['plaintext_key'],
            'record' => $issued['record'],
        ];
    }

    private function ensureBootstrapUser(string $username, string $fullName, int $roleId): User
    {
        $username = trim($username);
        if ($username === '') {
            throw ValidationException::withMessages([
                'username' => ['Bootstrap username must not be empty.'],
            ]);
        }

        /** @var User|null $existing */
        $existing = User::query()->where('username', $username)->first();
        if ($existing instanceof User) {
            if ((bool) $existing->is_deleted) {
                throw ValidationException::withMessages([
                    'username' => [sprintf('Bootstrap user [%s] exists but is deleted.', $username)],
                ]);
            }

            if ((int) $existing->role_id !== $roleId) {
                throw ValidationException::withMessages([
                    'username' => [sprintf('Bootstrap user [%s] already exists with a different role.', $username)],
                ]);
            }

            if (trim((string) $existing->full_name) === '' && $fullName !== '') {
                $existing->full_name = $fullName;
                $existing->save();
            }

            return $existing->fresh() ?? $existing;
        }

        /** @var User $user */
        $user = User::query()->create([
            'username' => $username,
            'full_name' => $fullName !== '' ? $fullName : Str::headline(str_replace(['-', '_'], ' ', $username)),
            'email' => null,
            'phone' => null,
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => false,
        ]);

        return $user->fresh() ?? $user;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeZones(mixed $value): array
    {
        $zones = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($value) ? $value : explode(',', (string) $value)
        ), static fn (string $item): bool => $item !== ''));

        return $zones !== [] ? $zones : ['Khu A'];
    }

    /**
     * @param  array<int,TableTemplate>  $templates
     */
    private function resolveTemplateBySeats(array $templates, int $seats): TableTemplate
    {
        foreach ($templates as $template) {
            if ((int) $template->seats === $seats) {
                return $template;
            }
        }

        throw ValidationException::withMessages([
            'table_templates' => [sprintf('Bootstrap table template for %d seats is missing.', $seats)],
        ]);
    }

    private function zoneCode(string $zone, int $fallbackIndex): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', Str::ascii($zone)) ?? '');
        $normalized = trim($normalized, '-');
        if ($normalized === '') {
            $normalized = 'ZONE'.$fallbackIndex;
        }

        return Str::limit($normalized, 12, '');
    }
}
