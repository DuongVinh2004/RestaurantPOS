<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class BranchSchedulingPolicyService
{
    /** @var array<int, Branch> */
    private array $branchCache = [];

    /** @var array<int, array<string,mixed>> */
    private array $contextCache = [];

    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {}

    public function resolveBranch(mixed $branchId = null, bool $activeOnly = true): Branch
    {
        $resolvedBranchId = $this->branchContextService->resolveBranchId($branchId, $activeOnly);

        if (! array_key_exists($resolvedBranchId, $this->branchCache)) {
            /** @var Branch|null $branch */
            $branch = Branch::query()->find($resolvedBranchId);
            if (! $branch instanceof Branch) {
                throw (new ModelNotFoundException)->setModel(Branch::class, [$resolvedBranchId]);
            }

            $this->branchCache[$resolvedBranchId] = $branch;
        }

        return $this->branchCache[$resolvedBranchId];
    }

    public function resolveBranchId(mixed $branchId = null, bool $activeOnly = true): int
    {
        return (int) $this->resolveBranch($branchId, $activeOnly)->branch_id;
    }

    /**
     * @return array{
     *   branch: Branch,
     *   branch_id: int,
     *   timezone: string,
     *   business_hours: array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>>,
     *   closure_windows: array<int, array{start_local:string,end_local:string,type:string,reason:?string}>,
     *   booking_policy: array<string,mixed>
     * }
     */
    public function resolveContext(mixed $branchId = null, bool $activeOnly = true): array
    {
        $branch = $this->resolveBranch($branchId, $activeOnly);
        $resolvedBranchId = (int) $branch->branch_id;

        if (! array_key_exists($resolvedBranchId, $this->contextCache)) {
            $timezone = $this->normalizeTimezone((string) ($branch->timezone ?: $this->defaultTimezone()));

            $this->contextCache[$resolvedBranchId] = [
                'branch' => $branch,
                'branch_id' => $resolvedBranchId,
                'timezone' => $timezone,
                'business_hours' => $this->normalizeBusinessHoursPayload($branch->business_hours),
                'closure_windows' => $this->normalizeClosureWindowsPayload($branch->closure_windows, $timezone),
                'booking_policy' => $this->normalizeBookingPolicyPayload($branch->booking_policy),
            ];
        }

        return $this->contextCache[$resolvedBranchId];
    }

    /**
     * @return array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>>
     */
    public function defaultBusinessHours(): array
    {
        return $this->normalizeBusinessHoursArray(
            (array) config('booking.branch_policy_defaults.business_hours', []),
            'business_hours'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function defaultBookingPolicy(): array
    {
        $defaults = (array) config('booking.branch_policy_defaults.booking_policy', []);
        $defaults['reservation'] = array_merge(
            is_array($defaults['reservation'] ?? null) ? $defaults['reservation'] : [],
            [
                'cancellation_cutoff_minutes' => (int) config('booking.customer_reservation_cancellation_cutoff_minutes', 30),
                'reschedule_cutoff_minutes' => (int) config('booking.customer_reservation_reschedule_cutoff_minutes', 120),
            ]
        );
        $defaults['waiting_list'] = array_merge(
            is_array($defaults['waiting_list'] ?? null) ? $defaults['waiting_list'] : [],
            [
                'notify_hold_minutes' => (int) config('booking.waiting_list_notify_hold_minutes', 10),
                'default_service_minutes' => (int) config('booking.waiting_list_service_minutes', 120),
            ]
        );
        $defaults['availability'] = array_merge(
            is_array($defaults['availability'] ?? null) ? $defaults['availability'] : [],
            [
                'service_buffer_minutes' => (int) config('booking.service_buffer_minutes', 0),
            ]
        );

        return $this->normalizeBookingPolicyArray(
            $defaults,
            'booking_policy'
        );
    }

    /**
     * @return array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>>
     */
    public function normalizeBusinessHoursPayload(mixed $value): array
    {
        if ($value === null) {
            return $this->defaultBusinessHours();
        }

        if (! is_array($value)) {
            $this->throwValidation('business_hours', 'Business hours payload must be an array.');
        }

        return $this->normalizeBusinessHoursArray($value, 'business_hours');
    }

    /**
     * @return array<int, array{start_local:string,end_local:string,type:string,reason:?string}>
     */
    public function normalizeClosureWindowsPayload(mixed $value, ?string $timezone = null): array
    {
        $timezone = $this->normalizeTimezone($timezone ?? $this->defaultTimezone());

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $this->throwValidation('closure_windows', 'Closure windows payload must be an array.');
        }

        $normalized = [];
        foreach ($value as $index => $window) {
            if (! is_array($window)) {
                $this->throwValidation('closure_windows', sprintf('Closure window #%d must be an object.', $index));
            }

            $startLocal = $this->normalizeLocalDateTime(
                $window['start_local'] ?? null,
                $timezone,
                sprintf('closure_windows.%d.start_local', $index)
            );
            $endLocal = $this->normalizeLocalDateTime(
                $window['end_local'] ?? null,
                $timezone,
                sprintf('closure_windows.%d.end_local', $index)
            );

            if ($endLocal->lessThanOrEqualTo($startLocal)) {
                $this->throwValidation(
                    sprintf('closure_windows.%d.end_local', $index),
                    'Closure window end_local must be after start_local.'
                );
            }

            $type = trim((string) ($window['type'] ?? 'closure'));
            if ($type === '') {
                $type = 'closure';
            }
            if (! in_array($type, ['closure', 'holiday', 'blackout'], true)) {
                $this->throwValidation(
                    sprintf('closure_windows.%d.type', $index),
                    'Closure window type must be one of: closure, holiday, blackout.'
                );
            }

            $normalized[] = [
                'start_local' => $startLocal->format('Y-m-d H:i:s'),
                'end_local' => $endLocal->format('Y-m-d H:i:s'),
                'type' => $type,
                'reason' => $this->normalizeNullableString($window['reason'] ?? null),
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => [$left['start_local'], $left['end_local']] <=> [$right['start_local'], $right['end_local']]);

        return array_values($normalized);
    }

    /**
     * @return array<string,mixed>
     */
    public function normalizeBookingPolicyPayload(mixed $value): array
    {
        if ($value === null) {
            return $this->defaultBookingPolicy();
        }

        if (! is_array($value)) {
            $this->throwValidation('booking_policy', 'Booking policy payload must be an array.');
        }

        return $this->normalizeBookingPolicyArray($value, 'booking_policy');
    }

    public function branchTimezone(mixed $branchId = null, bool $activeOnly = true): string
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (string) $context['timezone'];
    }

    /**
     * @return array{is_open:bool,reason:?string,branch_id:int,timezone:string,checked_at_local:string}
     */
    public function currentOpenStatus(mixed $branchId = null, ?CarbonInterface $at = null, bool $activeOnly = true): array
    {
        $context = $this->resolveContext($branchId, $activeOnly);
        $timezone = (string) $context['timezone'];
        $localPoint = ($at instanceof CarbonInterface ? CarbonImmutable::instance($at) : CarbonImmutable::now())
            ->setTimezone($timezone);

        $isWithinBusinessHours = $this->pointWithinBusinessHours((array) $context['business_hours'], $localPoint);
        $closureWindow = $this->firstOverlappingClosureWindow(
            (array) $context['closure_windows'],
            $timezone,
            $localPoint,
            $localPoint->addMinute()
        );

        $reason = null;
        if (! $isWithinBusinessHours) {
            $reason = 'outside_business_hours';
        }
        if ($closureWindow !== null) {
            $reason = 'closure_window';
        }

        return [
            'is_open' => $isWithinBusinessHours && $closureWindow === null,
            'reason' => $reason,
            'branch_id' => (int) $context['branch_id'],
            'timezone' => $timezone,
            'checked_at_local' => $localPoint->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{
     *   bookable:bool,
     *   reasons:list<string>,
     *   message:?string,
     *   branch_id:int,
     *   timezone:string
     * }
     */
    public function schedulingReadiness(mixed $branchId = null, bool $activeOnly = true): array
    {
        $branch = $this->resolveBranch($branchId, $activeOnly);
        $reasons = [];
        $timezone = $this->defaultTimezone();

        $configuredTimezone = trim((string) ($branch->timezone ?? ''));
        if ($configuredTimezone === '') {
            $reasons[] = 'branch_timezone_missing';
        } elseif (! $this->isValidTimezone($configuredTimezone)) {
            $reasons[] = 'branch_timezone_invalid';
        } else {
            $timezone = $configuredTimezone;
        }

        try {
            $context = $this->resolveContext($branchId, $activeOnly);
            $timezone = (string) $context['timezone'];

            if (! $this->hasConfiguredBusinessHours($context['business_hours'] ?? null)) {
                $reasons[] = 'business_hours_missing';
            }

            if (! $this->hasConfiguredBookingPolicy($context['booking_policy'] ?? null)) {
                $reasons[] = 'booking_policy_missing';
            }
        } catch (ValidationException) {
            $reasons[] = 'branch_scheduling_invalid';
        }

        return [
            'bookable' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'message' => $reasons === []
                ? null
                : $this->branchSchedulingUnavailableMessage(),
            'branch_id' => (int) $branch->branch_id,
            'timezone' => $timezone,
        ];
    }

    public function customerCancellationCutoffMinutes(mixed $branchId = null, bool $activeOnly = true): int
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (int) data_get($context, 'booking_policy.reservation.cancellation_cutoff_minutes', (int) config('booking.customer_reservation_cancellation_cutoff_minutes', 30));
    }

    public function customerRescheduleCutoffMinutes(mixed $branchId = null, bool $activeOnly = true): int
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (int) data_get($context, 'booking_policy.reservation.reschedule_cutoff_minutes', (int) config('booking.customer_reservation_reschedule_cutoff_minutes', 120));
    }

    public function waitingListEnabled(mixed $branchId = null, bool $activeOnly = true): bool
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (bool) data_get($context, 'booking_policy.waiting_list.enabled', true);
    }

    public function waitingListNotifyHoldMinutes(mixed $branchId = null, bool $activeOnly = true): int
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (int) data_get($context, 'booking_policy.waiting_list.notify_hold_minutes', (int) config('booking.waiting_list_notify_hold_minutes', 10));
    }

    public function waitingListServiceMinutes(mixed $branchId = null, bool $activeOnly = true): int
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (int) data_get($context, 'booking_policy.waiting_list.default_service_minutes', (int) config('booking.waiting_list_service_minutes', 120));
    }

    public function availabilityBufferMinutes(mixed $branchId = null, bool $activeOnly = true): int
    {
        $context = $this->resolveContext($branchId, $activeOnly);

        return (int) data_get($context, 'booking_policy.availability.service_buffer_minutes', (int) config('booking.service_buffer_minutes', 0));
    }

    /**
     * @return array{allowed:bool,reason:?string,message:?string,branch_id:int,timezone:string}
     */
    public function evaluateReservationWindow(
        mixed $branchId,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?CarbonInterface $nowUtc = null,
        string $useCase = 'reservation',
        bool $activeOnly = true,
    ): array {
        $readiness = $this->schedulingReadiness($branchId, $activeOnly);
        if (($readiness['bookable'] ?? false) !== true) {
            return [
                'allowed' => false,
                'reason' => 'branch_schedule_unavailable',
                'message' => (string) ($readiness['message'] ?? $this->branchSchedulingUnavailableMessage()),
                'branch_id' => (int) ($readiness['branch_id'] ?? 0),
                'timezone' => (string) ($readiness['timezone'] ?? $this->defaultTimezone()),
            ];
        }

        $context = $this->resolveContext($branchId, $activeOnly);
        $timezone = (string) $context['timezone'];
        $startAtUtc = CarbonImmutable::instance($startUtc)->utc();
        $endAtUtc = CarbonImmutable::instance($endUtc)->utc();
        $nowAtUtc = $nowUtc !== null ? CarbonImmutable::instance($nowUtc)->utc() : CarbonImmutable::now('UTC');

        if ($endAtUtc->lessThanOrEqualTo($startAtUtc)) {
            return $this->windowDecision($context, false, 'invalid_range', 'Requested time window must end after it starts.');
        }

        $localStart = $startAtUtc->setTimezone($timezone);
        $localEnd = $endAtUtc->setTimezone($timezone);
        $localNow = $nowAtUtc->setTimezone($timezone);
        $reservationPolicy = (array) data_get($context, 'booking_policy.reservation', []);

        $minLeadMinutes = max(0, (int) ($reservationPolicy['min_lead_time_minutes'] ?? 0));
        $leadThreshold = $minLeadMinutes === 0
            ? $localNow->copy()->startOfMinute()
            : $localNow->copy()->addMinutes($minLeadMinutes);
        if (! $this->bypassesReservationLeadTime($useCase) && $localStart->lessThan($leadThreshold)) {
            return $this->windowDecision(
                $context,
                false,
                'lead_time',
                sprintf(
                    '%s must start at least %d minute(s) ahead in the branch timezone.',
                    $this->windowLabel($useCase),
                    $minLeadMinutes
                )
            );
        }

        $maxAdvanceMinutes = max(1, (int) ($reservationPolicy['max_advance_time_minutes'] ?? (60 * 24 * 365)));
        if ($localStart->greaterThan($localNow->addMinutes($maxAdvanceMinutes))) {
            return $this->windowDecision(
                $context,
                false,
                'max_advance',
                sprintf(
                    '%s exceeds the branch advance booking window of %d minute(s).',
                    $this->windowLabel($useCase),
                    $maxAdvanceMinutes
                )
            );
        }

        $sameDayCutoffTime = $reservationPolicy['same_day_cutoff_time'] ?? null;
        if (
            ! $this->bypassesSameDayCutoff($useCase)
            && is_string($sameDayCutoffTime)
            && $sameDayCutoffTime !== ''
            && $localStart->isSameDay($localNow)
        ) {
            $cutoffMinutes = $this->timeToMinutes($sameDayCutoffTime, 'booking_policy.reservation.same_day_cutoff_time');
            $cutoffAt = $localNow->startOfDay()->addMinutes($cutoffMinutes);
            if ($localNow->greaterThanOrEqualTo($cutoffAt)) {
                return $this->windowDecision(
                    $context,
                    false,
                    'same_day_cutoff',
                    sprintf(
                        'Same-day %s requests close at %s in the branch timezone.',
                        $useCase === 'availability' ? 'availability' : 'reservation',
                        $sameDayCutoffTime
                    )
                );
            }
        }

        if (! $this->windowWithinBusinessHours((array) $context['business_hours'], $localStart, $localEnd)) {
            return $this->windowDecision(
                $context,
                false,
                'outside_business_hours',
                sprintf('%s falls outside the configured branch business hours.', $this->windowLabel($useCase))
            );
        }

        $closureWindow = $this->firstOverlappingClosureWindow((array) $context['closure_windows'], $timezone, $localStart, $localEnd);
        if ($closureWindow !== null) {
            $reason = $closureWindow['reason'] ?? null;

            return $this->windowDecision(
                $context,
                false,
                'closure_window',
                $reason !== null && trim((string) $reason) !== ''
                    ? sprintf('%s overlaps a branch closure window: %s.', $this->windowLabel($useCase), $reason)
                    : sprintf('%s overlaps a branch closure or blackout window.', $this->windowLabel($useCase))
            );
        }

        return $this->windowDecision($context, true, null, null);
    }

    public function assertReservationWindowAllowed(
        mixed $branchId,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        string $field = 'reservation',
        ?CarbonInterface $nowUtc = null,
        string $useCase = 'reservation',
        bool $activeOnly = true,
    ): void {
        $evaluation = $this->evaluateReservationWindow($branchId, $startUtc, $endUtc, $nowUtc, $useCase, $activeOnly);

        if (($evaluation['allowed'] ?? false) !== true) {
            throw ValidationException::withMessages([
                $field => [(string) ($evaluation['message'] ?? 'Requested branch-local scheduling policy rejected the window.')],
            ]);
        }
    }

    /**
     * @return array{allowed:bool,reason:?string,message:?string,branch_id:int,timezone:string}
     */
    public function evaluateAvailabilityWindow(
        mixed $branchId,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?CarbonInterface $nowUtc = null,
        bool $activeOnly = true,
    ): array {
        return $this->evaluateReservationWindow($branchId, $startUtc, $endUtc, $nowUtc, 'availability', $activeOnly);
    }

    public function assertWaitingListEligible(
        mixed $branchId,
        ?CarbonInterface $requestedAtUtc = null,
        string $field = 'branch_id',
        bool $activeOnly = true,
    ): void {
        $readiness = $this->schedulingReadiness($branchId, $activeOnly);
        if (($readiness['bookable'] ?? false) !== true) {
            throw ValidationException::withMessages([
                $field => [(string) ($readiness['message'] ?? $this->branchSchedulingUnavailableMessage())],
            ]);
        }

        $context = $this->resolveContext($branchId, $activeOnly);
        if (! (bool) data_get($context, 'booking_policy.waiting_list.enabled', true)) {
            throw ValidationException::withMessages([
                $field => ['Waiting list is disabled for the selected branch.'],
            ]);
        }

        $timezone = (string) $context['timezone'];
        $pointUtc = $requestedAtUtc !== null ? CarbonImmutable::instance($requestedAtUtc)->utc() : CarbonImmutable::now('UTC');
        $localPoint = $pointUtc->setTimezone($timezone);

        if (! $this->pointWithinBusinessHours((array) $context['business_hours'], $localPoint)) {
            throw ValidationException::withMessages([
                $field => ['Waiting list is only available while the branch is open.'],
            ]);
        }

        $closureWindow = $this->firstOverlappingClosureWindow(
            (array) $context['closure_windows'],
            $timezone,
            $localPoint,
            $localPoint->addMinute()
        );
        if ($closureWindow !== null) {
            $reason = $closureWindow['reason'] ?? null;

            throw ValidationException::withMessages([
                $field => [$reason !== null && trim((string) $reason) !== ''
                    ? sprintf('Waiting list is unavailable because the branch is closed: %s.', $reason)
                    : 'Waiting list is unavailable during a configured branch closure or blackout window.'],
            ]);
        }
    }

    public function assertOperationalServiceWindowOpen(
        mixed $branchId,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        string $field = 'branch_id',
        bool $activeOnly = true,
    ): void {
        $readiness = $this->schedulingReadiness($branchId, $activeOnly);
        if (($readiness['bookable'] ?? false) !== true) {
            throw ValidationException::withMessages([
                $field => [(string) ($readiness['message'] ?? $this->branchSchedulingUnavailableMessage())],
            ]);
        }

        $context = $this->resolveContext($branchId, $activeOnly);
        $timezone = (string) $context['timezone'];
        $localStart = CarbonImmutable::instance($startUtc)->utc()->setTimezone($timezone);
        $localEnd = CarbonImmutable::instance($endUtc)->utc()->setTimezone($timezone);

        if ($localEnd->lessThanOrEqualTo($localStart)) {
            throw ValidationException::withMessages([
                $field => ['Service session must end after it starts.'],
            ]);
        }

        if (! $this->windowWithinBusinessHours((array) $context['business_hours'], $localStart, $localEnd)) {
            throw ValidationException::withMessages([
                $field => ['Walk-in service sessions are only allowed while the branch is open.'],
            ]);
        }

        $closureWindow = $this->firstOverlappingClosureWindow(
            (array) $context['closure_windows'],
            $timezone,
            $localStart,
            $localEnd,
        );

        if ($closureWindow !== null) {
            $reason = $closureWindow['reason'] ?? null;

            throw ValidationException::withMessages([
                $field => [$reason !== null && trim((string) $reason) !== ''
                    ? sprintf('Walk-in service sessions are unavailable because the branch is closed: %s.', $reason)
                    : 'Walk-in service sessions are unavailable during a configured branch closure or blackout window.'],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array{allowed:bool,reason:?string,message:?string,branch_id:int,timezone:string}
     */
    private function windowDecision(array $context, bool $allowed, ?string $reason, ?string $message): array
    {
        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'message' => $message,
            'branch_id' => (int) $context['branch_id'],
            'timezone' => (string) $context['timezone'],
        ];
    }

    /**
     * @param  array<int,mixed>  $value
     * @return array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>>
     */
    private function normalizeBusinessHoursArray(array $value, string $field): array
    {
        $days = [];
        for ($day = 0; $day <= 6; $day++) {
            $days[$day] = [];
        }

        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                $this->throwValidation($field, sprintf('Business hours row #%d must be an object.', $index));
            }

            $dayOfWeek = filter_var($row['day_of_week'] ?? null, FILTER_VALIDATE_INT);
            if ($dayOfWeek === false || $dayOfWeek < 0 || $dayOfWeek > 6) {
                $this->throwValidation(
                    sprintf('%s.%d.day_of_week', $field, $index),
                    'day_of_week must be an integer between 0 (Sunday) and 6 (Saturday).'
                );
            }

            $isClosed = $this->toBool($row['is_closed'] ?? false);
            $periods = $isClosed ? [] : ($row['periods'] ?? []);
            if (! is_array($periods)) {
                $this->throwValidation(
                    sprintf('%s.%d.periods', $field, $index),
                    'Business hours periods must be an array.'
                );
            }

            foreach ($periods as $periodIndex => $period) {
                if (! is_array($period)) {
                    $this->throwValidation(
                        sprintf('%s.%d.periods.%d', $field, $index, $periodIndex),
                        'Business hours period must be an object.'
                    );
                }

                $startTime = $this->normalizeTimeValue(
                    $period['start_time'] ?? null,
                    sprintf('%s.%d.periods.%d.start_time', $field, $index, $periodIndex)
                );
                $endTime = $this->normalizeTimeValue(
                    $period['end_time'] ?? null,
                    sprintf('%s.%d.periods.%d.end_time', $field, $index, $periodIndex)
                );

                if ($startTime === $endTime) {
                    $this->throwValidation(
                        sprintf('%s.%d.periods.%d.end_time', $field, $index, $periodIndex),
                        'Business hours end_time must differ from start_time.'
                    );
                }

                $days[(int) $dayOfWeek][] = [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }
        }

        $normalized = [];
        for ($day = 0; $day <= 6; $day++) {
            $periods = collect($days[$day])
                ->unique(static fn (array $period): string => $period['start_time'].'|'.$period['end_time'])
                ->sortBy(fn (array $period): int => $this->timeToMinutes($period['start_time'], $field))
                ->values()
                ->all();

            $normalized[] = [
                'day_of_week' => $day,
                'periods' => $periods,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $value
     * @return array<string,mixed>
     */
    private function normalizeBookingPolicyArray(array $value, string $field): array
    {
        $defaults = (array) config('booking.branch_policy_defaults.booking_policy', []);
        $reservationDefaults = is_array($defaults['reservation'] ?? null) ? $defaults['reservation'] : [];
        $waitingListDefaults = is_array($defaults['waiting_list'] ?? null) ? $defaults['waiting_list'] : [];
        $availabilityDefaults = is_array($defaults['availability'] ?? null) ? $defaults['availability'] : [];

        $reservationInput = $value['reservation'] ?? [];
        $waitingListInput = $value['waiting_list'] ?? [];
        $availabilityInput = $value['availability'] ?? [];

        if (! is_array($reservationInput)) {
            $this->throwValidation($field.'.reservation', 'booking_policy.reservation must be an object.');
        }
        if (! is_array($waitingListInput)) {
            $this->throwValidation($field.'.waiting_list', 'booking_policy.waiting_list must be an object.');
        }
        if (! is_array($availabilityInput)) {
            $this->throwValidation($field.'.availability', 'booking_policy.availability must be an object.');
        }

        $reservation = array_merge($reservationDefaults, $reservationInput);
        $waitingList = array_merge($waitingListDefaults, $waitingListInput);
        $availability = array_merge($availabilityDefaults, $availabilityInput);

        $sameDayCutoffTime = $reservation['same_day_cutoff_time'] ?? null;
        if ($sameDayCutoffTime !== null && trim((string) $sameDayCutoffTime) !== '') {
            $sameDayCutoffTime = $this->normalizeTimeValue($sameDayCutoffTime, $field.'.reservation.same_day_cutoff_time');
        } else {
            $sameDayCutoffTime = null;
        }

        return [
            'reservation' => [
                'min_lead_time_minutes' => $this->normalizeIntegerRange(
                    $reservation['min_lead_time_minutes'] ?? 0,
                    0,
                    60 * 24 * 365,
                    $field.'.reservation.min_lead_time_minutes'
                ),
                'max_advance_time_minutes' => $this->normalizeIntegerRange(
                    $reservation['max_advance_time_minutes'] ?? (60 * 24 * 365),
                    1,
                    60 * 24 * 365 * 5,
                    $field.'.reservation.max_advance_time_minutes'
                ),
                'same_day_cutoff_time' => $sameDayCutoffTime,
                'cancellation_cutoff_minutes' => $this->normalizeIntegerRange(
                    $reservation['cancellation_cutoff_minutes'] ?? config('booking.customer_reservation_cancellation_cutoff_minutes', 30),
                    0,
                    60 * 24 * 7,
                    $field.'.reservation.cancellation_cutoff_minutes'
                ),
                'reschedule_cutoff_minutes' => $this->normalizeIntegerRange(
                    $reservation['reschedule_cutoff_minutes'] ?? config('booking.customer_reservation_reschedule_cutoff_minutes', 120),
                    0,
                    60 * 24 * 7,
                    $field.'.reservation.reschedule_cutoff_minutes'
                ),
            ],
            'waiting_list' => [
                'enabled' => $this->toBool($waitingList['enabled'] ?? true),
                'notify_hold_minutes' => $this->normalizeIntegerRange(
                    $waitingList['notify_hold_minutes'] ?? config('booking.waiting_list_notify_hold_minutes', 10),
                    1,
                    60,
                    $field.'.waiting_list.notify_hold_minutes'
                ),
                'default_service_minutes' => $this->normalizeIntegerRange(
                    $waitingList['default_service_minutes'] ?? config('booking.waiting_list_service_minutes', 120),
                    30,
                    480,
                    $field.'.waiting_list.default_service_minutes'
                ),
            ],
            'availability' => [
                'service_buffer_minutes' => $this->normalizeIntegerRange(
                    $availability['service_buffer_minutes'] ?? config('booking.service_buffer_minutes', 0),
                    0,
                    240,
                    $field.'.availability.service_buffer_minutes'
                ),
            ],
        ];
    }

    private function pointWithinBusinessHours(array $businessHours, CarbonImmutable $point): bool
    {
        foreach ($this->buildBusinessIntervals($businessHours, $point, $point->addMinute()) as $interval) {
            if ($point->greaterThanOrEqualTo($interval['start']) && $point->lessThan($interval['end'])) {
                return true;
            }
        }

        return false;
    }

    private function windowWithinBusinessHours(array $businessHours, CarbonImmutable $localStart, CarbonImmutable $localEnd): bool
    {
        foreach ($this->buildBusinessIntervals($businessHours, $localStart, $localEnd) as $interval) {
            if ($localStart->greaterThanOrEqualTo($interval['start']) && $localEnd->lessThanOrEqualTo($interval['end'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>> $businessHours
     * @return array<int, array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    private function buildBusinessIntervals(array $businessHours, CarbonImmutable $localStart, CarbonImmutable $localEnd): array
    {
        $businessHoursByDay = [];
        foreach ($businessHours as $dayConfig) {
            $businessHoursByDay[(int) ($dayConfig['day_of_week'] ?? 0)] = is_array($dayConfig['periods'] ?? null)
                ? $dayConfig['periods']
                : [];
        }

        $intervals = [];
        $dayCursor = $localStart->startOfDay()->subDay();
        $lastDay = $localEnd->startOfDay();

        while ($dayCursor->lessThanOrEqualTo($lastDay)) {
            $periods = $businessHoursByDay[(int) $dayCursor->dayOfWeek] ?? [];
            foreach ($periods as $period) {
                $startMinutes = $this->timeToMinutes((string) $period['start_time'], 'business_hours.start_time');
                $endMinutes = $this->timeToMinutes((string) $period['end_time'], 'business_hours.end_time');

                $intervalStart = $dayCursor->startOfDay()->addMinutes($startMinutes);
                $intervalEnd = $endMinutes === 1440
                    ? $dayCursor->startOfDay()->addDay()
                    : $dayCursor->startOfDay()->addMinutes($endMinutes);

                if ($endMinutes <= $startMinutes) {
                    $intervalEnd = $intervalEnd->addDay();
                }

                $intervals[] = [
                    'start' => $intervalStart,
                    'end' => $intervalEnd,
                ];
            }

            $dayCursor = $dayCursor->addDay();
        }

        usort($intervals, static fn (array $left, array $right): int => [$left['start']->getTimestamp(), $left['end']->getTimestamp()] <=> [$right['start']->getTimestamp(), $right['end']->getTimestamp()]);

        $merged = [];
        foreach ($intervals as $interval) {
            if ($merged === []) {
                $merged[] = $interval;

                continue;
            }

            $lastIndex = array_key_last($merged);
            if ($lastIndex === null) {
                $merged[] = $interval;

                continue;
            }

            $previous = $merged[$lastIndex];
            if ($interval['start']->lessThanOrEqualTo($previous['end'])) {
                if ($interval['end']->greaterThan($previous['end'])) {
                    $merged[$lastIndex]['end'] = $interval['end'];
                }

                continue;
            }

            $merged[] = $interval;
        }

        return $merged;
    }

    private function hasConfiguredBusinessHours(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $periods = $row['periods'] ?? null;
            if (! is_array($periods) || $periods === []) {
                continue;
            }

            foreach ($periods as $period) {
                if (
                    is_array($period)
                    && trim((string) ($period['start_time'] ?? '')) !== ''
                    && trim((string) ($period['end_time'] ?? '')) !== ''
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasConfiguredBookingPolicy(mixed $value): bool
    {
        return is_array($value)
            && is_array($value['reservation'] ?? null)
            && is_array($value['waiting_list'] ?? null)
            && is_array($value['availability'] ?? null);
    }

    private function isValidTimezone(string $timezone): bool
    {
        try {
            new \DateTimeZone(trim($timezone));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function branchSchedulingUnavailableMessage(): string
    {
        return 'Branch booking is unavailable until scheduling configuration is completed.';
    }

    /**
     * @param  array<int, array{start_local:string,end_local:string,type:string,reason:?string}>  $closureWindows
     * @return array{start_local:string,end_local:string,type:string,reason:?string}|null
     */
    private function firstOverlappingClosureWindow(
        array $closureWindows,
        string $timezone,
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd,
    ): ?array {
        foreach ($closureWindows as $closureWindow) {
            $closureStart = $this->normalizeLocalDateTime($closureWindow['start_local'] ?? null, $timezone, 'closure_windows.start_local');
            $closureEnd = $this->normalizeLocalDateTime($closureWindow['end_local'] ?? null, $timezone, 'closure_windows.end_local');

            if ($closureStart->lessThan($localEnd) && $closureEnd->greaterThan($localStart)) {
                return $closureWindow;
            }
        }

        return null;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return $this->defaultTimezone();
        }

        try {
            new \DateTimeZone($timezone);

            return $timezone;
        } catch (\Throwable) {
            return $this->defaultTimezone();
        }
    }

    private function defaultTimezone(): string
    {
        $configured = trim((string) config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC')));
        if ($configured === '') {
            return 'UTC';
        }

        try {
            new \DateTimeZone($configured);

            return $configured;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private function normalizeLocalDateTime(mixed $value, string $timezone, string $field): CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            $this->throwValidation($field, sprintf('%s is required.', $field));
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->setTimezone($timezone);
        } catch (\Throwable) {
            $this->throwValidation($field, sprintf('%s must be a valid datetime.', $field));
        }
    }

    private function normalizeTimeValue(mixed $value, string $field): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            $this->throwValidation($field, sprintf('%s is required.', $field));
        }

        if ($value === '24:00') {
            return $value;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            $this->throwValidation($field, sprintf('%s must use HH:MM and may use 24:00 only as an end time.', $field));
        }

        return $value;
    }

    private function timeToMinutes(string $value, string $field): int
    {
        $value = $this->normalizeTimeValue($value, $field);
        if ($value === '24:00') {
            return 1440;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return ($hours * 60) + $minutes;
    }

    private function normalizeIntegerRange(mixed $value, int $min, int $max, string $field): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false || $normalized < $min || $normalized > $max) {
            $this->throwValidation($field, sprintf('%s must be an integer between %d and %d.', $field, $min, $max));
        }

        return (int) $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }

    private function windowLabel(string $useCase): string
    {
        return match ($useCase) {
            'availability' => 'Requested availability window',
            'hold' => 'Requested hold window',
            'waiting_list' => 'Requested waiting-list window',
            'waiting_list_seat' => 'Requested waiting-list seating window',
            default => 'Requested reservation window',
        };
    }

    private function bypassesReservationLeadTime(string $useCase): bool
    {
        return in_array($useCase, ['availability', 'waiting_list_seat'], true);
    }

    private function bypassesSameDayCutoff(string $useCase): bool
    {
        return $useCase === 'waiting_list_seat';
    }

    private function throwValidation(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
