<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bucket' => ['sometimes', 'string', Rule::in(['upcoming', 'history', 'all'])],
            'status' => ['sometimes', 'array', 'max:10'],
            'status.*' => ['string', Rule::in(array_map(static fn (ReservationStatus $status) => $status->value, ReservationStatus::cases()))],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.max(1, (int) config('booking.customer_reservation_self_service_page_max', 20))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'session_id' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
