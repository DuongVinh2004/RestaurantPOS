<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Audit;

use App\Support\AuditEvent;
use App\Support\AuditTrail\AuditTrailActorResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TableStateAuditLogger
{
    /**
     * @param  array<int,mixed>  $beforeRows
     * @param  array<int,mixed>  $afterRows
     * @param  array<string,mixed>  $context
     */
    public static function insertTransitions(array $beforeRows, array $afterRows, string $action, ?int $actorUserId = null, array $context = [], ?Carbon $occurredAt = null): void
    {
        // --- BƯỚC 1: XÂY DỰNG BẢN GHI (BUILD RECORDS) ---
        // So sánh 2 mảng $beforeRows (Trạng thái cũ) và $afterRows (Trạng thái mới) để tìm ra sự khác biệt.
        $records = self::buildTransitionRecords($beforeRows, $afterRows, $action, $actorUserId, $context, $occurredAt);

        if ($records === []) {
            return; // Tránh Hit Database vô ích nếu trạng thái thực tế không hề thay đổi
        }

        // --- BƯỚC 2: CHUẨN HÓA DỮ LIỆU ĐỂ LƯU VÀO DB (JSON ENCODING) ---
        // Best Practice: Lưu Log dưới dạng JSON để linh hoạt lưu trữ được mọi cấu trúc data (NoSQL-like behavior)
        $rows = array_map(static function (array $record): array {
            // Sử dụng các flag JSON_UNESCAPED_UNICODE (để giữ nguyên tiếng Việt) và JSON_UNESCAPED_SLASHES (chống gạch chéo dư thừa)
            $record['before_json'] = $record['before_json'] !== null ? json_encode($record['before_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['after_json'] = $record['after_json'] !== null ? json_encode($record['after_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['summary_json'] = $record['summary_json'] !== null ? json_encode($record['summary_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['meta_json'] = $record['meta_json'] !== null ? json_encode($record['meta_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            return $record;
        }, $records);

        // --- BƯỚC 3: LƯU VÀO CƠ SỞ DỮ LIỆU BẰNG BULK INSERT ---
        try {
            // Thay vì dùng Eloquent Model (AuditLog::create()), dùng DB::table()->insert() để lưu NHIỀU dòng
            // cùng 1 lúc (Bulk Insert) giúp tối ưu hiệu năng tối đa (chỉ mất 1 Network Call).
            DB::table('audit_logs')->insert($rows);
        } catch (Throwable $e) {
            // Fallback an toàn: Việc lưu Log (Audit) THẤT BẠI KHÔNG ĐƯỢC PHÉP làm chết luồng chạy chính của phần mềm.
            // VD: Khách đang thanh toán, lỗi mạng không lưu được Log, thì khách vẫn phải được thanh toán xong.
            // Bắn một cảnh báo nội bộ (Sentry/Slack) để Developer vào kiểm tra DB.
            AuditEvent::warning('table_state_audit_insert_failed', [
                'action' => $action,
                'actor_user_id' => $actorUserId,
                'table_ids' => array_map(static fn (array $record): int => (int) $record['entity_id'], $records),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int,mixed>  $beforeRows
     * @param  array<int,mixed>  $afterRows
     * @param  array<string,mixed>  $context
     * @return array<int,array<string,mixed>>
     */
    public static function buildTransitionRecords(array $beforeRows, array $afterRows, string $action, ?int $actorUserId = null, array $context = [], ?Carbon $occurredAt = null): array
    {
        $occurredAt ??= Carbon::now('UTC');

        // Làm sạch và đồng nhất định dạng dữ liệu (Kiểu Int, String, DateTime)
        $normalizedBefore = self::normalizeRows($beforeRows);
        $normalizedAfter = self::normalizeRows($afterRows);

        // Gộp tất cả các Table ID bị ảnh hưởng lại
        $tableIds = array_values(array_unique(array_merge(array_keys($normalizedBefore), array_keys($normalizedAfter))));
        sort($tableIds);

        // --- THU THẬP METADATA (NGHỀ THÁM TỬ) ---
        // Lấy thông tin về Request HTTP hiện tại (Ai đang gọi API này? Từ IP nào? Thiết bị nào?)
        $request = app()->bound('request') ? request() : null;
        $actor = app(AuditTrailActorResolver::class)->resolve($actorUserId !== null ? ['user_id' => $actorUserId] : []);
        $ip = $request?->ip();
        $userAgent = $request?->userAgent(); // Ví dụ: Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)
        $requestId = $request?->attributes?->get('request_id'); // ID để truy vết log chéo (Distributed Tracing)

        $records = [];
        foreach ($tableIds as $tableId) {
            $before = $normalizedBefore[$tableId] ?? null;
            $after = $normalizedAfter[$tableId] ?? null;

            if ($before === null && $after === null) {
                continue;
            }

            // --- KIỂM TRA SỰ THAY ĐỔI TRẠNG THÁI (STATE MACHINE GUARD) ---
            // Nếu bàn đang Occupied, và lưu lại vẫn là Occupied => Không ghi Log dư thừa để tiết kiệm dung lượng Ổ Cứng
            if (($before['status'] ?? null) === ($after['status'] ?? null)) {
                continue;
            }

            // Kẹp thêm bối cảnh (Context) vào trạng thái Mới.
            // Ví dụ: Bàn đổi thành Available VÌ LÝ DO (Context) "Khách đã thanh toán xong".
            $afterPayload = $after;
            if ($afterPayload !== null && $context !== []) {
                $afterPayload = array_merge($afterPayload, ['context' => $context]);
            }

            // Gói ghém lại thành 1 record hoàn chỉnh
            $records[] = [
                'actor_user_id' => $actor['user_id'] ?? $actorUserId,
                'actor_type' => $actor['type'] ?? null,
                'actor_key' => $actor['key'] ?? null,
                'entity_type' => 'restaurant_table',
                'entity_id' => (string) $tableId,
                'action' => substr($action, 0, 50), // Cắt ngắn để an toàn lưu DB không bị lỗi tràn độ dài cột
                'before_json' => $before,
                'after_json' => $afterPayload,
                'summary_json' => [
                    'from_status' => $before['status'] ?? null,
                    'to_status' => $after['status'] ?? null,
                ],
                'meta_json' => [
                    'source' => 'table_state_audit',
                    'context' => $context !== [] ? $context : null,
                    'request' => array_filter([
                        'request_id' => $requestId !== null ? (string) $requestId : null,
                        'path' => $request?->path(),
                        'method' => $request?->getMethod(),
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ],
                'request_id' => $requestId !== null ? (string) $requestId : null,
                'ip' => $ip,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
                'created_at' => $occurredAt->copy()->utc()->format('Y-m-d H:i:s.u'), // Lưu tới đơn vị Micro-seconds (phần triệu giây)
            ];
        }

        return $records;
    }

    /**
     * @param  array<int,mixed>  $rows
     * @return array<int,array{table_id:int,status:?string,row_version:?int,updated_at:?string}>
     */
    private static function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            // Ép kiểu (Type Casting) cực kỳ cẩn thận từ Eloquent Object hoặc Array thành Array thuần túy
            $tableId = self::extractInt($row, 'table_id');
            if ($tableId <= 0) {
                continue;
            }

            $updatedAt = self::extractScalar($row, 'updated_at');
            // Chuẩn hóa mọi định dạng thời gian về đúng format Y-m-d H:i:s.u chuẩn UTC
            if ($updatedAt instanceof Carbon) {
                $updatedAt = $updatedAt->copy()->utc()->format('Y-m-d H:i:s.u');
            } elseif ($updatedAt instanceof \DateTimeInterface) {
                $updatedAt = Carbon::instance(\DateTimeImmutable::createFromInterface($updatedAt))->utc()->format('Y-m-d H:i:s.u');
            } elseif (is_string($updatedAt)) {
                $updatedAt = trim($updatedAt) !== '' ? $updatedAt : null;
            } else {
                $updatedAt = null;
            }

            $normalized[$tableId] = [
                'table_id' => $tableId,
                'status' => self::extractString($row, 'status'),
                'row_version' => self::extractNullableInt($row, 'row_version'),
                'updated_at' => $updatedAt,
            ];
        }

        // Luôn luôn sắp xếp (Sort) theo TableID để đảm bảo mảng Json tạo ra có tính nhất quán (Consistent)
        ksort($normalized);

        return $normalized;
    }

    // --- CÁC HÀM EXTRACTOR (Trích xuất dữ liệu đa hình) ---
    // Do $row truyền vào có thể là Array (nếu query bằng DB::table),
    // hoặc là Object (nếu dùng Eloquent Model). Phải xử lý đa hình (Polymorphism).

    private static function extractInt(mixed $row, string $key): int
    {
        return (int) (self::extractScalar($row, $key) ?? 0);
    }

    private static function extractNullableInt(mixed $row, string $key): ?int
    {
        $value = self::extractScalar($row, $key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function extractString(mixed $row, string $key): ?string
    {
        $value = self::extractScalar($row, $key);
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private static function extractScalar(mixed $row, string $key): mixed
    {
        // Nhánh 1: Nếu $row là Mảng (Array)
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        // Nhánh 2: Nếu $row là Đối tượng (Object)
        if (is_object($row)) {
            // Đối tượng tiêu chuẩn stdClass
            if (isset($row->{$key}) || property_exists($row, $key)) {
                return $row->{$key};
            }

            // Đối tượng Laravel Eloquent (Dùng getAttribute để lấy giá trị thực)
            if (method_exists($row, 'getAttribute')) {
                return $row->getAttribute($key);
            }
        }

        return null;
    }
}
