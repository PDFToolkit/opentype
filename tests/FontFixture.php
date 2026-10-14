<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

/** Small directory fixture; fontTools-generated outline fixtures cover complete fonts. */
final class FontFixture
{
    /** @return array<string, string> */
    public static function tables(int $glyphCount = 4, int $version = 0x00010000): array
    {
        $head = str_repeat("\0", 54);
        $head = substr_replace($head, pack('N', 0x00010000), 0, 4);
        $head = substr_replace($head, pack('N', 0x5F0F3CF5), 12, 4);
        $head = substr_replace($head, pack('n', 1000), 18, 2);
        $hhea = pack('N', 0x00010000).str_repeat("\0", 30).pack('n', 2);
        $maxp = pack('Nn', $version === 0x4F54544F ? 0x00005000 : 0x00010000, $glyphCount);
        if ($version !== 0x4F54544F) {
            $maxp .= str_repeat("\0", 26);
        }

        return [
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => pack('nnnnnn', 500, 0, 600, 0xFFEC, 0xFFFB, 20),
            'maxp' => $maxp,
        ];
    }

    /** @param array<string, string> $tables */
    public static function build(array $tables, int $version = 0x00010000): string
    {
        ksort($tables, SORT_STRING);
        $count = count($tables);
        $power = 1;
        $selector = 0;
        while ($power * 2 <= $count) {
            $power *= 2;
            ++$selector;
        }
        $header = pack('Nnnnn', $version, $count, $power * 16, $selector, 16 * ($count - $power));
        $body = '';
        $offset = 12 + 16 * $count;
        $headOffset = null;
        foreach ($tables as $tag => $data) {
            if ($tag === 'head') {
                $data = substr_replace($data, "\0\0\0\0", 8, 4);
                $headOffset = $offset;
            }
            $header .= $tag.pack('NNN', self::checksum($data), $offset, strlen($data));
            $padded = str_pad($data, (strlen($data) + 3) & ~3, "\0");
            $body .= $padded;
            $offset += strlen($padded);
        }
        $font = $header.$body;
        if ($headOffset !== null) {
            $font = substr_replace($font, pack('N', (0xB1B0AFBA - self::checksum($font)) & 0xFFFFFFFF), $headOffset + 8, 4);
        }

        return $font;
    }

    private static function checksum(string $bytes): int
    {
        $words = unpack('N*', str_pad($bytes, (strlen($bytes) + 3) & ~3, "\0"));
        if ($words === false) {
            throw new \RuntimeException('Unable to construct a test checksum.');
        }

        return array_sum($words) & 0xFFFFFFFF;
    }
}
