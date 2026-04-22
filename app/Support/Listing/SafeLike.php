<?php

declare(strict_types=1);

namespace App\Support\Listing;

final class SafeLike
{
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
