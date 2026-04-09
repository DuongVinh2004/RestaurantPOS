<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\ReservationOrderType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ReservationOrderTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ReservationOrderType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ReservationOrderType) {
            return $value;
        }

        return ReservationOrderType::from($this->normalize((string) $value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ReservationOrderType) {
            return $value->value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Reservation order type must be a string or ReservationOrderType enum.');
        }

        return ReservationOrderType::from($this->normalize($value))->value;
    }

    private function normalize(string $value): string
    {
        return match ($value) {
            'DineIn' => ReservationOrderType::OnSpot->value,
            default => $value,
        };
    }
}
