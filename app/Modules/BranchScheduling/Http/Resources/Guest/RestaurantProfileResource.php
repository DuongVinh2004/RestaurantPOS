<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Resources\Guest;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantProfileResource extends JsonResource
{
    /**
     * @param  array{branch:Branch,business_hours:array<int,mixed>,open_status:array<string,mixed>}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Branch $branch */
        $branch = $this->resource['branch'];
        $businessHours = (array) ($this->resource['business_hours'] ?? []);
        $openStatus = (array) ($this->resource['open_status'] ?? []);
        $timezone = (string) ($openStatus['timezone'] ?? $branch->timezone ?? config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC')));
        $todayHours = $this->todayHours($businessHours, (int) now($timezone)->dayOfWeek);

        return [
            'branch_id' => (int) $branch->branch_id,
            'branch_code' => (string) $branch->branch_code,
            'branch_name' => (string) $branch->branch_name,
            'timezone' => $timezone,
            'business_hours' => $businessHours,
            'today_hours' => [
                'day_of_week' => $todayHours['day_of_week'],
                'periods' => $todayHours['periods'],
                'is_closed' => $todayHours['periods'] === [],
            ],
            'current_status' => [
                'is_open' => (bool) ($openStatus['is_open'] ?? false),
                'reason' => $openStatus['reason'] ?? null,
                'checked_at_local' => (string) ($openStatus['checked_at_local'] ?? now($timezone)->format('Y-m-d H:i:s')),
                'timezone' => $timezone,
            ],
        ];
    }

    /**
     * @param  array<int,mixed>  $businessHours
     * @return array{day_of_week:int,periods:array<int,mixed>}
     */
    private function todayHours(array $businessHours, int $dayOfWeek): array
    {
        foreach ($businessHours as $row) {
            if (is_array($row) && (int) ($row['day_of_week'] ?? -1) === $dayOfWeek) {
                return [
                    'day_of_week' => $dayOfWeek,
                    'periods' => is_array($row['periods'] ?? null) ? array_values($row['periods']) : [],
                ];
            }
        }

        return [
            'day_of_week' => $dayOfWeek,
            'periods' => [],
        ];
    }
}
