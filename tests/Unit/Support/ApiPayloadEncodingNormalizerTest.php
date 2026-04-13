<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiPayloadEncodingNormalizer;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class ApiPayloadEncodingNormalizerTest extends TestCase
{
    #[Group('booking-smoke')]
    public function test_it_repairs_nested_mojibake_strings_without_touching_non_strings(): void
    {
        $payload = [
            'message' => $this->encodeMojibake('Không có'),
            'nested' => [
                'title' => $this->encodeMojibake('Sơ đồ bàn'),
            ],
            'count' => 2,
            'status' => true,
        ];

        $normalized = ApiPayloadEncodingNormalizer::normalize($payload);

        $this->assertGreaterThan(0, $normalized['replacement_count']);
        $this->assertSame('Không có', $normalized['value']['message']);
        $this->assertSame('Sơ đồ bàn', $normalized['value']['nested']['title']);
        $this->assertSame(2, $normalized['value']['count']);
        $this->assertTrue($normalized['value']['status']);
    }

    #[Group('booking-smoke')]
    public function test_it_leaves_clean_payloads_unchanged(): void
    {
        $payload = [
            'message' => 'Ready',
            'title' => 'Không có',
        ];

        $normalized = ApiPayloadEncodingNormalizer::normalize($payload);

        $this->assertSame(0, $normalized['replacement_count']);
        $this->assertSame($payload, $normalized['value']);
    }

    private function encodeMojibake(string $value): string
    {
        $bytes = unpack('C*', $value);
        self::assertIsArray($bytes);

        $mapped = '';

        foreach ($bytes as $byte) {
            $mapped .= mb_chr(match ($byte) {
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
                default => $byte,
            }, 'UTF-8');
        }

        return $mapped;
    }
}
