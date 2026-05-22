<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Queries\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GetAnalyticsOverviewHandler
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters, int $userId, ?int $roleId, ?string $roleName): array
    {
        // Require date limits
        $dateFrom = Carbon::parse($filters['date_from'] ?? Carbon::today()->toDateString());
        $dateTo = Carbon::parse($filters['date_to'] ?? Carbon::today()->toDateString());
        
        // Enforce max 31 days to prevent heavy queries
        if ($dateFrom->diffInDays($dateTo) > 31) {
            throw new InvalidArgumentException("Date range cannot exceed 31 days.");
        }
        
        // Align to start/end of day
        $dateFromStr = $dateFrom->startOfDay()->toDateTimeString();
        $dateToStr = $dateTo->endOfDay()->toDateTimeString();

        $branchId = $filters['branch_id'] ?? null;
        $granularity = $filters['granularity'] ?? 'day'; // 'day' or 'hour'

        $baseReservationQuery = DB::table('reservations')
            ->whereBetween('start_time', [$dateFromStr, $dateToStr]);
            
        if ($branchId) {
            $baseReservationQuery->where('branch_id', $branchId);
        }

        // 1. Basic Counts
        $statusCounts = (clone $baseReservationQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalReservations = array_sum($statusCounts);
        $cancelledCount = $statusCounts['Cancelled'] ?? 0;
        $noShowCount = $statusCounts['NoShow'] ?? 0;

        // 2. Revenue (based on completed reservations' final_bill_amount or payments)
        // We will use payments for accurate revenue summary by method and time
        $basePaymentQuery = DB::table('payments')
            ->where('status', 'Success')
            ->whereBetween('paid_at', [$dateFromStr, $dateToStr]);
            
        if ($branchId) {
            $basePaymentQuery->where('branch_id', $branchId);
        }

        $paymentSummary = (clone $basePaymentQuery)
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get();

        $totalRevenue = (clone $basePaymentQuery)->sum('amount');

        // 3. Revenue Heatmap (by hour or day)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($isSqlite) {
            $timeFormat = $granularity === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
            $dateExpr = "strftime('{$timeFormat}', paid_at)";
        } else {
            $timeFormat = $granularity === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
            $dateExpr = "DATE_FORMAT(paid_at, '{$timeFormat}')";
        }
        
        $heatmapData = (clone $basePaymentQuery)
            ->select(DB::raw("{$dateExpr} as period"), DB::raw('SUM(amount) as revenue'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 4. Top Menu Items (from reservation_order_items tied to active/completed orders)
        $topItemsQuery = DB::table('reservation_order_items as roi')
            ->join('reservation_orders as ro', 'roi.order_id', '=', 'ro.order_id')
            ->join('reservations as r', 'ro.reservation_id', '=', 'r.reservation_id')
            ->where('roi.status', '!=', 'Cancelled')
            ->whereBetween('r.start_time', [$dateFromStr, $dateToStr]);

        if ($branchId) {
            $topItemsQuery->where('r.branch_id', $branchId);
        }

        $topItems = $topItemsQuery
            ->select('roi.item_name_snapshot as item_name', DB::raw('SUM(roi.quantity) as total_quantity'))
            ->groupBy('roi.item_name_snapshot')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        return [
            'overview' => [
                'total_reservations' => $totalReservations,
                'cancelled_count' => $cancelledCount,
                'no_show_count' => $noShowCount,
                'total_revenue' => (float) $totalRevenue,
            ],
            'payment_summary' => $paymentSummary->map(fn($row) => [
                'method' => $row->payment_method,
                'amount' => (float) $row->total_amount
            ])->toArray(),
            'revenue_heatmap' => $heatmapData->map(fn($row) => [
                'period' => $row->period,
                'revenue' => (float) $row->revenue
            ])->toArray(),
            'top_items' => $topItems->map(fn($row) => [
                'name' => $row->item_name,
                'quantity' => (int) $row->total_quantity
            ])->toArray(),
        ];
    }
}
