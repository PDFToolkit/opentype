<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\GlyphBounds;

final readonly class FontHeaderReader
{
    public function read(BinaryView $table): FontHeader
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only head version 1.0 is supported.');
        }
        if ($table->size() !== 54 || $table->uint32(12) !== 0x5F0F3CF5) {
            throw new MalformedFontException('Invalid head size or magic.');
        }
        $revision = $table->uint32(4);
        $revision = ($revision >= 0x80000000 ? $revision - 0x100000000 : $revision) / 65536.0;
        try {
            return new FontHeader($revision, $table->uint32(8), $table->uint16(16), $table->uint16(18), ($table->uint32(20) << 32) | $table->uint32(24), ($table->uint32(28) << 32) | $table->uint32(32), new GlyphBounds($table->int16(36), $table->int16(38), $table->int16(40), $table->int16(42)), $table->uint16(44), $table->uint16(46), $table->int16(48), $table->int16(50), $table->int16(52));
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed head fields.', 0, $error);
        }
    }
}
