<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\BranchScheduling\Domain\Models\TableTemplate;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Billing\Application\Workflows\FinanceTaxProfileWorkflow;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteBootstrapService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
        private readonly FinanceTaxProfileService $financeTaxProfileService,
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
        $branchName = trim((string) ($options['branch_name'] ?? config('booking.multi_branch.default_branch_name', 'Chi nhanh chinh')));
        $timezone = trim((string) ($options['timezone'] ?? config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC'))));
        $currency = strtoupper(trim((string) ($options['currency'] ?? config('booking.multi_branch.default_branch_currency', 'VND'))));

        if (! Branch::query()->exists()) {
            /** @var Branch $branch */
            $branch = Branch::query()->create([
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
                'description' => 'Chi nhanh khoi tao he thong.',
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
                'description' => 'Chi nhanh khoi tao he thong.',
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
            ['template_code' => 'BOOT-2P', 'seats' => 2, 'description' => 'Ban 2 cho khoi tao'],
            ['template_code' => 'BOOT-4P', 'seats' => 4, 'description' => 'Ban 4 cho khoi tao'],
            ['template_code' => 'BOOT-6P', 'seats' => 6, 'description' => 'Ban 6 cho khoi tao'],
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
        $zones = $this->normalizeZones($options['zones'] ?? 'Tang tret,San vuon');
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
                        'description' => sprintf('Ban %d cho tai %s.', $seats, $zone),
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
                'category' => ['name' => 'Do uong', 'description' => 'Do uong khoi tao', 'sort_order' => 10],
                'items' => [
                    ['code' => 'BOOT-WATER', 'name' => 'Tra da', 'price' => 10000],
                ],
            ],
            [
                'category' => ['name' => 'Mon chinh', 'description' => 'Mon chinh khoi tao', 'sort_order' => 20],
                'items' => [
                    ['code' => 'BOOT-FRIED-RICE', 'name' => 'Com chien', 'price' => 89000],
                    ['code' => 'BOOT-NOODLE-BOWL', 'name' => 'Bun xao', 'price' => 79000],
                ],
            ],
        ];

        $categoryCount = 0;
        $itemCount = 0;
        $priceCount = 0;
        $effectiveFrom = now('UTC')->startOfDay();

        foreach ($definitions as $definition) {
            /** @var MenuCategory $category */
            $category = MenuCategory::query()->firstOrCreate(
                ['name' => $definition['category']['name']],
                [
                    'description' => $definition['category']['description'],
                    'sort_order' => $definition['category']['sort_order'],
                    'is_deleted' => false,
                ]
            );
            $categoryCount++;

            foreach ($definition['items'] as $itemDefinition) {
                /** @var MenuItem $item */
                $item = MenuItem::query()->firstOrCreate(
                    ['code' => $itemDefinition['code']],
                    [
                        'category_id' => (int) $category->category_id,
                        'name' => $itemDefinition['name'],
                        'description' => 'Mon khoi tao',
                        'img_url' => null,
                        'is_available' => true,
                        'is_preorder_enabled' => false,
                        'preorder_quota_per_day' => null,
                        'preorder_cutoff_minutes' => 0,
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
            fullName: trim((string) ($options['admin_name'] ?? 'Quan tri khoi tao')),
            roleId: 1,
        );
        $staff = $this->ensureBootstrapUser(
            username: trim((string) ($options['staff_username'] ?? 'bootstrap-staff')),
            fullName: trim((string) ($options['staff_name'] ?? 'Nhan vien khoi tao')),
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
        $description = $this->financeTaxProfileService->describe();
        if (($description['runtime_profile'] ?? null) === null) {
            $description = $this->financeTaxProfileService->upsert(
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
        $label = trim((string) ($options['staff_key_label'] ?? 'Khoa API nhan vien khoi tao'));

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

        return $zones !== [] ? $zones : ['Tang tret'];
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
