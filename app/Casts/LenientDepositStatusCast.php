<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\DepositStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LenientDepositStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): DepositStatus|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DepositStatus) {
            return $value;
        }

        if (! is_string($value)) {
            return (string) $value;
        }

        return DepositStatus::tryFrom($value) ?? $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DepositStatus) {
            return $value->value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Deposit status must be a string or DepositStatus enum.');
        }

        return $value;
    }
}
