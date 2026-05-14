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
            ['template_code' => 'MS-2P', 'aliases' => ['BOOT-2P'], 'seats' => 2, 'description' => 'Bàn 2 chỗ tiêu chuẩn Mộc Sen'],
            ['template_code' => 'MS-4P', 'aliases' => ['BOOT-4P'], 'seats' => 4, 'description' => 'Bàn 4 chỗ tiêu chuẩn Mộc Sen'],
            ['template_code' => 'MS-6P', 'aliases' => ['BOOT-6P'], 'seats' => 6, 'description' => 'Bàn 6 chỗ tiêu chuẩn Mộc Sen'],
        ];

        $templates = [];
        foreach ($definitions as $definition) {
            /** @var TableTemplate $template */
            $template = TableTemplate::query()
                ->where('template_code', $definition['template_code'])
                ->orWhereIn('template_code', $definition['aliases'])
                ->orderByRaw('CASE WHEN template_code = ? THEN 0 ELSE 1 END', [$definition['template_code']])
                ->first();

            if (! $template instanceof TableTemplate) {
                $template = new TableTemplate;
            }

            $template->fill([
                'template_code' => $definition['template_code'],
                'seats' => $definition['seats'],
                'description' => $definition['description'],
            ]);
            $template->save();

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
        $zones = $this->normalizeZones($options['zones'] ?? 'Main Hall,Window Zone,Garden Corner,Private Room');
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
                        'description' => sprintf('Bàn %d chỗ tại khu %s.', $seats, $zone),
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
                'category' => ['name' => 'Khai vị', 'description' => 'Món mở đầu nhẹ, dễ chia sẻ tại Mộc Sen.', 'sort_order' => 10, 'aliases' => ['Khai vi']],
                'items' => [
                    ['code' => 'MS-GOI-CUON-TOM-THIT', 'name' => 'Gỏi cuốn tôm thịt', 'description' => 'Tôm, thịt mềm, rau sống, bún mảnh, sốt đậu phộng.', 'price' => 59000, 'img_url' => '/customer-web/menu/goi-cuon-tom-thit.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-NEM-SEN-GION', 'name' => 'Nem sen giòn', 'description' => 'Nem chiên giòn nhân thịt, nấm, miến và củ sen.', 'price' => 69000, 'img_url' => '/customer-web/menu/nem-sen-gion.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-SALAD-XOAI-TOM', 'name' => 'Salad xoài tôm', 'description' => 'Xoài xanh, tôm áp chảo, rau thơm, sốt chua ngọt.', 'price' => 79000, 'img_url' => '/customer-web/menu/salad-xoai-tom.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-CHA-MUC-MINI', 'name' => 'Chả mực mini', 'description' => 'Chả mực giã tay, chiên vàng, dùng kèm tương ớt Mộc Sen.', 'price' => 89000, 'img_url' => '/customer-web/menu/cha-muc-mini.jpg', 'preorder_quota_per_day' => 60],
                    ['code' => 'MS-DAU-HU-RANG-MUOI', 'name' => 'Đậu hũ rang muối', 'description' => 'Đậu hũ non áo bột mỏng, rang muối sả giòn.', 'price' => 55000, 'img_url' => '/customer-web/menu/dau-hu-rang-muoi.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-GOI-GA-BAP-CHUOI', 'name' => 'Gỏi gà bắp chuối', 'description' => 'Gà xé, bắp chuối, rau răm, hành phi và nước mắm chua ngọt.', 'price' => 76000, 'img_url' => '/customer-web/menu/goi-ga-bap-chuoi.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-CHA-GIO-HAI-SAN', 'name' => 'Chả giò hải sản', 'description' => 'Cuốn hải sản chiên giòn, dùng kèm rau sống và sốt Mộc Sen.', 'price' => 89000, 'img_url' => '/customer-web/menu/cha-gio-hai-san.jpg', 'preorder_quota_per_day' => 60],
                ],
            ],
            [
                'category' => ['name' => 'Món chính', 'description' => 'Các món Việt đậm vị cho bữa chính.', 'sort_order' => 20, 'aliases' => ['Mon chinh']],
                'items' => [
                    ['code' => 'MS-COM-GA-LA-SEN', 'name' => 'Cơm gà lá sen', 'description' => 'Gà áp chảo, cơm dẻo, sốt gừng nhẹ, rau củ theo mùa.', 'price' => 89000, 'img_url' => '/customer-web/menu/com-ga-la-sen.jpg', 'preorder_quota_per_day' => 100],
                    ['code' => 'MS-BUN-BO-MOC-SEN', 'name' => 'Bún bò Mộc Sen', 'description' => 'Nước dùng đậm vị, thịt bò mềm, rau thơm và sa tế nhẹ.', 'price' => 95000, 'img_url' => '/customer-web/menu/bun-bo-moc-sen.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-CA-KHO-NIEU-DAT', 'name' => 'Cá kho niêu đất', 'description' => 'Cá kho tiêu, nước màu truyền thống, ăn kèm cơm trắng.', 'price' => 119000, 'img_url' => '/customer-web/menu/ca-kho-nieu-dat.jpg', 'preorder_quota_per_day' => 60],
                    ['code' => 'MS-BO-LUC-LAC-SOT-TIEU', 'name' => 'Bò lúc lắc sốt tiêu', 'description' => 'Bò mềm áp chảo, khoai tây, salad và sốt tiêu đen.', 'price' => 139000, 'img_url' => '/customer-web/menu/bo-luc-lac-sot-tieu.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-GA-NUONG-MAT-ONG', 'name' => 'Gà nướng mật ong', 'description' => 'Gà nướng vàng, mật ong nhẹ, rau củ nướng.', 'price' => 129000, 'img_url' => '/customer-web/menu/ga-nuong-mat-ong.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-TOM-SOT-ME', 'name' => 'Tôm sốt me', 'description' => 'Tôm áp chảo, sốt me chua ngọt, hành phi.', 'price' => 149000, 'img_url' => '/customer-web/menu/tom-sot-me.jpg', 'preorder_quota_per_day' => 60],
                    ['code' => 'MS-SUON-NON-RIM-MAM', 'name' => 'Sườn non rim mắm', 'description' => 'Sườn non rim mắm tỏi, ăn kèm dưa leo và cơm.', 'price' => 129000, 'img_url' => '/customer-web/menu/suon-non-rim-mam.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-VIT-AP-CHAO-SOT-ME', 'name' => 'Vịt áp chảo sốt me', 'description' => 'Vịt áp chảo da giòn, sốt me chua ngọt và rau thơm.', 'price' => 159000, 'img_url' => '/customer-web/menu/vit-ap-chao-sot-me.jpg', 'preorder_quota_per_day' => 50],
                    ['code' => 'MS-CA-CHIEN-MAM-XOAI', 'name' => 'Cá chiên mắm xoài', 'description' => 'Cá chiên giòn, mắm xoài xanh và rau sống ăn kèm.', 'price' => 149000, 'img_url' => '/customer-web/menu/ca-chien-mam-xoai.jpg', 'preorder_quota_per_day' => 55],
                    ['code' => 'MS-BO-KHO-BANH-MI', 'name' => 'Bò kho bánh mì', 'description' => 'Bò kho mềm, cà rốt, nước sốt thơm và bánh mì nóng.', 'price' => 99000, 'img_url' => '/customer-web/menu/bo-kho-banh-mi.jpg', 'preorder_quota_per_day' => 70],
                ],
            ],
            [
                'category' => ['name' => 'Cơm & bún/phở', 'description' => 'Các phần ăn quen thuộc cho trưa văn phòng và bữa tối nhanh.', 'sort_order' => 30, 'aliases' => ['Com va mi', 'Cơm và mì']],
                'items' => [
                    ['code' => 'MS-PHO-GA-THAO-MOC', 'name' => 'Phở gà thảo mộc', 'description' => 'Nước dùng thanh, gà xé, rau thơm và bánh phở mềm.', 'price' => 79000, 'img_url' => '/customer-web/menu/pho-ga-thao-moc.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-BUN-CHA-HA-NOI', 'name' => 'Bún chả Hà Nội', 'description' => 'Thịt nướng than, nước chấm chua ngọt, bún và rau sống.', 'price' => 89000, 'img_url' => '/customer-web/menu/bun-cha-ha-noi.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-COM-SUON-MAT-ONG', 'name' => 'Cơm sườn mật ong', 'description' => 'Sườn nướng mật ong, cơm trắng, trứng và đồ chua.', 'price' => 99000, 'img_url' => '/customer-web/menu/com-suon-mat-ong.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-MI-XAO-BO-RAU-CU', 'name' => 'Mì xào bò rau củ', 'description' => 'Mì xào, bò mềm, rau củ giòn và sốt hài hòa.', 'price' => 92000, 'img_url' => '/customer-web/menu/mi-xao-bo-rau-cu.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-BUN-THIT-NUONG', 'name' => 'Bún thịt nướng', 'description' => 'Thịt nướng, bún, rau sống, đồ chua và nước mắm.', 'price' => 85000, 'img_url' => '/customer-web/menu/bun-thit-nuong.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-COM-BO-XAO-SATE', 'name' => 'Cơm bò xào sa tế', 'description' => 'Bò xào sa tế cay nhẹ, cơm trắng, dưa leo và đồ chua.', 'price' => 109000, 'img_url' => '/customer-web/menu/com-bo-xao-sate.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-MIEN-GA-NAM', 'name' => 'Miến gà nấm', 'description' => 'Miến dai, gà xé, nấm hương và nước dùng thanh.', 'price' => 79000, 'img_url' => '/customer-web/menu/mien-ga-nam.jpg', 'preorder_quota_per_day' => 75],
                ],
            ],
            [
                'category' => ['name' => 'Rau & chay', 'description' => 'Món rau, nấm và lựa chọn chay nhẹ.', 'sort_order' => 40, 'aliases' => ['Rau va chay']],
                'items' => [
                    ['code' => 'MS-RAU-CU-XAO-TOI', 'name' => 'Rau củ xào tỏi', 'description' => 'Rau củ theo mùa xào tỏi thơm, giữ độ giòn.', 'price' => 55000, 'img_url' => '/customer-web/menu/rau-cu-xao-toi.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-DAU-HU-SOT-NAM', 'name' => 'Đậu hũ sốt nấm', 'description' => 'Đậu hũ non, nấm đông cô, sốt thanh nhẹ.', 'price' => 65000, 'img_url' => '/customer-web/menu/dau-hu-sot-nam.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-NAM-KHO-TIEU', 'name' => 'Nấm kho tiêu', 'description' => 'Nấm kho tiêu, hành boa-rô, ăn kèm cơm nóng.', 'price' => 69000, 'img_url' => '/customer-web/menu/nam-kho-tieu.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-CANH-RAU-CU-HAT-SEN', 'name' => 'Canh rau củ hạt sen', 'description' => 'Canh rau củ, hạt sen, nước dùng rau củ nhẹ.', 'price' => 59000, 'img_url' => '/customer-web/menu/canh-rau-cu-hat-sen.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-GOI-RAU-MAM-BO', 'name' => 'Gỏi rau mầm bò', 'description' => 'Rau mầm, bò áp chảo, sốt mè rang.', 'price' => 89000, 'img_url' => '/customer-web/menu/goi-rau-mam-bo.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-CA-TIM-NUONG-MO-HANH', 'name' => 'Cà tím nướng mỡ hành', 'description' => 'Cà tím nướng mềm, mỡ hành, đậu phộng và nước mắm chay.', 'price' => 59000, 'img_url' => '/customer-web/menu/ca-tim-nuong-mo-hanh.jpg', 'preorder_quota_per_day' => 70],
                    ['code' => 'MS-DAU-BAP-XAO-TOI', 'name' => 'Đậu bắp xào tỏi', 'description' => 'Đậu bắp xào tỏi nhanh lửa, giữ độ giòn và vị ngọt tự nhiên.', 'price' => 52000, 'img_url' => '/customer-web/menu/dau-bap-xao-toi.jpg', 'preorder_quota_per_day' => 75],
                ],
            ],
            [
                'category' => ['name' => 'Tráng miệng', 'description' => 'Món ngọt nhẹ sau bữa ăn.', 'sort_order' => 50, 'aliases' => ['Trang mieng']],
                'items' => [
                    ['code' => 'MS-CHE-SEN-LONG-NHAN', 'name' => 'Chè sen long nhãn', 'description' => 'Hạt sen mềm, long nhãn ngọt thanh, dùng lạnh.', 'price' => 45000, 'img_url' => '/customer-web/menu/che-sen-long-nhan.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-PANNA-COTTA-DUA', 'name' => 'Panna cotta dừa', 'description' => 'Kem dừa mềm mịn, sốt xoài chua nhẹ.', 'price' => 49000, 'img_url' => '/customer-web/menu/panna-cotta-dua.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-BANH-FLAN-CA-PHE', 'name' => 'Bánh flan cà phê', 'description' => 'Flan mềm, caramel, cà phê đậm nhẹ.', 'price' => 42000, 'img_url' => '/customer-web/menu/banh-flan-ca-phe.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-KEM-DUA-NON', 'name' => 'Kem dừa non', 'description' => 'Kem dừa, dừa non, đậu phộng rang.', 'price' => 55000, 'img_url' => '/customer-web/menu/kem-dua-non.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-SUA-CHUA-NEP-CAM', 'name' => 'Sữa chua nếp cẩm', 'description' => 'Sữa chua mịn, nếp cẩm dẻo, vị ngọt dịu.', 'price' => 45000, 'img_url' => '/customer-web/menu/sua-chua-nep-cam.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-BANH-CHUOI-NUONG', 'name' => 'Bánh chuối nướng', 'description' => 'Chuối chín nướng thơm, nước cốt dừa và mè rang.', 'price' => 49000, 'img_url' => '/customer-web/menu/banh-chuoi-nuong.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-TAU-HU-NUOC-DUONG', 'name' => 'Tàu hũ nước đường', 'description' => 'Tàu hũ mềm, nước đường gừng và trân châu nhỏ.', 'price' => 39000, 'img_url' => '/customer-web/menu/tau-hu-nuoc-duong.jpg', 'preorder_quota_per_day' => 90],
                ],
            ],
            [
                'category' => ['name' => 'Đồ uống', 'description' => 'Đồ uống thanh mát đi cùng bữa Việt.', 'sort_order' => 60, 'aliases' => ['Do uong']],
                'items' => [
                    ['code' => 'MS-TRA-SEN-LANH', 'name' => 'Trà sen lạnh', 'description' => 'Trà sen thơm nhẹ, vị thanh, ít ngọt.', 'price' => 35000, 'img_url' => '/customer-web/menu/tra-sen-lanh.jpg', 'preorder_quota_per_day' => 120],
                    ['code' => 'MS-NUOC-EP-CAM-CA-ROT', 'name' => 'Nước ép cam cà rốt', 'description' => 'Cam tươi và cà rốt ép lạnh.', 'price' => 49000, 'img_url' => '/customer-web/menu/nuoc-ep-cam-ca-rot.jpg', 'preorder_quota_per_day' => 90],
                    ['code' => 'MS-CA-PHE-SUA-DA', 'name' => 'Cà phê sữa đá', 'description' => 'Cà phê rang đậm, sữa đặc, đá viên.', 'price' => 39000, 'img_url' => '/customer-web/menu/ca-phe-sua-da.jpg', 'preorder_quota_per_day' => 100],
                    ['code' => 'MS-NUOC-CHANH-SA', 'name' => 'Nước chanh sả', 'description' => 'Chanh tươi, sả, mật ong nhẹ.', 'price' => 39000, 'img_url' => '/customer-web/menu/nuoc-chanh-sa.jpg', 'preorder_quota_per_day' => 100],
                    ['code' => 'MS-SINH-TO-XOAI', 'name' => 'Sinh tố xoài', 'description' => 'Xoài chín, sữa chua, đá xay.', 'price' => 55000, 'img_url' => '/customer-web/menu/sinh-to-xoai.jpg', 'preorder_quota_per_day' => 80],
                    ['code' => 'MS-TRA-TAC-MAT-ONG', 'name' => 'Trà tắc mật ong', 'description' => 'Trà tắc mát, mật ong nhẹ và lát tắc tươi.', 'price' => 39000, 'img_url' => '/customer-web/menu/tra-tac-mat-ong.jpg', 'preorder_quota_per_day' => 100],
                ],
            ],
            [
                'category' => ['name' => 'Combo', 'description' => 'Set món giúp khách chọn nhanh theo dịp.', 'sort_order' => 70, 'aliases' => ['Combo dat truoc', 'Combo đặt trước']],
                'items' => [
                    ['code' => 'MS-SET-TRUA-VAN-PHONG', 'name' => 'Set trưa văn phòng', 'description' => 'Món chính + canh nhỏ + trà sen.', 'price' => 149000, 'img_url' => '/customer-web/menu/set-trua-van-phong.jpg', 'preorder_quota_per_day' => 70, 'preorder_cutoff_minutes' => 15],
                    ['code' => 'MS-SET-GIA-DINH-MOC-SEN', 'name' => 'Set gia đình Mộc Sen', 'description' => '4 món chính, 1 rau, 1 tráng miệng.', 'price' => 399000, 'img_url' => '/customer-web/menu/set-gia-dinh-moc-sen.jpg', 'preorder_quota_per_day' => 40, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'MS-SET-HEN-HO-BEN-CUA-SO', 'name' => 'Set hẹn hò bên cửa sổ', 'description' => 'Khai vị, 2 món chính, 2 đồ uống, 1 tráng miệng.', 'price' => 299000, 'img_url' => '/customer-web/menu/set-hen-ho-ben-cua-so.jpg', 'preorder_quota_per_day' => 35, 'preorder_cutoff_minutes' => 30],
                    ['code' => 'MS-SET-BEP-TRUONG-DE-XUAT', 'name' => 'Set bếp trưởng đề xuất', 'description' => 'Combo 5 món theo mùa cho nhóm 4 khách, cân bằng khai vị, món chính và tráng miệng.', 'price' => 459000, 'img_url' => '/customer-web/menu/set-bep-truong-de-xuat.jpg', 'preorder_quota_per_day' => 30, 'preorder_cutoff_minutes' => 30],
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
                        'img_url' => $itemDefinition['img_url'] ?? null,
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
