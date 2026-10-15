<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Value\Tag;

final readonly class VerticalOriginsReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(SfntFont $font): VerticalOrigins
    {
        if ($font->version !== SfntVersion::Cff) {
            throw new UnsupportedFontException('VORG cannot supply TrueType glyph origins; use glyf and vmtx.');
        }
        $table = $font->table(new Tag('VORG'));
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only VORG version 1.0 is supported.');
        }
        $count = $table->uint16(6);
        if ($count > $this->limits->maxLayoutRecords || $count > $font->glyphCount() || $table->size() !== 8 + 4 * $count) {
            throw new MalformedFontException('Invalid VORG dimensions or record budget.');
        }
        $origins = [];
        $previous = -1;
        for ($index = 0; $index < $count; ++$index) {
            $glyph = $table->uint16(8 + 4 * $index);
            if ($glyph <= $previous || $glyph >= $font->glyphCount()) {
                throw new MalformedFontException('Vertical origin records must be ordered unique glyph IDs.');
            }
            $origins[$glyph] = $table->int16(10 + 4 * $index);
            $previous = $glyph;
        }

        return new VerticalOrigins($font->glyphCount(), $table->int16(4), $origins);
    }
}
