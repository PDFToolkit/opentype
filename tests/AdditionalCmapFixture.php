<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

/** Original byte fixtures, not copied from a third-party font. */
final readonly class AdditionalCmapFixture
{
    public function subtable(int $format): string
    {
        return match ($format) {
            0 => pack('nnn', 0, 262, 0).str_repeat("\0", 65)."\1\0\3".str_repeat("\0", 188),
            2 => $this->highByte(),
            6 => pack('nnnnnnnn', 6, 16, 0, 65, 3, 1, 0, 3),
            8 => $this->mixed(),
            10 => pack('nnNNNNnnn', 10, 0, 26, 0, 0x10000, 3, 1, 0, 3),
            13 => pack('nnNNNNNN', 13, 0, 28, 0, 1, 0, 0x10FFFF, 3),
            default => throw new \InvalidArgumentException('Unknown test format.'),
        };
    }

    public function cmap(int $format, ?string $subtable = null, ?int $encoding = null, int $platform = 0): string
    {
        return pack('nnnnN', 0, 1, $platform, $encoding ?? ($format === 13 ? 6 : ($format >= 8 ? 4 : 3)), 12)
            .($subtable ?? $this->subtable($format));
    }

    /** @return array<int, int> */
    public function probes(int $format): array
    {
        return match ($format) {
            0, 6 => [0 => 0, 64 => 0, 65 => 1, 66 => 0, 67 => 3, 68 => 0, 0x10000 => 0],
            2 => [64 => 0, 65 => 1, 66 => 0, 67 => 2, 0x12 => 0, 0x1233 => 0, 0x1234 => 4, 0x1235 => 0, 0x1236 => 1, 0x1334 => 0, 0x10000 => 0],
            8 => [0 => 0, 1 => 0, 65 => 1, 66 => 2, 67 => 0, 0x10000 => 3, 0x10001 => 4, 0x10002 => 0],
            10 => [0 => 0, 65 => 0, 0xFFFF => 0, 0x10000 => 1, 0x10001 => 0, 0x10002 => 3, 0x10003 => 0],
            13 => [0 => 3, 65 => 3, 0xD7FF => 3, 0xE000 => 3, 0x10FFFF => 3],
            default => [],
        };
    }

    private function highByte(): string
    {
        $keys = str_repeat("\0", 512);
        $keys = substr_replace($keys, pack('n', 8), 2 * 0x12, 2);
        // Header 0: GID 2-1=1, missing remains zero, GID 3-1=2.
        // Header 1: GID 1-65533=4 (mod 65536), zero, 65534-65533=1.
        return pack('nnn', 2, 546, 0).$keys
            .pack('nnnn', 65, 3, 65535, 10)
            .pack('nnnn', 0x34, 3, 3, 8)
            .pack('nnnnnn', 2, 0, 3, 1, 0, 65534);
    }

    private function mixed(): string
    {
        $bits = str_repeat("\0", 8192);
        $bits[0] = "\x40"; // High word 1 starts a 32-bit code; U+0001 has no standalone mapping.

        return pack('nnNN', 8, 0, 8232, 0).$bits.pack('N', 2)
            .pack('NNN', 65, 66, 1).pack('NNN', 0x10000, 0x10001, 3);
    }
}
