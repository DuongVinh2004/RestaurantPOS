<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;

final class Money
{
    public const SCALE = 2;

    public const MINOR_FACTOR = 100;

    public static function minorUnits(mixed $amount, bool $clampNegative = false): int
    {
        $normalized = self::normalizeDecimalString($amount);
        if ($normalized === null) {
            return 0;
        }

        if (! preg_match('/^([+-]?)(?:(\d+)(?:\.(\d*))?|\.(\d+))$/', $normalized, $matches)) {
            return 0;
        }

        $negative = ($matches[1] ?? '') === '-';
        $whole = ($matches[2] ?? '') !== '' ? $matches[2] : '0';
        $fraction = ($matches[3] ?? '') !== '' ? $matches[3] : ($matches[4] ?? '');

        $minor = ((int) $whole) * self::MINOR_FACTOR;
        $minor += (int) str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');

        $roundDigit = $fraction[self::SCALE] ?? '0';
        if ($roundDigit >= '5') {
            $minor++;
        }

        if ($negative) {
            $minor *= -1;
        }

        return $clampNegative && $minor < 0 ? 0 : $minor;
    }

    public static function format(mixed $amount, bool $clampNegative = false): string
    {
        return self::formatMinor(self::minorUnits($amount, $clampNegative));
    }

    public static function formatMinor(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        $whole = intdiv($absolute, self::MINOR_FACTOR);
        $fraction = $absolute % self::MINOR_FACTOR;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, self::SCALE, '0', STR_PAD_LEFT);
    }

    public static function toFloat(mixed $amount, bool $clampNegative = false): float
    {
        return (float) self::format($amount, $clampNegative);
    }

    public static function minorToFloat(int $minorUnits): float
    {
        return (float) self::formatMinor($minorUnits);
    }

    /**
     * @param  iterable<mixed>  $values
     */
    public static function sumMinor(iterable $values, ?callable $resolver = null, bool $clampNegative = false): int
    {
        $total = 0;

        foreach ($values as $key => $value) {
            $amount = $resolver !== null ? $resolver($value, $key) : $value;
            $total += self::minorUnits($amount, $clampNegative);
        }

        return $total;
    }

    public static function greaterThan(mixed $left, mixed $right): bool
    {
        return self::minorUnits($left) > self::minorUnits($right);
    }

    public static function greaterThanOrEqual(mixed $left, mixed $right): bool
    {
        return self::minorUnits($left) >= self::minorUnits($right);
    }

    public static function lessThan(mixed $left, mixed $right): bool
    {
        return self::minorUnits($left) < self::minorUnits($right);
    }

    public static function lessThanOrEqual(mixed $left, mixed $right): bool
    {
        return self::minorUnits($left) <= self::minorUnits($right);
    }

    public static function isPositive(mixed $amount): bool
    {
        return self::minorUnits($amount) > 0;
    }

    public static function isZeroOrNegative(mixed $amount): bool
    {
        return self::minorUnits($amount) <= 0;
    }

    private static function normalizeDecimalString(mixed $amount): ?string
    {
        if ($amount instanceof BackedEnum) {
            $amount = $amount->value;
        }

        if ($amount === null || $amount === '') {
            return '0';
        }

        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                return null;
            }

            return rtrim(rtrim(sprintf('%.10F', $amount), '0'), '.') ?: '0';
        }

        if (! is_string($amount) && ! is_numeric($amount)) {
            return null;
        }

        $normalized = trim((string) $amount);
        if ($normalized === '') {
            return '0';
        }

        $normalized = str_replace(',', '', $normalized);
        if (str_contains($normalized, 'e') || str_contains($normalized, 'E')) {
            if (! is_numeric($normalized)) {
                return null;
            }

            return rtrim(rtrim(sprintf('%.10F', (float) $normalized), '0'), '.') ?: '0';
        }

        return $normalized;
    }
}
