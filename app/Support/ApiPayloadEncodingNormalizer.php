<?php

declare(strict_types=1);

namespace App\Support;

final class ApiPayloadEncodingNormalizer
{
    /**
     * @var array<int, int>
     */
    private const WINDOWS_1252_CODEPOINTS = [
        0x80 => 0x20AC,
        0x82 => 0x201A,
        0x83 => 0x0192,
        0x84 => 0x201E,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02C6,
        0x89 => 0x2030,
        0x8A => 0x0160,
        0x8B => 0x2039,
        0x8C => 0x0152,
        0x8E => 0x017D,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201C,
        0x94 => 0x201D,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02DC,
        0x99 => 0x2122,
        0x9A => 0x0161,
        0x9B => 0x203A,
        0x9C => 0x0153,
        0x9E => 0x017E,
        0x9F => 0x0178,
    ];

    /**
     * @var list<list<int>>
     */
    private const VIETNAMESE_BASES = [
        [0x61],
        [0x61, 0x0306],
        [0x61, 0x0302],
        [0x65],
        [0x65, 0x0302],
        [0x69],
        [0x6F],
        [0x6F, 0x0302],
        [0x6F, 0x031B],
        [0x75],
        [0x75, 0x031B],
        [0x79],
    ];

    /**
     * @var list<list<int>>
     */
    private const TONE_MARKS = [
        [],
        [0x0300],
        [0x0301],
        [0x0309],
        [0x0303],
        [0x0323],
    ];

    /**
     * @var list<int>
     */
    private const SPECIAL_CHARACTERS = [
        0x0111,
        0x0110,
        0x2013,
        0x2014,
        0x2018,
        0x2019,
        0x201C,
        0x201D,
        0x2022,
    ];

    /**
     * @var array<string, string>|null
     */
    private static ?array $replacements = null;

    /**
     * @return array{replacement_count: int, value: mixed}
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            return self::normalizeString($value);
        }

        if (! is_array($value)) {
            return [
                'replacement_count' => 0,
                'value' => $value,
            ];
        }

        $replacementCount = 0;
        $changed = false;
        $normalized = [];

        foreach ($value as $key => $item) {
            $result = self::normalize($item);
            $replacementCount += $result['replacement_count'];
            $changed = $changed || $result['value'] !== $item;
            $normalized[$key] = $result['value'];
        }

        return [
            'replacement_count' => $replacementCount,
            'value' => $changed ? $normalized : $value,
        ];
    }

    /**
     * @return array{replacement_count: int, value: string}
     */
    public static function normalizeString(string $value): array
    {
        if (! self::containsPotentialMojibake($value)) {
            return [
                'replacement_count' => 0,
                'value' => $value,
            ];
        }

        $normalized = $value;
        $replacementCount = 0;

        foreach (self::replacements() as $mojibake => $character) {
            if (! str_contains($normalized, $mojibake)) {
                continue;
            }

            $occurrences = substr_count($normalized, $mojibake);
            $replacementCount += $occurrences;
            $normalized = str_replace($mojibake, $character, $normalized);
        }

        return [
            'replacement_count' => $replacementCount,
            'value' => $normalized,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function replacements(): array
    {
        if (self::$replacements !== null) {
            return self::$replacements;
        }

        $characters = [];

        foreach (self::SPECIAL_CHARACTERS as $codePoint) {
            $character = mb_chr($codePoint, 'UTF-8');
            if (is_string($character) && $character !== '') {
                $characters[$character] = true;
            }
        }

        foreach (self::VIETNAMESE_BASES as $base) {
            foreach (self::TONE_MARKS as $tone) {
                $lower = self::normalizeCharacter(array_merge($base, $tone));
                if ($lower === null || self::isAsciiOnly($lower)) {
                    continue;
                }

                $characters[$lower] = true;

                $upper = mb_strtoupper($lower, 'UTF-8');
                if ($upper !== '') {
                    $characters[$upper] = true;
                }
            }
        }

        $replacements = [];

        foreach (array_keys($characters) as $character) {
            $mojibake = self::decodeUtf8BytesAsWindows1252($character);
            if ($mojibake !== $character) {
                $replacements[$mojibake] = $character;
            }
        }

        uksort($replacements, static fn (string $left, string $right): int => mb_strlen($right, 'UTF-8') <=> mb_strlen($left, 'UTF-8'));

        return self::$replacements = $replacements;
    }

    private static function decodeUtf8BytesAsWindows1252(string $character): string
    {
        $bytes = unpack('C*', $character);
        if ($bytes === false) {
            return $character;
        }

        $decoded = '';

        foreach ($bytes as $byte) {
            $decoded .= mb_chr(self::WINDOWS_1252_CODEPOINTS[$byte] ?? $byte, 'UTF-8');
        }

        return $decoded;
    }

    /**
     * @param  list<int>  $codePoints
     */
    private static function normalizeCharacter(array $codePoints): ?string
    {
        $character = '';

        foreach ($codePoints as $codePoint) {
            $current = mb_chr($codePoint, 'UTF-8');
            if (! is_string($current) || $current === '') {
                return null;
            }

            $character .= $current;
        }

        if (class_exists(\Normalizer::class)) {
            /** @var string|false $normalized */
            $normalized = \Normalizer::normalize($character, \Normalizer::FORM_C);

            return $normalized === false ? null : $normalized;
        }

        return $character;
    }

    private static function containsPotentialMojibake(string $value): bool
    {
        return str_contains($value, 'Ã')
            || str_contains($value, 'â')
            || str_contains($value, 'Ä')
            || str_contains($value, 'Æ');
    }

    private static function isAsciiOnly(string $value): bool
    {
        return preg_match('/^[\x00-\x7F]+$/', $value) === 1;
    }
}
