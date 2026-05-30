<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lớp này là Proxy/Facade chuyên xử lý các Request từ ứng dụng của Khách hàng (Customer Web/App).
 * Nhiệm vụ chính: Xác thực quyền sở hữu (IDOR protection), kiểm tra luật nhà hàng (Policies),
 * và ủy quyền thực thi xuống các Core Service.
 */
class CustomerReservationSelfService
{
    public function __construct(
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
        private readonly ReservationService $reservationService,
        private readonly ReservationRescheduleService $reservationRescheduleService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    /**
     * --- BƯỚC 1: LẤY DANH SÁCH ĐẶT BÀN CỦA KHÁCH ---
     *
     * @param  array<string,mixed>  $filters
     * @return array{scope:string,paginator:LengthAwarePaginator}
     */
    public function listAccessibleReservations(?int $customerUserId, ?string $sessionId, array $filters = []): array
    {
        // Luồng 1: Dành cho khách có tài khoản (OWNER). Lấy toàn bộ lịch sử theo user_id.
        if ($customerUserId !== null) {
            return [
                'scope' => ReservationAccessScope::OWNER,
                'paginator' => $this->paginateOwnerReservations($customerUserId, $filters),
            ];
        }

        // Luồng 2: Dành cho khách vãng lai (SESSION). Chỉ lấy ra đúng cái đơn mà khách đang xem qua Link ẩn danh.
        if ($sessionId !== null && trim($sessionId) !== '') {
            return [
                'scope' => ReservationAccessScope::SESSION,
                'paginator' => $this->paginateSessionReservations(trim($sessionId), $filters),
            ];
        }

        throw ValidationException::withMessages([
            'customer' => ['Customer authentication or a valid session_id is required.'],
        ]);
    }

    /**
     * --- BƯỚC 2: KHÁCH HÀNG TỰ HỦY BÀN ---
     *
     * @param  array<string,mixed>  $payload
     */
    public function cancelAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload = []): Reservation
    {
        // 2.1: Xác thực quyền sở hữu (Chống IDOR)
        $reservation = $this->findAccessibleReservationOrFail($reservationId, $customerUserId, $sessionId);

        // 2.2: Thẩm định Luật nhà hàng (Domain Policy) - Khách có ĐƯỢC PHÉP hủy lúc này không?
        $this->assertCustomerCanCancel($reservation);

        $actorUserId = $customerUserId;
        $cancelReason = isset($payload['cancel_reason']) && trim((string) $payload['cancel_reason']) !== ''
            ? trim((string) $payload['cancel_reason'])
            : 'Cancelled by customer self-service';

        // 2.3: Ủy quyền cho Core Service thực thi việc Hủy (kéo theo hủy món, nhả bàn, hoàn tiền...)
        $updatedReservation = $this->reservationService->updateReservationStatus(
            reservationId: $reservationId,
            newStatus: ReservationStatus::Cancelled->value,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            actorUserId: $actorUserId,
            options: [
                'actor_type' => 'customer',
                'cancel_reason' => $cancelReason,
                'force' => false, // Tuyệt đối không cho phép Khách hàng Force Cancel (Ép hủy)
                // Lưu vết (Audit) nếu khách vãng lai tự hủy
                'audit_context' => $sessionId !== null && trim($sessionId) !== '' && $customerUserId === null
                    ? ['customer_session_id' => trim($sessionId)]
                    : [],
            ],
        );

        return $this->refreshReservationForScope($updatedReservation, $customerUserId);
    }

    /**
     * --- BƯỚC 3: KHÁCH HÀNG TỰ DỜI LỊCH (RESCHEDULE) ---
     *
     * @param  array<string,mixed>  $payload
     */
    public function rescheduleAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload = []): Reservation
    {
        $reservation = $this->findAccessibleReservationOrFail($reservationId, $customerUserId, $sessionId);

        // Thẩm định Luật: Khách có được phép dời lịch lúc này không?
        $this->assertCustomerCanReschedule($reservation);

        $actorUserId = $customerUserId;
        $normalizedPayload = $payload;
        $normalizedPayload['reason'] = isset($payload['reason']) && trim((string) $payload['reason']) !== ''
            ? trim((string) $payload['reason'])
            : 'Customer self-service reschedule';

        // Ủy quyền cho Reschedule Service (Service này sẽ lo việc check xem giờ mới có trống bàn không)
        $updatedReservation = $this->reservationRescheduleService->reschedule($reservationId, $normalizedPayload, [
            'type' => $customerUserId !== null ? 'customer' : 'customer_session',
            'user_id' => $actorUserId,
            'session_id' => $customerUserId === null && $sessionId !== null ? trim($sessionId) : null,
        ]);

        return $this->refreshReservationForScope($updatedReservation, $customerUserId);
    }

    /**
     * --- LÕI BẢO MẬT: TÌM VÀ XÁC THỰC QUYỀN SỞ HỮU ---
     * Ngăn chặn khách A lấy ID bàn của khách B truyền vào API để hủy hoại.
     */
    public function findAccessibleReservationOrFail(int $reservationId, ?int $customerUserId, ?string $sessionId): Reservation
    {
        if ($customerUserId !== null) {
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()
                ->where('reservation_id', $reservationId)
                ->where('user_id', $customerUserId) // Ép cứng điều kiện user_id phải là của user đang đăng nhập
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw new ModelNotFoundException('Reservation not found.');
        }

        $sessionId = trim((string) $sessionId);
        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()->find($reservationId);

        if (! $reservation instanceof Reservation || $sessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId)) {
            throw new ModelNotFoundException('Reservation not found.');
        }

        return $reservation;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function paginateOwnerReservations(int $customerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $bucket = $this->resolveBucket($filters);
        $statuses = $this->resolveStatuses($filters);

        $query = Reservation::query()
            ->where('user_id', $customerUserId)
            ->with($this->relationsForScope(ReservationAccessScope::OWNER));

        $this->applyBucketFilter($query, $bucket);
        $this->applyStatusFilter($query, $statuses);

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', Carbon::parse((string) $filters['from'])->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', Carbon::parse((string) $filters['to'])->utc());
        }

        $this->applySort($query, $bucket);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function paginateSessionReservations(string $sessionId, array $filters): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $bucket = $this->resolveBucket($filters);
        $statuses = $this->resolveStatuses($filters);

        // Khách vãng lai tìm lại các đơn của mình thông qua lịch sử Session nằm trong bảng table_holds
        $reservationIds = DB::table('table_holds')
            ->where('session_id', $sessionId)
            ->whereNotNull('confirmed_reservation_id')
            ->orderByDesc('created_at')
            ->pluck('confirmed_reservation_id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($reservationIds === []) {
            return $this->paginateCollection(collect(), $perPage, $page, $filters);
        }

        $query = Reservation::query()
            ->whereIn('reservation_id', $reservationIds)
            ->with($this->relationsForScope(ReservationAccessScope::SESSION));

        $this->applyBucketFilter($query, $bucket);
        $this->applyStatusFilter($query, $statuses);

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', Carbon::parse((string) $filters['from'])->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', Carbon::parse((string) $filters['to'])->utc());
        }

        $this->applySort($query, $bucket);

        $reservations = $query->get()
            ->filter(fn (Reservation $reservation) => $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId))
            ->values();

        return $this->paginateCollection($reservations, $perPage, $page, $filters);
    }

    /**
     * --- LUẬT NHÀ HÀNG (DOMAIN POLICY): HỦY BÀN ---
     */
    private function assertCustomerCanCancel(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        // Chỉ được hủy khi đơn đang ở trạng thái Confirmed (Đã xác nhận)
        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be cancelled by the customer self-service flow.'],
            ]);
        }

        // Khách đã bước vào quán, lễ tân bấm Check-in rồi thì không thể tự cầm điện thoại ấn Hủy bàn được nữa
        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be cancelled from customer self-service.'],
            ]);
        }

        // CUT-OFF TIME POLICY (Luật thời gian chốt chặn):
        // Lấy cấu hình của chi nhánh đó xem "Cho phép khách tự hủy trước bao nhiêu phút?"
        // VD: Quán lẩu quy định 120 phút. Nếu khách đặt 19:00, mà 18:00 khách mới vào web bấm Hủy -> Báo lỗi!
        // Giúp bảo vệ nhà hàng khỏi rủi ro bàn trống sát giờ (No-show trá hình) và lãng phí thực phẩm đã rã đông.
        $cutoffMinutes = max(0, $this->branchSchedulingPolicyService->customerCancellationCutoffMinutes($reservation->branch_id, false));
        $cutoffAt = $reservation->start_time->copy()->utc()->subMinutes($cutoffMinutes);
        if (Carbon::now('UTC')->gte($cutoffAt)) {
            throw ValidationException::withMessages([
                'reservation' => [sprintf('Reservation can only be cancelled at least %d minute(s) before the start time.', $cutoffMinutes)],
            ]);
        }
    }

    private function refreshReservationForScope(Reservation $reservation, ?int $customerUserId): Reservation
    {
        $scope = $customerUserId !== null ? ReservationAccessScope::OWNER : ReservationAccessScope::SESSION;

        return Reservation::query()
            ->with($this->relationsForScope($scope))
            ->findOrFail((int) $reservation->reservation_id);
    }

    /**
     * --- LUẬT NHÀ HÀNG (DOMAIN POLICY): DỜI LỊCH ---
     */
    private function assertCustomerCanReschedule(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be rescheduled by the customer self-service flow.'],
            ]);
        }

        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be rescheduled from customer self-service.'],
            ]);
        }

        // CUT-OFF TIME Tương tự như Hủy bàn: Không cho phép dời lịch quá sát giờ.
        $cutoffMinutes = max(0, $this->branchSchedulingPolicyService->customerRescheduleCutoffMinutes($reservation->branch_id, false));
        $cutoffAt = $reservation->start_time->copy()->utc()->subMinutes($cutoffMinutes);
        if (Carbon::now('UTC')->gte($cutoffAt)) {
            throw ValidationException::withMessages([
                'reservation' => [sprintf('Reservation can only be rescheduled at least %d minute(s) before the start time.', $cutoffMinutes)],
            ]);
        }
    }

    private function resolvePerPage(array $filters): int
    {
        $max = max(1, (int) config('booking.customer_reservation_self_service_page_max', 20));
        $default = min($max, max(1, (int) config('booking.customer_reservation_self_service_page_default', 10)));
        $requested = isset($filters['per_page']) ? (int) $filters['per_page'] : $default;

        return max(1, min($max, $requested));
    }

    /**
     * --- UI/UX: PHÂN LOẠI TAB (BUCKET) ---
     * Tách luồng dữ liệu thành Tab "Sắp tới" (Upcoming) và Tab "Lịch sử" (History)
     *
     * @param  array<string,mixed>  $filters
     */
    private function resolveBucket(array $filters): string
    {
        $bucket = strtolower(trim((string) ($filters['bucket'] ?? 'upcoming')));

        return in_array($bucket, ['upcoming', 'history', 'all'], true) ? $bucket : 'upcoming';
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return list<string>
     */
    private function resolveStatuses(array $filters): array
    {
        $statuses = array_map(
            static fn ($value) => trim((string) $value),
            (array) ($filters['status'] ?? [])
        );

        return array_values(array_filter($statuses, static fn (string $value) => $value !== ''));
    }

    private function applyBucketFilter($query, string $bucket): void
    {
        $now = Carbon::now('UTC');

        // Upcoming: Các đơn chưa kết thúc và chưa quá giờ
        if ($bucket === 'upcoming') {
            $query->where(function ($inner) use ($now): void {
                $inner->whereNotIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::Expired->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::NoShow->value,
                ])->where('end_time', '>=', $now);
            });

            return;
        }

        // History: Các đơn đã bị Hủy, Đã ăn xong (Completed), Bị bom bàn (NoShow) hoặc đã trôi qua thời gian hiện tại
        if ($bucket === 'history') {
            $query->where(function ($inner) use ($now): void {
                $inner->whereIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::Expired->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::NoShow->value,
                ])->orWhere('end_time', '<', $now);
            });
        }
    }

    /**
     * @param  list<string>  $statuses
     */
    private function applyStatusFilter($query, array $statuses): void
    {
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }
    }

    private function applySort($query, string $bucket): void
    {
        // Upcoming thì sếp lịch nào sắp diễn ra lên đầu (Tăng dần)
        if ($bucket === 'upcoming') {
            $query->orderBy('start_time')->orderBy('reservation_id');

            return;
        }

        // History thì xếp lịch nào mới ăn xong gần nhất lên đầu (Giảm dần)
        $query->orderByDesc('start_time')->orderByDesc('reservation_id');
    }

    /**
     * @param  Collection<int,Reservation>  $items
     * @param  array<string,mixed>  $filters
     */
    private function paginateCollection(Collection $items, int $perPage, int $page, array $filters): LengthAwarePaginator
    {
        $offset = max(0, ($page - 1) * $perPage);
        $results = $items->slice($offset, $perPage)->values();

        return new Paginator(
            $results,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => '/api/v1/reservations',
                'query' => array_filter($filters, static fn ($value) => $value !== null && $value !== '' && $value !== []),
            ]
        );
    }

    /**
     * --- BẢO MẬT DỮ LIỆU (DATA PRIVACY) ---
     *
     * @return list<string>
     */
    private function relationsForScope(string $scope): array
    {
        // Kỹ thuật Data Minimization:
        // Nếu là khách vãng lai (Session), chỉ trả về thông tin tối thiểu (Bàn, Món ăn).
        // Nếu là chủ tài khoản đăng nhập (Owner), mới trả về lịch sử Thanh toán, Điểm thưởng Loyalty, Hạng thành viên, Voucher.
        return match ($scope) {
            ReservationAccessScope::SESSION => [
                'user',
                'tables',
                'orders.items.item',
            ],
            ReservationAccessScope::OWNER => [
                'user',
                'user.points',
                'user.currentTier',
                'tables',
                'orders.items.item',
                'payments',
                'depositPaymentSessions',
                'appliedUserVoucher.voucher',
            ],
            default => [
                'user',
                'tables',
            ],
        };
    }
}
