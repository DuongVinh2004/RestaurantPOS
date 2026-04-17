<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\CustomerAccessSession;
use App\Models\StaffApiKey;
use App\Models\User;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\CheckoutPayments\Application\Services\StaffCashierShiftService;
use App\Modules\CheckoutPayments\Domain\Models\CashierShift;
use App\Modules\FloorOps\Application\Services\StaffBranchContextService;
use App\Services\CustomerAccessSessionService;
use App\Services\StaffApiKeyGovernanceService;
use App\Support\AuditEvent;
use App\Support\StaffCapabilityResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OpaqueProductAuthService
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
        private readonly CustomerAccessSessionService $customerAccessSessionService,
        private readonly StaffApiKeyGovernanceService $staffApiKeyGovernanceService,
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
        private readonly BranchContextService $branchContextService,
        private readonly StaffBranchContextService $staffBranchContextService,
        private readonly StaffCashierShiftService $staffCashierShiftService,
    ) {}

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function loginCustomer(string $identifier, string $password, array $context = []): array
    {
        $user = $this->resolveUserForPasswordLogin($identifier, (array) config('customer_auth.allowed_role_ids', [3]));
        $this->assertPasswordCredentials($user, $password, 'customer');

        $issued = $this->customerAccessSessionService->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            $this->customerSessionContext($user, $context),
        );

        AuditEvent::info('customer_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

        return $this->formatCustomerSessionPayload($issued['access_session'], (string) $issued['plain_text_token']);
    }

    /**
     * @return array<string,mixed>
     */
    public function refreshCustomer(int $accessSessionId): array
    {
        $current = $this->requireActiveCustomerAccessSession($accessSessionId);

        $user = $current->user;
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session is no longer bound to a valid customer account.'],
            ]);
        }

        $this->customerAccessSessionService->revokeSession($current);

        $issued = $this->customerAccessSessionService->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            [
                'session_id' => $current->session_id,
                'guest_name' => $current->guest_name,
                'phone' => $current->phone,
                'session_meta_json' => $current->session_meta_json,
                'created_ip' => $current->created_ip,
                'user_agent' => $current->user_agent,
                'source' => 'customer_session_refresh',
            ],
        );

        AuditEvent::info('customer_password_login_refreshed', [
            'user_id' => (int) $user->user_id,
            'revoked_access_session_id' => (int) $current->getKey(),
            'replacement_access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

        return $this->formatCustomerSessionPayload($issued['access_session'], (string) $issued['plain_text_token']);
    }

    /**
     * @return array<string,mixed>
     */
    public function currentCustomer(int $accessSessionId): array
    {
        return $this->formatCustomerSessionPayload($this->requireActiveCustomerAccessSession($accessSessionId), null);
    }

    /**
     * @return array<string,mixed>
     */
    public function logoutCustomer(int $accessSessionId): array
    {
        $session = $this->customerAccessSessionService->revokeSession($accessSessionId);
        if (! $session instanceof CustomerAccessSession) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session was not found.'],
            ]);
        }

        AuditEvent::info('customer_password_login_logged_out', [
            'access_session_id' => (int) $session->getKey(),
            'user_id' => (int) ($session->user_id ?? 0),
        ]);

        return [
            'auth_mode' => 'customer_access_session',
            'access_session_id' => (int) $session->getKey(),
            'revoked_at_utc' => $session->revoked_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function loginStaff(string $identifier, string $password, array $context = []): array
    {
        $user = $this->resolveUserForPasswordLogin($identifier, (array) config('staff_auth.allowed_role_ids', [1, 2]));
        $this->assertPasswordCredentials($user, $password, 'staff');

        $issued = $this->staffApiKeyGovernanceService->issueKey(
            (int) $user->user_id,
            $this->staffSessionLabel($context),
            $this->staffSessionExpiry(),
        );

        /** @var StaffApiKey $record */
        $record = $issued['record'];
        $record->loadMissing('user.role');

        AuditEvent::info('staff_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'staff_api_key_id' => (int) $record->getKey(),
        ]);

        return $this->formatStaffSessionPayload($record, (string) $issued['plaintext_key']);
    }

    /**
     * @return array<string,mixed>
     */
    public function refreshStaff(int $staffApiKeyId): array
    {
        $current = $this->requireActiveStaffApiKey($staffApiKeyId);

        $rotated = $this->staffApiKeyGovernanceService->rotateKey(
            (int) $current->getKey(),
            (string) $current->label,
            $this->staffSessionExpiry(),
        );

        /** @var StaffApiKey $replacement */
        $replacement = $rotated['record'];
        $replacement->loadMissing('user.role');

        AuditEvent::info('staff_password_login_refreshed', [
            'user_id' => (int) ($replacement->user_id ?? 0),
            'revoked_staff_api_key_id' => (int) ($rotated['revoked']->getKey() ?? 0),
            'replacement_staff_api_key_id' => (int) $replacement->getKey(),
        ]);

        return $this->formatStaffSessionPayload($replacement, (string) $rotated['plaintext_key']);
    }

    /**
     * @return array<string,mixed>
     */
    public function currentStaff(int $staffApiKeyId): array
    {
        return $this->formatStaffSessionPayload($this->requireActiveStaffApiKey($staffApiKeyId), null);
    }

    /**
     * @return array<string,mixed>
     */
    public function logoutStaff(int $staffApiKeyId): array
    {
        $record = $this->staffApiKeyGovernanceService->revokeKey($staffApiKeyId, 'staff_password_logout');

        AuditEvent::info('staff_password_login_logged_out', [
            'staff_api_key_id' => (int) $record->getKey(),
            'user_id' => (int) ($record->user_id ?? 0),
        ]);

        return [
            'auth_mode' => 'staff_api_key',
            'staff_api_key_id' => (int) $record->getKey(),
            'revoked_at_utc' => $record->revoked_at?->utc()->toIso8601String(),
        ];
    }

    private function resolveUserForPasswordLogin(string $identifier, array $allowedRoleIds): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $allowedRoleIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $allowedRoleIds
        ), static fn (int $value): bool => $value > 0));

        return User::query()
            ->with('role')
            ->notDeleted()
            ->when($allowedRoleIds !== [], static fn ($query) => $query->whereIn('role_id', $allowedRoleIds))
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();
    }

    private function assertPasswordCredentials(?User $user, string $password, string $scope): void
    {
        if (! $user instanceof User || trim((string) ($user->password_hash ?? '')) === '' || ! Hash::check($password, (string) $user->password_hash)) {
            AuditEvent::warning('password_login_failed', [
                'scope' => $scope,
                'user_id' => $user?->user_id !== null ? (int) $user->user_id : null,
            ]);

            throw ValidationException::withMessages([
                'identifier' => ['Invalid credentials.'],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function customerSessionContext(User $user, array $context): array
    {
        return [
            'session_id' => isset($context['session_id']) ? trim((string) $context['session_id']) : null,
            'guest_name' => trim((string) ($context['guest_name'] ?? $user->full_name ?? '')) ?: null,
            'phone' => trim((string) ($context['phone'] ?? $user->phone ?? '')) ?: null,
            'session_meta_json' => array_filter([
                'session_label' => trim((string) ($context['session_label'] ?? 'customer_password_login')) ?: null,
                'source' => 'customer_password_login',
                'device_id' => trim((string) ($context['device_id'] ?? '')) ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'created_ip' => trim((string) ($context['ip'] ?? '')) ?: null,
            'user_agent' => trim((string) ($context['user_agent'] ?? '')) ?: null,
            'source' => 'customer_password_login',
        ];
    }

    private function customerSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60 * 24 * 14)));
    }

    private function staffSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function staffSessionLabel(array $context): string
    {
        $label = trim((string) ($context['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $device = trim((string) ($context['device_name'] ?? ''));
        if ($device !== '') {
            return 'Auth Session - '.$device;
        }

        return 'Auth Session';
    }

    private function requireActiveCustomerAccessSession(int $accessSessionId): CustomerAccessSession
    {
        /** @var CustomerAccessSession $session */
        $session = $this->customerAccessSessionService->showSession($accessSessionId);

        if ($session->revoked_at !== null || $session->expires_at === null || ! $session->expires_at->utc()->isFuture()) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session is no longer active.'],
            ]);
        }

        $session->loadMissing('user.role');

        return $session;
    }

    private function requireActiveStaffApiKey(int $staffApiKeyId): StaffApiKey
    {
        /** @var StaffApiKey $record */
        $record = $this->staffApiKeyGovernanceService->showKey($staffApiKeyId);

        if ($record->revoked_at !== null || ($record->expires_at !== null && ! $record->expires_at->utc()->isFuture())) {
            throw ValidationException::withMessages([
                'staff_api_key' => ['Staff auth session is no longer active.'],
            ]);
        }

        $record->loadMissing('user.role');

        return $record;
    }

    /**
     * @return array<string,mixed>
     */
    private function formatCustomerSessionPayload(CustomerAccessSession $session, ?string $plainTextToken): array
    {
        $session->loadMissing('user.role');

        return [
            'auth_mode' => 'customer_access_session',
            'token_type' => 'opaque',
            'auth_header' => (string) config('customer_auth.header', 'X-Customer-Token'),
            'access_token' => $plainTextToken,
            'access_session_id' => (int) $session->getKey(),
            'session_id' => (string) ($session->session_id ?? ''),
            'expires_at_utc' => $session->expires_at?->utc()->toIso8601String(),
            'user' => $this->formatUserPayload($session->user),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function formatStaffSessionPayload(StaffApiKey $record, ?string $plainTextKey): array
    {
        $record->loadMissing('user.role');
        $capabilityContext = $this->formatStaffCapabilityContext($record->user);
        $startupContext = $this->formatStaffStartupContext($record->user, $capabilityContext);

        return [
            'auth_mode' => 'staff_api_key',
            'token_type' => 'opaque',
            'auth_header' => 'X-Staff-Key',
            'access_token' => $plainTextKey,
            'staff_api_key_id' => (int) $record->getKey(),
            'expires_at_utc' => $record->expires_at?->utc()->toIso8601String(),
            'user' => $this->formatUserPayload($record->user),
            'capabilities' => $capabilityContext['capabilities'],
            'known_capabilities' => $capabilityContext['known_capabilities'],
            'capability_source' => $capabilityContext['source'],
            'startup' => $startupContext,
        ];
    }

    /**
     * @return array{
     *   capabilities:list<string>,
     *   known_capabilities:list<string>,
     *   source:string
     * }
     */
    private function formatStaffCapabilityContext(?User $user): array
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
     * @return array<string,mixed>|null
     */
    private function formatUserPayload(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'user_id' => (int) $user->user_id,
            'username' => (string) ($user->username ?? ''),
            'full_name' => (string) ($user->full_name ?? ''),
            'email' => $user->email,
            'phone' => $user->phone,
            'role_id' => $user->role_id !== null ? (int) $user->role_id : null,
            'role_name' => $user->role?->role_name,
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
    private function formatStaffStartupContext(?User $user, array $capabilityContext): array
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
