<?php

declare(strict_types=1);

namespace App\Services\Admin\Restaurant;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableTemplate;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Support\AuditEvent;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminRestaurantTableService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{tables: array<int, RestaurantTable>, meta: array<string,mixed>}
     */
    public function listTables(array $filters = []): array
    {
        $query = RestaurantTable::query()
            ->with(['template', 'branch'])
            ->orderByRaw("COALESCE(zone, '') ASC")
            ->orderBy('table_code');

        if (! ((bool) ($filters['include_deleted'] ?? false))) {
            $query->where('is_deleted', false);
        }

        $zone = $this->normalizeZoneInput($filters['zone'] ?? null);
        if (array_key_exists('zone', $filters)) {
            if ($zone === null) {
                $query->where(function ($builder): void {
                    $builder->whereNull('zone')->orWhere('zone', '');
                });
            } else {
                $query->where('zone', $zone);
            }
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (string) $filters['status']);
        }

        if (($filters['template_id'] ?? null) !== null) {
            $query->where('template_id', (int) $filters['template_id']);
        }

        if (($filters['branch_id'] ?? null) !== null && $filters['branch_id'] !== '') {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (($filters['q'] ?? null) !== null && trim((string) $filters['q']) !== '') {
            $keyword = trim((string) $filters['q']);
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('table_code', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%')
                    ->orWhere('zone', 'like', '%' . $keyword . '%');
            });
        }

        /** @var EloquentCollection<int, RestaurantTable> $tables */
        $tables = $query->get();
        $usageByTable = $this->loadUsageSnapshot($tables->modelKeys());

        $decorated = $tables->map(function (RestaurantTable $table) use ($usageByTable): RestaurantTable {
            $usage = $usageByTable[(int) $table->table_id] ?? $this->emptyUsage();
            $table->setRelation('usage', collect($usage));
            $table->setRelation('guards', collect($this->buildGuards($table, $usage)));

            return $table;
        })->all();

        return [
            'tables' => $decorated,
            'meta' => [
                'action' => 'admin_restaurant_tables',
                'count' => count($decorated),
                'filters' => [
                    'zone' => $zone,
                    'status' => $filters['status'] ?? null,
                    'template_id' => ($filters['template_id'] ?? null) !== null ? (int) $filters['template_id'] : null,
                    'include_deleted' => (bool) ($filters['include_deleted'] ?? false),
                    'q' => ($filters['q'] ?? null) !== null ? trim((string) $filters['q']) : null,
                    'branch_id' => ($filters['branch_id'] ?? null) !== null && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null,
                ],
            ],
        ];
    }

    public function showTable(int $tableId): RestaurantTable
    {
        $table = RestaurantTable::query()
            ->with(['template', 'branch'])
            ->findOrFail($tableId);

        $usage = $this->loadUsageSnapshot([$tableId])[$tableId] ?? $this->emptyUsage();
        $table->setRelation('usage', collect($usage));
        $table->setRelation('guards', collect($this->buildGuards($table, $usage)));

        return $table;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createTable(array $payload, ?int $actorUserId = null): RestaurantTable
    {
        Validator::make($payload, [
            'status' => ['nullable', 'in:Available,Blocked,Maintenance'],
        ])->validate();

        $table = new RestaurantTable();
        $table->fill([
            'branch_id' => $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null),
            'table_code' => trim((string) $payload['table_code']),
            'template_id' => (int) $payload['template_id'],
            'zone' => $this->normalizeZoneInput($payload['zone'] ?? null),
            'pos_x' => $payload['pos_x'] ?? null,
            'pos_y' => $payload['pos_y'] ?? null,
            'status' => $payload['status'] ?? RestaurantTableStatus::Available->value,
            'description' => $this->nullableTrimmed($payload['description'] ?? null),
            'price' => $payload['price'] ?? null,
            'is_deleted' => (bool) ($payload['is_deleted'] ?? false),
        ]);
        $table->save();

        AuditEvent::info('admin.restaurant_table.created', [
            'table_id' => (int) $table->table_id,
            'table_code' => (string) $table->table_code,
            '_audit' => [
                'action' => 'master_data.restaurant_table.created',
                'entity_type' => 'restaurant_table',
                'entity_id' => (string) $table->table_id,
                'subjects' => $table->branch_id !== null
                    ? [['type' => 'branch', 'id' => (string) $table->branch_id, 'role' => 'branch']]
                    : [],
                'after' => $this->auditSnapshot($table),
                'summary' => [
                    'table_code' => (string) $table->table_code,
                    'status' => (string) ($table->status?->value ?? $table->status),
                    'is_deleted' => (bool) $table->is_deleted,
                ],
                'actor' => $this->auditActor($actorUserId),
            ],
        ]);

        return $this->showTable((int) $table->table_id);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updateTable(int $tableId, array $payload, ?int $actorUserId = null): RestaurantTable
    {
        return DB::transaction(function () use ($tableId, $payload, $actorUserId): RestaurantTable {
            /** @var RestaurantTable|null $table */
            $table = RestaurantTable::query()->with('template')->lockForUpdate()->find($tableId);
            if (! $table instanceof RestaurantTable) {
                abort(404, 'Table not found.');
            }

            $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
            if ($expectedRowVersion <= 0 || (int) $table->row_version !== $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Table has been modified by another operation. Please reload and retry.'],
                ]);
            }

            $before = $this->auditSnapshot($table);

            $usage = $this->loadUsageSnapshot([$tableId])[$tableId] ?? $this->emptyUsage();
            $hasOperationalLinks = (bool) ($usage['has_active_operational_links'] ?? false);

            $newBranchId = array_key_exists('branch_id', $payload)
                ? $this->branchContextService->resolveBranchId($payload['branch_id'])
                : (int) $table->branch_id;
            $newTableCode = array_key_exists('table_code', $payload)
                ? trim((string) $payload['table_code'])
                : (string) $table->table_code;
            $newTemplateId = array_key_exists('template_id', $payload)
                ? (int) $payload['template_id']
                : (int) $table->template_id;
            $newZone = array_key_exists('zone', $payload)
                ? $this->normalizeZoneInput($payload['zone'])
                : $this->normalizeZoneInput($table->zone);
            $newStatus = array_key_exists('status', $payload)
                ? (string) $payload['status']
                : (string) ($table->status?->value ?? $table->status);
            $newIsDeleted = array_key_exists('is_deleted', $payload)
                ? (bool) $payload['is_deleted']
                : (bool) $table->is_deleted;

            $currentStatus = (string) ($table->status?->value ?? $table->status);
            $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Occupied->value, RestaurantTableStatus::Reserved->value], true);

            if ($newStatus !== $currentStatus) {
                if (! in_array($newStatus, [RestaurantTableStatus::Available->value, RestaurantTableStatus::Blocked->value, RestaurantTableStatus::Maintenance->value], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Admin master-data updates cannot set runtime-owned status values.'],
                    ]);
                }

                if ($runtimeOwnedStatus) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot override a table status that is currently managed by live reservation/order flow.'],
                    ]);
                }

                if ($hasOperationalLinks && in_array($newStatus, [RestaurantTableStatus::Blocked->value, RestaurantTableStatus::Maintenance->value], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot block or mark maintenance while the table still has active reservations or holds.'],
                    ]);
                }
            }

            if ($newBranchId !== (int) $table->branch_id && ($hasOperationalLinks || $runtimeOwnedStatus)) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Cannot change branch linkage while the table is operationally linked.'],
                ]);
            }

            if ($newTableCode !== (string) $table->table_code && $hasOperationalLinks) {
                throw ValidationException::withMessages([
                    'table_code' => ['Cannot rename a table while it still has active reservations, holds, or live orders.'],
                ]);
            }

            if ($newZone !== $this->normalizeZoneInput($table->zone) && ($hasOperationalLinks || $runtimeOwnedStatus)) {
                throw ValidationException::withMessages([
                    'zone' => ['Cannot change zone linkage while the table is operationally linked.'],
                ]);
            }

            if ($newTemplateId !== (int) $table->template_id && ($hasOperationalLinks || $runtimeOwnedStatus)) {
                throw ValidationException::withMessages([
                    'template_id' => ['Cannot change table capacity template while the table is operationally linked.'],
                ]);
            }

            if ($newIsDeleted !== (bool) $table->is_deleted && ($hasOperationalLinks || $runtimeOwnedStatus)) {
                throw ValidationException::withMessages([
                    'is_deleted' => ['Cannot archive or restore a table while it is operationally linked.'],
                ]);
            }

            $table->fill([
                'branch_id' => $newBranchId,
                'table_code' => $newTableCode,
                'template_id' => $newTemplateId,
                'zone' => $newZone,
                'pos_x' => array_key_exists('pos_x', $payload) ? $payload['pos_x'] : $table->pos_x,
                'pos_y' => array_key_exists('pos_y', $payload) ? $payload['pos_y'] : $table->pos_y,
                'status' => $newStatus,
                'description' => array_key_exists('description', $payload)
                    ? $this->nullableTrimmed($payload['description'])
                    : $table->description,
                'price' => array_key_exists('price', $payload) ? $payload['price'] : $table->price,
                'is_deleted' => $newIsDeleted,
            ]);
            $table->save();

            AuditEvent::info('admin.restaurant_table.updated', [
                'table_id' => (int) $table->table_id,
                'table_code' => (string) $table->table_code,
                '_audit' => [
                    'action' => 'master_data.restaurant_table.updated',
                    'entity_type' => 'restaurant_table',
                    'entity_id' => (string) $table->table_id,
                    'subjects' => $table->branch_id !== null
                        ? [['type' => 'branch', 'id' => (string) $table->branch_id, 'role' => 'branch']]
                        : [],
                    'before' => $before,
                    'after' => $this->auditSnapshot($table),
                    'summary' => [
                        'table_code' => (string) $table->table_code,
                        'status' => (string) ($table->status?->value ?? $table->status),
                        'is_deleted' => (bool) $table->is_deleted,
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $this->showTable($tableId);
        });
    }

    /**
     * @return array<int, TableTemplate>
     */
    public function listTemplates(): array
    {
        return TableTemplate::query()
            ->withCount('tables')
            ->orderBy('seats')
            ->orderBy('template_code')
            ->get()
            ->all();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function deleteTable(int $tableId, array $payload, ?int $actorUserId = null): RestaurantTable
    {
        return DB::transaction(function () use ($tableId, $payload, $actorUserId): RestaurantTable {
            /** @var RestaurantTable|null $table */
            $table = RestaurantTable::query()->with('template')->lockForUpdate()->find($tableId);
            if (! $table instanceof RestaurantTable) {
                abort(404, 'Table not found.');
            }

            $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
            if ($expectedRowVersion <= 0 || (int) $table->row_version !== $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Table has been modified by another operation. Please reload and retry.'],
                ]);
            }

            $usage = $this->loadUsageSnapshot([$tableId])[$tableId] ?? $this->emptyUsage();
            $hasOperationalLinks = (bool) ($usage['has_active_operational_links'] ?? false);
            $currentStatus = (string) ($table->status?->value ?? $table->status);
            $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Occupied->value, RestaurantTableStatus::Reserved->value], true);

            if ($runtimeOwnedStatus || $hasOperationalLinks) {
                throw ValidationException::withMessages([
                    'table_id' => ['Cannot delete or archive a table while it still has active reservations, holds, or live orders.'],
                ]);
            }

            if (! (bool) $table->is_deleted) {
                $before = $this->auditSnapshot($table);
                $table->is_deleted = true;
                $table->save();

                AuditEvent::info('admin.restaurant_table.deleted', [
                    'table_id' => (int) $table->table_id,
                    'table_code' => (string) $table->table_code,
                    '_audit' => [
                        'action' => 'master_data.restaurant_table.deleted',
                        'entity_type' => 'restaurant_table',
                        'entity_id' => (string) $table->table_id,
                        'subjects' => $table->branch_id !== null
                            ? [['type' => 'branch', 'id' => (string) $table->branch_id, 'role' => 'branch']]
                            : [],
                        'before' => $before,
                        'after' => $this->auditSnapshot($table),
                        'summary' => [
                            'table_code' => (string) $table->table_code,
                            'status' => (string) ($table->status?->value ?? $table->status),
                            'is_deleted' => true,
                        ],
                        'actor' => $this->auditActor($actorUserId),
                    ],
                ]);
            }

            return $this->showTable($tableId);
        });
    }

    /**
     * @param list<int> $tableIds
     * @return array<int, array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool}>
     */
    private function loadUsageSnapshot(array $tableIds): array
    {
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        if ($tableIds === []) {
            return [];
        }

        $now = Carbon::now('UTC');

        $reservationCounts = DB::table('reservation_tables')
            ->join('reservations', 'reservations.reservation_id', '=', 'reservation_tables.reservation_id')
            ->whereIn('reservation_tables.table_id', $tableIds)
            ->whereIn('reservations.status', ReservationStatus::activeDbValues())
            ->where('reservations.end_time', '>', $now)
            ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
            ->groupBy('reservation_tables.table_id')
            ->pluck('aggregate_count', 'reservation_tables.table_id');

        $holdCounts = collect();
        if (Schema::hasTable('table_holds') && Schema::hasTable('table_hold_details')) {
            $holdCountsQuery = DB::table('table_hold_details')
                ->join('table_holds', 'table_holds.hold_id', '=', 'table_hold_details.hold_id')
                ->whereIn('table_hold_details.table_id', $tableIds)
                ->where('table_holds.end_time', '>', $now);

            HoldConflictScope::apply($holdCountsQuery, 'table_holds', $now);

            $holdCounts = $holdCountsQuery
                ->selectRaw('table_hold_details.table_id, COUNT(*) as aggregate_count')
                ->groupBy('table_hold_details.table_id')
                ->pluck('aggregate_count', 'table_hold_details.table_id');
        }

        $orderCounts = collect();
        if (Schema::hasTable('reservation_orders') && Schema::hasTable('reservation_tables')) {
            $orderCounts = DB::table('reservation_tables')
                ->join('reservation_orders', 'reservation_orders.reservation_id', '=', 'reservation_tables.reservation_id')
                ->whereIn('reservation_tables.table_id', $tableIds)
                ->where('reservation_orders.status', 'Active')
                ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
                ->groupBy('reservation_tables.table_id')
                ->pluck('aggregate_count', 'reservation_tables.table_id');
        }

        $snapshot = [];
        foreach ($tableIds as $tableId) {
            $reservationCount = (int) ($reservationCounts[$tableId] ?? 0);
            $holdCount = (int) ($holdCounts[$tableId] ?? 0);
            $orderCount = (int) ($orderCounts[$tableId] ?? 0);
            $snapshot[$tableId] = [
                'active_reservation_count' => $reservationCount,
                'active_hold_count' => $holdCount,
                'active_order_count' => $orderCount,
                'has_active_operational_links' => $reservationCount > 0 || $holdCount > 0 || $orderCount > 0,
            ];
        }

        return $snapshot;
    }

    /**
     * @param array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool} $usage
     * @return array{can_archive:bool,can_change_template:bool,can_change_runtime_status:bool,can_change_zone:bool,can_change_table_code:bool,can_change_branch:bool}
     */
    private function buildGuards(RestaurantTable $table, array $usage): array
    {
        $currentStatus = (string) ($table->status?->value ?? $table->status);
        $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Occupied->value, RestaurantTableStatus::Reserved->value], true);
        $hasLinks = (bool) ($usage['has_active_operational_links'] ?? false);

        return [
            'can_archive' => ! $runtimeOwnedStatus && ! $hasLinks,
            'can_change_template' => ! $runtimeOwnedStatus && ! $hasLinks,
            'can_change_runtime_status' => ! $runtimeOwnedStatus,
            'can_change_zone' => ! $runtimeOwnedStatus && ! $hasLinks,
            'can_change_table_code' => ! $runtimeOwnedStatus && ! $hasLinks,
            'can_change_branch' => ! $runtimeOwnedStatus && ! $hasLinks,
        ];
    }

    /**
     * @return array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool}
     */
    private function emptyUsage(): array
    {
        return [
            'active_reservation_count' => 0,
            'active_hold_count' => 0,
            'active_order_count' => 0,
            'has_active_operational_links' => false,
        ];
    }

    private function normalizeZoneInput(mixed $zone): ?string
    {
        if ($zone === null) {
            return null;
        }

        $value = trim((string) $zone);

        return $value === '' ? null : $value;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string,mixed>
     */
    private function auditSnapshot(RestaurantTable $table): array
    {
        return [
            'branch_id' => $table->branch_id !== null ? (int) $table->branch_id : null,
            'table_code' => (string) $table->table_code,
            'template_id' => $table->template_id !== null ? (int) $table->template_id : null,
            'zone' => $table->zone,
            'status' => (string) ($table->status?->value ?? $table->status),
            'description' => $table->description,
            'price' => $table->price,
            'is_deleted' => (bool) $table->is_deleted,
            'row_version' => (int) ($table->row_version ?? 1),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function auditActor(?int $actorUserId): ?array
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return [
            'type' => 'staff_user',
            'user_id' => $actorUserId,
            'key' => 'staff_user:' . $actorUserId,
        ];
    }
}
