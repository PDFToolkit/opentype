<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class LinearThresholdReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }
    public function read(BinaryView $table, int $glyphCount): LinearThresholds
    {
        if ($table->uint16(0) !== 0) {
            throw new UnsupportedFontException('Only LTSH version 0 is supported.');
        }
        if ($glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs || $glyphCount > $this->limits->maxLayoutRecords || $glyphCount !== $table->uint16(2) || $table->size() !== 4 + $glyphCount || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('Invalid LTSH glyph count, size or budget.');
        }
        $values = [];
        for ($glyph = 0; $glyph < $glyphCount; ++$glyph) {
            $values[] = $table->uint8(4 + $glyph);
        }
        return new LinearThresholds($values);
    }
}
