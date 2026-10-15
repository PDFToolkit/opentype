<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

final readonly class Os2Reader
{
    public function read(BinaryView $table): Os2Table
    {
        $version = $table->uint16(0);
        if ($version > 5) {
            throw new UnsupportedFontException('Only OS/2 versions 0 to 5 are supported.');
        }
        $legacy = $version === 0 && $table->size() === 68;
        $length = match ($version) {
            0 => $legacy ? 68 : 78,
            1 => 86,
            5 => 100,
            default => 96,
        };
        if ($table->size() < $length) {
            throw new MalformedFontException('The OS/2 table length does not match its version.');
        }
        $panose = [];
        for ($index = 0; $index < 10; ++$index) {
            $panose[] = $table->uint8(32 + $index);
        }
        try {
            return new Os2Table(
                $version,
                $table->int16(2),
                $table->uint16(4),
                $table->uint16(6),
                new EmbeddingRights($table->uint16(8), $version),
                $table->int16(10),
                $table->int16(12),
                $table->int16(14),
                $table->int16(16),
                $table->int16(18),
                $table->int16(20),
                $table->int16(22),
                $table->int16(24),
                $table->int16(26),
                $table->int16(28),
                $table->int16(30),
                new Panose($panose),
                new MetricRanges([$table->uint32(42), $table->uint32(46), $table->uint32(50), $table->uint32(54)]),
                $table->bytes(58, 4),
                $table->uint16(62),
                $table->uint16(64),
                $table->uint16(66),
                $legacy ? null : $table->int16(68),
                $legacy ? null : $table->int16(70),
                $legacy ? null : $table->int16(72),
                $legacy ? null : $table->uint16(74),
                $legacy ? null : $table->uint16(76),
                $version >= 1 ? new MetricRanges([$table->uint32(78), $table->uint32(82)]) : null,
                $version >= 2 ? $table->int16(86) : null,
                $version >= 2 ? $table->int16(88) : null,
                $version >= 2 ? $table->uint16(90) : null,
                $version >= 2 ? $table->uint16(92) : null,
                $version >= 2 ? $table->uint16(94) : null,
                $version === 5 ? $table->uint16(96) : null,
                $version === 5 ? $table->uint16(98) : null,
            );
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed OS/2 fields.', 0, $error);
        }
    }
}
