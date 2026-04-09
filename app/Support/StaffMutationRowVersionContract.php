<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\Staff\AddOrderItemsRequest;
use App\Http\Requests\Staff\ApplyReservationVoucherRequest;
use App\Http\Requests\Staff\CancelWaitingListRequest;
use App\Http\Requests\Staff\CheckInReservationRequest;
use App\Http\Requests\Staff\CheckoutOrderRequest;
use App\Http\Requests\Staff\CloseOrderRequest;
use App\Http\Requests\Staff\CreateTableOrderRequest;
use App\Http\Requests\Staff\MoveTableRequest;
use App\Http\Requests\Staff\NotifyWaitingListRequest;
use App\Http\Requests\Staff\PayOrderRequest;
use App\Http\Requests\Staff\RedeemReservationPointsRequest;
use App\Http\Requests\Staff\RefundAndCancelReservationRequest;
use App\Http\Requests\Staff\RefundReservationRequest;
use App\Http\Requests\Staff\ReleaseReservationPointsRequest;
use App\Http\Requests\Staff\ReleaseTableRequest;
use App\Http\Requests\Staff\RemoveReservationVoucherRequest;
use App\Http\Requests\Staff\RescheduleReservationRequest;
use App\Http\Requests\Staff\SeatWaitingListRequest;

final class StaffMutationRowVersionContract
{
    /**
     * @return array<class-string, string>
     */
    public static function requestMap(): array
    {
        return [
            AddOrderItemsRequest::class => 'staff.order-items',
            ApplyReservationVoucherRequest::class => 'staff.reservation-voucher-apply',
            CancelWaitingListRequest::class => 'staff.waiting-list-cancel',
            CheckInReservationRequest::class => 'staff.reservation-checkin',
            CheckoutOrderRequest::class => 'staff.checkout',
            CloseOrderRequest::class => 'staff.order-close',
            CreateTableOrderRequest::class => 'staff.table-orders',
            MoveTableRequest::class => 'staff.reservation-move-table',
            NotifyWaitingListRequest::class => 'staff.waiting-list-notify',
            PayOrderRequest::class => 'staff.order-pay',
            RedeemReservationPointsRequest::class => 'staff.reservation-loyalty-redeem',
            RefundAndCancelReservationRequest::class => 'staff.reservation-refund-cancel',
            RefundReservationRequest::class => 'staff.reservation-refund',
            ReleaseReservationPointsRequest::class => 'staff.reservation-loyalty-release',
            ReleaseTableRequest::class => 'staff.table-release',
            RemoveReservationVoucherRequest::class => 'staff.reservation-voucher-remove',
            RescheduleReservationRequest::class => 'staff.reservation-reschedule',
            SeatWaitingListRequest::class => 'staff.waiting-list-seat',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function snapshot(): array
    {
        $missing = [];
        $compliant = [];

        foreach (self::requestMap() as $requestClass => $scope) {
            $rules = self::normalizeRules((new $requestClass())->rules()['row_version'] ?? null);

            if (! in_array('required', $rules, true)) {
                $missing[] = [
                    'request' => $requestClass,
                    'scope' => $scope,
                    'rules' => $rules,
                ];

                continue;
            }

            $compliant[] = [
                'request' => $requestClass,
                'scope' => $scope,
            ];
        }

        return [
            'required_request_count' => count(self::requestMap()),
            'compliant_request_count' => count($compliant),
            'missing_required_count' => count($missing),
            'missing' => $missing,
            'status' => $missing === [] ? 'ok' : 'fail',
            'reasons' => $missing === [] ? [] : ['staff_mutation_row_version_contract_missing'],
        ];
    }

    /**
     * @param mixed $rules
     * @return list<string>
     */
    private static function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (! is_array($rules)) {
            return [];
        }

        return array_values(array_map(
            static fn ($rule): string => is_string($rule) ? $rule : (is_object($rule) ? $rule::class : gettype($rule)),
            $rules,
        ));
    }
}
