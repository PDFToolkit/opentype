<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class HorizontalDeviceMetricsReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }
    public function read(BinaryView $table, int $glyphCount): HorizontalDeviceMetrics
    {
        if ($table->uint16(0) !== 0) {
            throw new UnsupportedFontException('Only hdmx version 0 is supported.');
        }
        $count = $table->uint16(2);
        $size = $table->uint32(4);
        if ($glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs || $count * ($glyphCount + 1) > $this->limits->maxLayoutRecords || $size < $glyphCount + 2 || $size % 4 !== 0 || $table->size() !== 8 + $size * $count || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('Invalid hdmx sizes or record budget.');
        }
        try {
            $records = [];
            for ($index = 0; $index < $count; ++$index) {
                $offset = 8 + $index * $size;
                $widths = [];
                for ($glyph = 0; $glyph < $glyphCount; ++$glyph) {
                    $widths[] = $table->uint8($offset + 2 + $glyph);
                }
                if (trim($table->bytes($offset + 2 + $glyphCount, $size - 2 - $glyphCount), "\0") !== '') {
                    throw new MalformedFontException('hdmx padding must be zero.');
                }
                $records[] = new HorizontalDeviceRecord($table->uint8($offset), $table->uint8($offset + 1), $widths);
            }
            return new HorizontalDeviceMetrics($records);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Invalid hdmx records.', 0, $error);
        }
    }
}
