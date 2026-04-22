<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\Cashiering\Application\UseCases\Shifts\StaffCashierShiftService;
use App\Modules\Cashiering\Domain\Models\CashierShift;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffStartupContextBuilder
{
    /**
     * @var list<string>
     */
    private const STAFF_WORKSPACE_ORDER = ['ops', 'kitchen', 'admin'];

    private const DEFAULT_STAFF_WORKSPACE = 'ops';

    /**
     * @var array<string,list<string>>
     */
    private const STAFF_WORKSPACE_CAPABILITIES = [
        'ops' => [
            'table.board.view',
            'table.release',
            'reservation.manage',
            'waiting_list.manage',
            'order.manage',
            'settlement.manage',
            'payment.refund',
            'cashier.shift.manage',
            'conversation.manage',
            'voucher.manage',
            'loyalty.view',
            'loyalty.redeem',
            'loyalty.adjust',
        ],
        'kitchen' => [
            'kitchen.manage',
        ],
        'admin' => [
            'audit.view',
            'reporting.view',
            'inventory.manage',
            'menu.manage',
            'settings.manage',
            'privacy.manage',
            'ops.view',
            'ops.health.view',
            'ops.metrics.view',
            'ops.release.view',
            'voucher.master_data.manage',
        ],
    ];

    public function __construct(
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
        private readonly BranchContextService $branchContextService,
        private readonly StaffBranchContextService $staffBranchContextService,
        private readonly StaffCashierShiftService $staffCashierShiftService,
    ) {}

    /**
     * @return array{
     *   capabilities:list<string>,
     *   known_capabilities:list<string>,
     *   source:string
     * }
     */
    public function buildCapabilityContext(?User $user): array
    {
        $resolved = $this->staffCapabilityResolver->resolveForActor(
            (int) ($user?->role_id ?? 0),
            (string) ($user?->role?->role_name ?? '')
        );

        return [
            'capabilities' => array_values(array_map('strval', (array) ($resolved['capabilities'] ?? []))),
            'known_capabilities' => array_values(array_map('strval', (array) ($resolved['known_capabilities'] ?? []))),
            'source' => (string) ($resolved['source'] ?? 'deny_by_default'),
        ];
    }

    /**
     * @param  array{
     *   capabilities:list<string>,
     *   known_capabilities:list<string>,
     *   source:string
     * }  $capabilityContext
     * @return array<string,mixed>
     */
    public function buildStartupContext(?User $user, array $capabilityContext): array
    {
        $defaultBranch = $this->resolveDefaultBranchContext();
        $branchAccess = $this->resolveStaffBranchAccessContext($user);
        $activeCashierShift = $this->resolveActiveCashierShiftContext($user);
        $capabilities = array_values(array_map('strval', (array) ($capabilityContext['capabilities'] ?? [])));
        $knownCapabilities = array_values(array_map('strval', (array) ($capabilityContext['known_capabilities'] ?? [])));
        $workspaceContext = $this->resolveStaffWorkspaceContext($capabilities);
        $hasStaffWebAccess = $workspaceContext['available_workspaces'] !== [];
        $hasBranchAccess = $branchAccess['accessible_branch_ids'] !== [];
        $requiresCashierShift = $this->hasCapability($capabilities, 'settlement.manage')
            || $this->hasCapability($capabilities, 'cashier.shift.manage');

        return [
            'primary_workspace' => $workspaceContext['primary_workspace'],
            'available_workspaces' => $workspaceContext['available_workspaces'],
            'default_branch_id' => $branchAccess['default_branch_id'],
            'allowed_branch_ids' => $branchAccess['accessible_branch_ids'],
            'assigned_station_ids' => $this->resolveAssignedStationIdsContext($capabilities),
            'default_branch' => $defaultBranch,
            'branch_access' => $branchAccess,
            'active_cashier_shift' => $activeCashierShift,
            'navigation' => $this->formatStaffNavigationContext($capabilities),
            'readiness' => [
                'access' => $hasStaffWebAccess ? 'ready' : 'capability_missing',
                'branch' => $hasBranchAccess ? 'ready' : 'missing',
                'cashier_shift' => ! $requiresCashierShift
                    ? 'not_applicable'
                    : ($activeCashierShift !== null ? 'ready' : 'action_required'),
                'operator_ready' => $hasStaffWebAccess && $hasBranchAccess,
                'requires_cashier_shift' => $requiresCashierShift,
                'granted_capability_count' => count($capabilities),
                'known_capability_count' => count($knownCapabilities),
            ],
        ];
    }

    /**
     * @return array{
     *   accessible_branch_ids:list<int>,
     *   default_branch_id:int|null,
     *   current_branch_id:int|null,
     *   has_default_branch_access:bool,
     *   has_multi_branch_access:bool,
     *   branch_selector_enabled:bool,
     *   access_source:string,
     *   branches_uri:string
     * }
     */
    private function resolveStaffBranchAccessContext(?User $user): array
    {
        $fallback = [
            'accessible_branch_ids' => [],
            'default_branch_id' => null,
            'current_branch_id' => null,
            'has_default_branch_access' => false,
            'has_multi_branch_access' => false,
            'branch_selector_enabled' => false,
            'access_source' => 'unavailable',
            'branches_uri' => '/api/v1/staff/branches',
        ];

        if (! $user instanceof User || ! Schema::hasTable('branches')) {
            return $fallback;
        }

        try {
            return $this->staffBranchContextService->branchAccessContext((int) $user->user_id);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  list<string>  $capabilities
     * @return array<string,array{key:string,required_capabilities:list<string>,can_access:bool,primary_route:string}>
     */
    private function formatStaffNavigationContext(array $capabilities): array
    {
        $items = [
            'branches' => [['reservation.manage'], '/api/v1/staff/branches'],
            'floor' => [['table.board.view'], '/api/v1/staff/tables/board'],
            'reservations' => [['reservation.manage'], '/api/v1/staff/reservations'],
            'ordering' => [['order.manage'], '/api/v1/staff/orders'],
            'kitchen' => [['kitchen.manage'], '/api/v1/staff/kitchen/stations'],
            'cashier' => [['cashier.shift.manage'], '/api/v1/staff/cashier/shifts/current'],
            'checkout' => [['settlement.manage'], '/api/v1/staff/orders/{order_id}/settlement-preview'],
            'refunds' => [['payment.refund'], '/api/v1/staff/reservations/{reservation_id}/refund-preview'],
            'conversations' => [['conversation.manage'], '/api/v1/staff/conversations'],
            'audit' => [['audit.view'], '/api/v1/staff/audit-trail'],
            'reporting' => [['reporting.view'], '/api/v1/staff/reporting/daily-sales'],
            'waiting_list' => [['waiting_list.manage'], '/api/v1/staff/waiting-list'],
        ];

        $navigation = [];
        foreach ($items as $key => [$requiredCapabilities, $primaryRoute]) {
            $requiredCapabilities = array_values(array_map('strval', (array) $requiredCapabilities));
            $navigation[(string) $key] = [
                'key' => (string) $key,
                'required_capabilities' => $requiredCapabilities,
                'can_access' => $this->hasAnyCapability($capabilities, $requiredCapabilities),
                'primary_route' => (string) $primaryRoute,
            ];
        }

        return $navigation;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveDefaultBranchContext(): ?array
    {
        if (! Schema::hasTable('branches')) {
            return null;
        }

        try {
            $branch = $this->branchContextService->defaultBranch();
        } catch (\Throwable) {
            return null;
        }

        return $this->formatBootstrapBranch($branch);
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{primary_workspace:string,available_workspaces:list<string>}
     */
    private function resolveStaffWorkspaceContext(array $capabilities): array
    {
        $availableWorkspaces = [];

        foreach (self::STAFF_WORKSPACE_ORDER as $workspace) {
            $workspaceCapabilities = self::STAFF_WORKSPACE_CAPABILITIES[$workspace] ?? [];
            if ($this->hasAnyCapability($capabilities, $workspaceCapabilities)) {
                $availableWorkspaces[] = $workspace;
            }
        }

        return [
            'primary_workspace' => $availableWorkspaces[0] ?? self::DEFAULT_STAFF_WORKSPACE,
            'available_workspaces' => $availableWorkspaces,
        ];
    }

    /**
     * @param  list<string>  $capabilities
     * @return list<int>
     */
    private function resolveAssignedStationIdsContext(array $capabilities): array
    {
        if (! $this->hasCapability($capabilities, 'kitchen.manage') || ! Schema::hasTable('kitchen_stations')) {
            return [];
        }

        try {
            $query = DB::table('kitchen_stations');

            if (Schema::hasColumn('kitchen_stations', 'is_active')) {
                $query->where('is_active', true);
            }

            return $query
                ->orderBy('station_id')
                ->pluck('station_id')
                ->map(static fn ($value): int => (int) $value)
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveActiveCashierShiftContext(?User $user): ?array
    {
        if (! $user instanceof User || ! Schema::hasTable('cashier_shifts')) {
            return null;
        }

        try {
            $shift = $this->staffCashierShiftService->currentOpenShift((int) $user->user_id);
        } catch (\Throwable) {
            return null;
        }

        if (! $shift instanceof CashierShift) {
            return null;
        }

        $shift->loadMissing('branch');

        return [
            'cashier_shift_id' => (int) $shift->cashier_shift_id,
            'branch_id' => (int) $shift->branch_id,
            'branch' => $this->formatBootstrapBranch($shift->branch),
            'shift_code' => (string) $shift->shift_code,
            'status' => (string) $shift->status,
            'currency' => (string) ($shift->currency ?? 'VND'),
            'terminal_code' => $shift->terminal_code,
            'row_version' => (int) ($shift->row_version ?? 1),
            'opened_at' => $shift->opened_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function formatBootstrapBranch(?Branch $branch): ?array
    {
        if (! $branch instanceof Branch) {
            return null;
        }

        return [
            'branch_id' => (int) $branch->branch_id,
            'branch_code' => (string) $branch->branch_code,
            'branch_name' => (string) $branch->branch_name,
            'timezone' => $branch->timezone !== null ? (string) $branch->timezone : null,
            'currency' => $branch->currency !== null ? (string) $branch->currency : null,
            'is_default' => (bool) $branch->is_default,
            'is_active' => (bool) $branch->is_active,
        ];
    }

    /**
     * @param  list<string>  $capabilities
     * @param  list<string>  $requiredCapabilities
     */
    private function hasAnyCapability(array $capabilities, array $requiredCapabilities): bool
    {
        foreach ($requiredCapabilities as $capability) {
            if ($this->hasCapability($capabilities, $capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function hasCapability(array $capabilities, string $capability): bool
    {
        return in_array('*', $capabilities, true) || in_array($capability, $capabilities, true);
    }
}
