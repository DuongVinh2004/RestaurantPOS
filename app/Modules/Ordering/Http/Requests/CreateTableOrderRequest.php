<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests;

use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateTableOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: nếu không truyền, server sẽ tự resolve reservation đang phục vụ cho bàn
            'reservation_id' => ['nullable', 'integer', 'min:1'],

            // Cho phép tạo order rỗng (POS thường tạo trước, add items sau)
            'items' => ['nullable', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:200'],

            'notes' => ['nullable', 'string', 'max:500'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $reservationId = (int) ($this->input('reservation_id') ?? 0);
            $tableId = (int) ($this->route('table_id') ?? 0);

            if ($reservationId <= 0 || $tableId <= 0) {
                return;
            }

            $belongsToTable = ReservationTable::query()
                ->where('reservation_id', $reservationId)
                ->where('table_id', $tableId)
                ->exists();

            if (! $belongsToTable) {
                $validator->errors()->add('reservation_id', 'The selected reservation does not belong to the target table.');

                return;
            }

            $isReserved = Reservation::query()
                ->where('reservation_id', $reservationId)
                ->where('status', ReservationStatus::checkedInDbValue())
                ->exists();

            if (! $isReserved) {
                $validator->errors()->add('reservation_id', 'The selected reservation is not currently in service.');
            }
        });
    }
}
