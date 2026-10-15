<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

final readonly class HorizontalHeaderReader
{
    public function read(BinaryView $table): HorizontalHeader
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only hhea version 1.0 is supported.');
        }
        if ($table->size() !== 36 || $table->bytes(24, 10) !== str_repeat("\0", 10)) {
            throw new MalformedFontException('hhea reserved fields, format or size are invalid.');
        }
        try {
            return new HorizontalHeader($table->int16(4), $table->int16(6), $table->int16(8), $table->uint16(10), $table->int16(12), $table->int16(14), $table->int16(16), $table->int16(18), $table->int16(20), $table->int16(22), $table->uint16(34));
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed hhea fields.', 0, $error);
        }
    }
}
