<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Outline\GlyphBounds;

/** Complete head 1.0 fields; LONGDATETIME values are signed seconds since 1904 UTC. */
final readonly class FontHeader
{
    public function __construct(public float $fontRevision, public int $checksumAdjustment, public int $flags, public int $unitsPerEm, public int $created, public int $modified, public GlyphBounds $bounds, public int $macStyle, public int $lowestRecPPEM, public int $fontDirectionHint, public int $indexToLocFormat, public int $glyphDataFormat = 0)
    {
        if (!is_finite($fontRevision) || $fontRevision < -32768 || $fontRevision >= 32768 || $checksumAdjustment < 0 || $checksumAdjustment > 0xFFFFFFFF || $flags < 0 || $flags > 0x7FFF || $unitsPerEm < 16 || $unitsPerEm > 16384 || $macStyle < 0 || $macStyle > 127 || $lowestRecPPEM < 0 || $lowestRecPPEM > 65535 || $fontDirectionHint < -2 || $fontDirectionHint > 2 || !in_array($indexToLocFormat, [0, 1], true) || $glyphDataFormat !== 0) {
            throw new \InvalidArgumentException('Invalid head version 1.0 fields.');
        }
    }
}
